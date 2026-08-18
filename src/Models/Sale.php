<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;
use RuntimeException;

/**
 * AZARED - POS sales (checkout) engine.
 *
 * SECURITY / INTEGRITY RULES ENFORCED HERE:
 *  - Every write happens inside a single PDO transaction; any failure
 *    rolls back the whole sale (no half-written invoices).
 *  - Product prices and tax are always re-read from the database at
 *    checkout time - the client's displayed price is never trusted.
 *  - Every product row touched is locked with SELECT ... FOR UPDATE
 *    before its stock is changed, so two concurrent checkouts for the
 *    same product can never both succeed against a stock level that
 *    only supports one of them.
 *  - Stock cannot go negative unless the product explicitly allows it.
 *  - The invoice number is generated from InvoiceNumber (also lock-based)
 *    inside the same transaction, so numbers are gap-free and unique.
 */
final class Sale
{
    /**
     * @param array $cart [['product_id'=>int,'qty'=>float,'discount_amount'=>float], ...]
     * @param array $payments [['method'=>string,'amount'=>float,'reference_no'=>?string], ...]
     */
    public static function checkout(
        array $cart,
        array $payments,
        int $userId,
        ?int $storeId,
        ?int $customerId,
        string $discountType,
        float $discountValue,
        ?string $note
    ): array {
        if (empty($cart)) {
            throw new RuntimeException('CART_EMPTY');
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $subtotal = 0.0;
            $taxTotal = 0.0;
            $lineItems = [];

            foreach ($cart as $line) {
                $productId = (int) $line['product_id'];
                $qty = (float) $line['qty'];
                if ($qty <= 0) {
                    throw new RuntimeException('INVALID_QTY');
                }

                // Lock the row: authoritative price, tax, and stock come
                // from here - never from what the client sent.
                $product = Product::lockForUpdate($db, $productId);
                if (!$product || $product['status'] !== 'active') {
                    throw new RuntimeException('PRODUCT_UNAVAILABLE:' . $productId);
                }

                $unitPrice = (float) $product['sell_price'];
                if (
                    $product['wholesale_price'] !== null &&
                    $product['wholesale_min_qty'] !== null &&
                    $qty >= (float) $product['wholesale_min_qty']
                ) {
                    $unitPrice = (float) $product['wholesale_price'];
                }

                $itemDiscount = max(0.0, (float) ($line['discount_amount'] ?? 0));
                $lineGross = round($unitPrice * $qty, 2);
                $itemDiscount = min($itemDiscount, $lineGross);

                $taxPercent = (float) $product['tax_percent'];
                $taxableBase = $lineGross - $itemDiscount;
                $itemTax = $taxPercent > 0 && !$product['tax_inclusive']
                    ? round($taxableBase * $taxPercent / 100, 2)
                    : 0.0;

                $lineSubtotal = round($taxableBase + $itemTax, 2);

                $currentStock = (float) $product['stock'];
                $allowNegative = (bool) $product['allow_negative_stock'];
                if (($currentStock - $qty) < 0 && !$allowNegative) {
                    throw new RuntimeException('STOCK_INSUFFICIENT:' . $product['name']);
                }

                $subtotal += $lineGross - $itemDiscount;
                $taxTotal += $itemTax;

                $lineItems[] = [
                    'product'         => $product,
                    'qty'             => $qty,
                    'unit_price'      => $unitPrice,
                    'discount_amount' => $itemDiscount,
                    'tax_amount'      => $itemTax,
                    'subtotal'        => $lineSubtotal,
                    'current_stock'   => $currentStock,
                ];
            }

            $subtotal = round($subtotal, 2);

            $discountAmount = 0.0;
            if ($discountType === 'percent') {
                $discountAmount = round($subtotal * max(0.0, min(100.0, $discountValue)) / 100, 2);
            } else {
                $discountAmount = round(max(0.0, $discountValue), 2);
            }
            $discountAmount = min($discountAmount, $subtotal);

            $grandTotal = round($subtotal - $discountAmount + $taxTotal, 2);
            if ($grandTotal < 0) {
                $grandTotal = 0.0;
            }

            $paidTotal = 0.0;
            foreach ($payments as $p) {
                $amount = (float) ($p['amount'] ?? 0);
                if ($amount <= 0) {
                    throw new RuntimeException('INVALID_PAYMENT_AMOUNT');
                }
                $paidTotal += $amount;
            }
            $paidTotal = round($paidTotal, 2);

            if ($paidTotal < $grandTotal) {
                throw new RuntimeException('PAYMENT_INSUFFICIENT');
            }
            $changeAmount = round($paidTotal - $grandTotal, 2);

            $invoiceNo = InvoiceNumber::next($db, 'AZR');

            $saleStmt = $db->prepare(
                'INSERT INTO sales
                    (invoice_no, store_id, customer_id, user_id, subtotal, discount_type, discount_value,
                     discount_amount, tax_amount, grand_total, paid_total, change_amount, status, note)
                 VALUES
                    (:invoice_no, :store_id, :customer_id, :user_id, :subtotal, :discount_type, :discount_value,
                     :discount_amount, :tax_amount, :grand_total, :paid_total, :change_amount, "completed", :note)'
            );
            $saleStmt->execute([
                'invoice_no'       => $invoiceNo,
                'store_id'         => $storeId,
                'customer_id'      => $customerId,
                'user_id'          => $userId,
                'subtotal'         => $subtotal,
                'discount_type'    => $discountType,
                'discount_value'   => $discountValue,
                'discount_amount'  => $discountAmount,
                'tax_amount'       => $taxTotal,
                'grand_total'      => $grandTotal,
                'paid_total'       => $paidTotal,
                'change_amount'    => $changeAmount,
                'note'             => $note !== '' ? $note : null,
            ]);
            $saleId = (int) $db->lastInsertId();

            $itemStmt = $db->prepare(
                'INSERT INTO sale_items (sale_id, product_id, product_name, sku, qty, unit_price, cost_price, discount_amount, tax_amount, subtotal)
                 VALUES (:sale_id, :product_id, :product_name, :sku, :qty, :unit_price, :cost_price, :discount_amount, :tax_amount, :subtotal)'
            );

            foreach ($lineItems as $li) {
                $itemStmt->execute([
                    'sale_id'         => $saleId,
                    'product_id'      => $li['product']['id'],
                    'product_name'    => $li['product']['name'],
                    'sku'             => $li['product']['sku'],
                    'qty'             => $li['qty'],
                    'unit_price'      => $li['unit_price'],
                    // HPP basis: snapshot the product's weighted-average cost
                    // at the moment of sale - this is what every profit/HPP
                    // report reads from, so it never drifts if avg_cost
                    // later changes from subsequent purchases.
                    'cost_price'      => (float) ($li['product']['avg_cost'] ?? $li['product']['cost_price']),
                    'discount_amount' => $li['discount_amount'],
                    'tax_amount'      => $li['tax_amount'],
                    'subtotal'        => $li['subtotal'],
                ]);

                StockMovement::apply(
                    $db,
                    (int) $li['product']['id'],
                    'sale',
                    -1 * $li['qty'],
                    $li['current_stock'],
                    'sale',
                    $saleId,
                    $userId,
                    'Penjualan ' . $invoiceNo,
                    $storeId,
                    (bool) $li['product']['allow_negative_stock']
                );

                // Tax module integration: record an immutable pajak keluaran
                // (output tax) snapshot for this line, independent of the
                // sale's own totals so a later change to the tax's rate in
                // /tax/settings never rewrites this historical figure.
                $taxPercent = (float) $li['product']['tax_percent'];
                if ($taxPercent > 0) {
                    $taxInclusive = (bool) $li['product']['tax_inclusive'];
                    $lineGross = round((float) $li['unit_price'] * $li['qty'], 2);
                    $taxableBase = $lineGross - $li['discount_amount'];
                    $taxAmountForRecord = $taxInclusive
                        ? round($taxableBase - ($taxableBase / (1 + $taxPercent / 100)), 2)
                        : (float) $li['tax_amount'];
                    $taxableForRecord = $taxInclusive ? ($taxableBase - $taxAmountForRecord) : $taxableBase;

                    $taxId = !empty($li['product']['tax_id']) ? (int) $li['product']['tax_id'] : null;
                    $taxName = $taxId ? (Tax::find($taxId)['name'] ?? 'PPN') : 'PPN';

                    TaxTransaction::record(
                        $db,
                        $taxId,
                        $taxName,
                        $taxPercent,
                        $taxableForRecord,
                        $taxAmountForRecord,
                        'output',
                        $taxInclusive,
                        'sale',
                        $saleId,
                        $storeId
                    );
                }
            }

            $paymentStmt = $db->prepare(
                'INSERT INTO sale_payments (sale_id, method, amount, reference_no) VALUES (:sale_id, :method, :amount, :reference_no)'
            );
            foreach ($payments as $p) {
                $paymentStmt->execute([
                    'sale_id'      => $saleId,
                    'method'       => $p['method'],
                    'amount'       => (float) $p['amount'],
                    'reference_no' => !empty($p['reference_no']) ? $p['reference_no'] : null,
                ]);
            }

            AuditLog::record($userId, 'sale.create', 'sale', $saleId, null, [
                'invoice_no'  => $invoiceNo,
                'grand_total' => $grandTotal,
                'items'       => count($lineItems),
            ]);

            $db->commit();

            return self::find($saleId);
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.*, c.name AS customer_name, c.phone AS customer_phone, u.full_name AS cashier_name,
                    st.name AS store_name, st.address AS store_address, st.phone AS store_phone
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN stores st ON st.id = s.store_id
             WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $sale = $stmt->fetch();
        if (!$sale) {
            return null;
        }

        $itemsStmt = Database::connection()->prepare('SELECT * FROM sale_items WHERE sale_id = :id ORDER BY id ASC');
        $itemsStmt->execute(['id' => $id]);
        $sale['items'] = $itemsStmt->fetchAll();

        $paymentsStmt = Database::connection()->prepare('SELECT * FROM sale_payments WHERE sale_id = :id ORDER BY id ASC');
        $paymentsStmt->execute(['id' => $id]);
        $sale['payments'] = $paymentsStmt->fetchAll();

        return $sale;
    }

    public static function findByInvoice(string $invoiceNo): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id FROM sales WHERE invoice_no = :inv LIMIT 1');
        $stmt->execute(['inv' => $invoiceNo]);
        $row = $stmt->fetch();
        return $row ? self::find((int) $row['id']) : null;
    }

    public static function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(s.invoice_no LIKE :search OR c.name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[] = 's.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(s.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(s.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = Database::connection()->prepare(
            "SELECT s.*, c.name AS customer_name, u.full_name AS cashier_name
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN users u ON u.id = s.user_id
             WHERE {$whereSql}
             ORDER BY s.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Full-featured filter for /reports/sales: tanggal, toko, kasir,
     * customer, metode pembayaran, status. Payment method is matched via
     * EXISTS against sale_payments since a sale may carry multiple methods
     * (split payment).
     */
    private static function reportWhere(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(s.invoice_no LIKE :search OR c.name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[] = 's.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(s.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(s.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['store_id'])) {
            $where[] = 's.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 's.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 's.customer_id = :customer_id';
            $params['customer_id'] = (int) $filters['customer_id'];
        }
        if (!empty($filters['payment_method'])) {
            $where[] = 'EXISTS (SELECT 1 FROM sale_payments sp WHERE sp.sale_id = s.id AND sp.method = :payment_method)';
            $params['payment_method'] = $filters['payment_method'];
        }

        return [implode(' AND ', $where), $params];
    }

    public static function report(array $filters, int $page = 1, int $perPage = 25): array
    {
        [$whereSql, $params] = self::reportWhere($filters);

        $countStmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = Database::connection()->prepare(
            "SELECT s.*, c.name AS customer_name, u.full_name AS cashier_name,
                    (SELECT GROUP_CONCAT(DISTINCT sp.method) FROM sale_payments sp WHERE sp.sale_id = s.id) AS payment_methods
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN users u ON u.id = s.user_id
             WHERE {$whereSql}
             ORDER BY s.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * All matching rows (no LIMIT) for CSV export / summary totals.
     */
    public static function reportAll(array $filters): array
    {
        [$whereSql, $params] = self::reportWhere($filters);
        $stmt = Database::connection()->prepare(
            "SELECT s.*, c.name AS customer_name, u.full_name AS cashier_name,
                    (SELECT GROUP_CONCAT(DISTINCT sp.method) FROM sale_payments sp WHERE sp.sale_id = s.id) AS payment_methods
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN users u ON u.id = s.user_id
             WHERE {$whereSql}
             ORDER BY s.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE sales SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public static function todayStats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT COALESCE(SUM(grand_total),0) AS total, COUNT(*) AS cnt
             FROM sales WHERE DATE(created_at) = CURDATE() AND status IN ('completed','partially_returned')"
        );
        return $stmt->fetch() ?: ['total' => 0, 'cnt' => 0];
    }

    public static function monthStats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT COALESCE(SUM(grand_total),0) AS total, COUNT(*) AS cnt
             FROM sales WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())
             AND status IN ('completed','partially_returned')"
        );
        return $stmt->fetch() ?: ['total' => 0, 'cnt' => 0];
    }

    public static function paymentMethodBreakdownToday(): array
    {
        $stmt = Database::connection()->query(
            "SELECT sp.method, COALESCE(SUM(sp.amount),0) AS total
             FROM sale_payments sp
             JOIN sales s ON s.id = sp.sale_id
             WHERE DATE(s.created_at) = CURDATE()
             GROUP BY sp.method ORDER BY total DESC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Estimated gross profit today = revenue recognised (sale subtotal,
     * excl. tax) minus HPP, using each item's cost_price SNAPSHOT taken
     * at the moment of sale (see checkout()) - not the product's current
     * cost, so this figure never silently drifts after later purchases.
     */
    public static function grossProfitTodayEstimate(): float
    {
        $stmt = Database::connection()->query(
            "SELECT COALESCE(SUM((si.unit_price - si.cost_price) * si.qty - si.discount_amount),0) AS profit
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             WHERE DATE(s.created_at) = CURDATE() AND s.status IN ('completed','partially_returned')"
        );
        return (float) $stmt->fetchColumn();
    }

    // =====================================================================
    // Finance / reporting helpers (Phase 3). $endDate is EXCLUSIVE
    // (half-open range [start, end)) so callers can pass "next day 00:00"
    // and avoid DATE()-wrapping every comparison.
    // =====================================================================

    public static function revenueBetween(string $startDate, string $endDate, ?int $storeId = null): array
    {
        $sql = "SELECT
                    COALESCE(SUM(subtotal + discount_amount),0) AS gross_sales,
                    COALESCE(SUM(discount_amount),0) AS discount,
                    COALESCE(SUM(subtotal),0) AS net_sales,
                    COALESCE(SUM(tax_amount),0) AS tax,
                    COALESCE(SUM(grand_total),0) AS grand_total,
                    COUNT(*) AS cnt
                FROM sales
                WHERE created_at >= :start AND created_at < :end
                  AND status IN ('completed','partially_returned','returned')";
        $params = ['start' => $startDate, 'end' => $endDate];
        if ($storeId) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = $storeId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: ['gross_sales' => 0, 'discount' => 0, 'net_sales' => 0, 'tax' => 0, 'grand_total' => 0, 'cnt' => 0];
    }

    public static function returnsBetween(string $startDate, string $endDate, ?int $storeId = null): float
    {
        $sql = "SELECT COALESCE(SUM(sr.refund_amount),0)
                FROM sales_returns sr
                JOIN sales s ON s.id = sr.sale_id
                WHERE sr.created_at >= :start AND sr.created_at < :end";
        $params = ['start' => $startDate, 'end' => $endDate];
        if ($storeId) {
            $sql .= ' AND s.store_id = :store_id';
            $params['store_id'] = $storeId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    /**
     * HPP (COGS) for the period: SUM of each sale line's snapshotted
     * cost_price x (qty actually kept by the customer, i.e. net of any
     * returned_qty on that same line).
     */
    public static function hppBetween(string $startDate, string $endDate, ?int $storeId = null): float
    {
        $sql = "SELECT COALESCE(SUM((si.qty - si.returned_qty) * si.cost_price),0)
                FROM sale_items si
                JOIN sales s ON s.id = si.sale_id
                WHERE s.created_at >= :start AND s.created_at < :end
                  AND s.status IN ('completed','partially_returned','returned')";
        $params = ['start' => $startDate, 'end' => $endDate];
        if ($storeId) {
            $sql .= ' AND s.store_id = :store_id';
            $params['store_id'] = $storeId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Per-product HPP breakdown for the period - the detail behind
     * hppBetween()'s single aggregate figure. Used by the dedicated
     * /reports/hpp screen (Laporan > HPP): for each product, quantity
     * sold (net of returns), average cost snapshotted at sale time,
     * total HPP, total revenue attributed to it, and the resulting
     * gross margin. Sorted by total HPP descending (highest
     * cost-impact products first).
     */
    public static function hppByProductBetween(string $startDate, string $endDate, ?int $storeId = null): array
    {
        $sql = "SELECT si.product_id, p.sku, p.name AS product_name,
                       COALESCE(c.name, '-') AS category_name,
                       SUM(si.qty - si.returned_qty) AS qty_net,
                       CASE WHEN SUM(si.qty - si.returned_qty) > 0
                            THEN SUM((si.qty - si.returned_qty) * si.cost_price) / SUM(si.qty - si.returned_qty)
                            ELSE 0 END AS avg_cost,
                       SUM((si.qty - si.returned_qty) * si.cost_price) AS total_hpp,
                       SUM((si.qty - si.returned_qty) * si.unit_price) AS total_revenue
                FROM sale_items si
                JOIN sales s ON s.id = si.sale_id
                JOIN products p ON p.id = si.product_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE s.created_at >= :start AND s.created_at < :end
                  AND s.status IN ('completed','partially_returned','returned')";
        $params = ['start' => $startDate, 'end' => $endDate];
        if ($storeId) {
            $sql .= ' AND s.store_id = :store_id';
            $params['store_id'] = $storeId;
        }
        $sql .= ' GROUP BY si.product_id, p.sku, p.name, c.name
                   HAVING SUM(si.qty - si.returned_qty) > 0
                   ORDER BY total_hpp DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Cash-in breakdown by payment method for the period (used to
     * attribute revenue to the Kas/Bank accounts in the cash flow report).
     */
    public static function paymentMethodBreakdownBetween(string $startDate, string $endDate, ?int $storeId = null): array
    {
        $sql = "SELECT sp.method, COALESCE(SUM(sp.amount),0) AS total
                FROM sale_payments sp
                JOIN sales s ON s.id = sp.sale_id
                WHERE s.created_at >= :start AND s.created_at < :end";
        $params = ['start' => $startDate, 'end' => $endDate];
        if ($storeId) {
            $sql .= ' AND s.store_id = :store_id';
            $params['store_id'] = $storeId;
        }
        $sql .= ' GROUP BY sp.method';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
