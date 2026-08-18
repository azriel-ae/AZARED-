<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;
use RuntimeException;

/**
 * AZARED - purchasing from suppliers. When a purchase is recorded with
 * status "received" (the default), stock is increased automatically and
 * atomically, with the same locking discipline as Sale::checkout().
 */
final class Purchase
{
    public static function create(
        array $items,
        array $payments,
        int $supplierId,
        int $userId,
        ?int $storeId,
        string $purchaseDate,
        ?string $supplierInvoiceNo,
        float $discountAmount,
        ?string $note,
        string $status = 'received'
    ): array {
        if (empty($items)) {
            throw new RuntimeException('ITEMS_EMPTY');
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $subtotal = 0.0;
            $taxTotal = 0.0;
            $lineItems = [];

            foreach ($items as $line) {
                $productId = (int) $line['product_id'];
                $qty = (float) $line['qty'];
                $costPrice = (float) $line['cost_price'];
                if ($qty <= 0 || $costPrice < 0) {
                    throw new RuntimeException('INVALID_ITEM');
                }

                $product = $status === 'received'
                    ? Product::lockForUpdate($db, $productId)
                    : Product::find($productId);

                if (!$product) {
                    throw new RuntimeException('PRODUCT_NOT_FOUND:' . $productId);
                }

                $itemDiscount = max(0.0, (float) ($line['discount_amount'] ?? 0));
                $gross = round($costPrice * $qty, 2);
                $itemDiscount = min($itemDiscount, $gross);
                $taxPercent = max(0.0, (float) ($line['tax_percent'] ?? 0));
                $itemTax = round(($gross - $itemDiscount) * $taxPercent / 100, 2);
                $lineSubtotal = round($gross - $itemDiscount + $itemTax, 2);
                $taxId = !empty($line['tax_id']) ? (int) $line['tax_id'] : null;

                $subtotal += $gross;
                $taxTotal += $itemTax;

                $lineItems[] = [
                    'product'         => $product,
                    'qty'             => $qty,
                    'cost_price'      => $costPrice,
                    'discount_amount' => $itemDiscount,
                    'tax_amount'      => $itemTax,
                    'tax_percent'     => $taxPercent,
                    'tax_id'          => $taxId,
                    'subtotal'        => $lineSubtotal,
                    'current_stock'   => (float) $product['stock'],
                ];
            }

            $subtotal = round($subtotal, 2);
            $discountAmount = min(round(max(0.0, $discountAmount), 2), $subtotal);
            $total = round($subtotal - $discountAmount + $taxTotal, 2);

            $paidTotal = 0.0;
            foreach ($payments as $p) {
                $paidTotal += (float) ($p['amount'] ?? 0);
            }
            $paidTotal = round($paidTotal, 2);

            $purchaseNo = InvoiceNumber::next($db, 'PO');

            $stmt = $db->prepare(
                'INSERT INTO purchases
                    (purchase_no, supplier_invoice_no, supplier_id, store_id, user_id, purchase_date,
                     subtotal, discount_amount, tax_amount, total, paid_total, status, note)
                 VALUES
                    (:purchase_no, :supplier_invoice_no, :supplier_id, :store_id, :user_id, :purchase_date,
                     :subtotal, :discount_amount, :tax_amount, :total, :paid_total, :status, :note)'
            );
            $stmt->execute([
                'purchase_no'         => $purchaseNo,
                'supplier_invoice_no' => $supplierInvoiceNo !== '' ? $supplierInvoiceNo : null,
                'supplier_id'         => $supplierId,
                'store_id'            => $storeId,
                'user_id'             => $userId,
                'purchase_date'       => $purchaseDate,
                'subtotal'            => $subtotal,
                'discount_amount'     => $discountAmount,
                'tax_amount'          => $taxTotal,
                'total'               => $total,
                'paid_total'          => $paidTotal,
                'status'              => $status,
                'note'                => $note !== '' ? $note : null,
            ]);
            $purchaseId = (int) $db->lastInsertId();

            $itemStmt = $db->prepare(
                'INSERT INTO purchase_items (purchase_id, product_id, qty, cost_price, discount_amount, tax_amount, tax_id, subtotal)
                 VALUES (:purchase_id, :product_id, :qty, :cost_price, :discount_amount, :tax_amount, :tax_id, :subtotal)'
            );
            foreach ($lineItems as $li) {
                $itemStmt->execute([
                    'purchase_id'     => $purchaseId,
                    'product_id'      => $li['product']['id'],
                    'qty'             => $li['qty'],
                    'cost_price'      => $li['cost_price'],
                    'discount_amount' => $li['discount_amount'],
                    'tax_amount'      => $li['tax_amount'],
                    'tax_id'          => $li['tax_id'],
                    'subtotal'        => $li['subtotal'],
                ]);

                if ($status === 'received' && $li['tax_amount'] > 0) {
                    // Tax module integration: record pajak masukan (input
                    // tax) as an immutable snapshot, mirroring what
                    // Sale::checkout() does for pajak keluaran.
                    $taxName = $li['tax_id'] ? (Tax::find((int) $li['tax_id'])['name'] ?? 'PPN') : 'PPN';
                    TaxTransaction::record(
                        $db,
                        $li['tax_id'],
                        $taxName,
                        $li['tax_percent'],
                        round($li['cost_price'] * $li['qty'] - $li['discount_amount'], 2),
                        $li['tax_amount'],
                        'input',
                        false,
                        'purchase',
                        $purchaseId,
                        $storeId
                    );
                }

                if ($status === 'received') {
                    StockMovement::apply(
                        $db,
                        (int) $li['product']['id'],
                        'purchase',
                        $li['qty'],
                        $li['current_stock'],
                        'purchase',
                        $purchaseId,
                        $userId,
                        'Pembelian ' . $purchaseNo,
                        $storeId,
                        true
                    );

                    // HPP basis: recompute the weighted moving-average cost
                    // rather than blindly overwriting it with the latest
                    // purchase price. cost_price is still kept as a
                    // "last purchase price" reference field.
                    $newAvg = self::weightedAverageCost(
                        $li['current_stock'],
                        (float) ($li['product']['avg_cost'] ?? $li['product']['cost_price']),
                        $li['qty'],
                        $li['cost_price']
                    );
                    $db->prepare('UPDATE products SET cost_price = :cost, avg_cost = :avg WHERE id = :id')
                        ->execute(['cost' => $li['cost_price'], 'avg' => $newAvg, 'id' => $li['product']['id']]);
                }
            }

            $paymentStmt = $db->prepare(
                'INSERT INTO purchase_payments (purchase_id, method, amount, reference_no) VALUES (:purchase_id, :method, :amount, :reference_no)'
            );
            foreach ($payments as $p) {
                if ((float) ($p['amount'] ?? 0) <= 0) {
                    continue;
                }
                $paymentStmt->execute([
                    'purchase_id'  => $purchaseId,
                    'method'       => $p['method'],
                    'amount'       => (float) $p['amount'],
                    'reference_no' => !empty($p['reference_no']) ? $p['reference_no'] : null,
                ]);
            }

            AuditLog::record($userId, 'purchase.create', 'purchase', $purchaseId, null, [
                'purchase_no' => $purchaseNo,
                'total'       => $total,
                'status'      => $status,
            ]);

            $db->commit();
            return self::find($purchaseId);
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Mark a draft purchase as received: locks each product row and adds
     * stock atomically, exactly once (guarded by the status check inside
     * the same transaction to prevent double-receiving under concurrency).
     */
    public static function markReceived(int $purchaseId, int $userId): array
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM purchases WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $purchaseId]);
            $purchase = $stmt->fetch();

            if (!$purchase || $purchase['status'] !== 'draft') {
                throw new RuntimeException('NOT_DRAFT');
            }

            $itemsStmt = $db->prepare('SELECT * FROM purchase_items WHERE purchase_id = :id');
            $itemsStmt->execute(['id' => $purchaseId]);
            $items = $itemsStmt->fetchAll();

            foreach ($items as $item) {
                $product = Product::lockForUpdate($db, (int) $item['product_id']);
                if (!$product) {
                    throw new RuntimeException('PRODUCT_NOT_FOUND');
                }
                $oldStock = (float) $product['stock'];
                $oldAvg = (float) ($product['avg_cost'] ?? $product['cost_price']);

                StockMovement::apply(
                    $db,
                    (int) $product['id'],
                    'purchase',
                    (float) $item['qty'],
                    $oldStock,
                    'purchase',
                    $purchaseId,
                    $userId,
                    'Pembelian diterima ' . $purchase['purchase_no'],
                    $purchase['store_id'] ? (int) $purchase['store_id'] : null,
                    true
                );

                $newAvg = self::weightedAverageCost($oldStock, $oldAvg, (float) $item['qty'], (float) $item['cost_price']);
                $db->prepare('UPDATE products SET cost_price = :cost, avg_cost = :avg WHERE id = :id')
                    ->execute(['cost' => $item['cost_price'], 'avg' => $newAvg, 'id' => $product['id']]);

                if ((float) $item['tax_amount'] > 0) {
                    $taxId = !empty($item['tax_id']) ? (int) $item['tax_id'] : null;
                    $taxName = $taxId ? (Tax::find($taxId)['name'] ?? 'PPN') : 'PPN';
                    $taxPercent = ((float) $item['cost_price'] * (float) $item['qty'] - (float) $item['discount_amount']) > 0
                        ? round((float) $item['tax_amount'] / (((float) $item['cost_price'] * (float) $item['qty']) - (float) $item['discount_amount']) * 100, 3)
                        : 0.0;
                    TaxTransaction::record(
                        $db,
                        $taxId,
                        $taxName,
                        $taxPercent,
                        round((float) $item['cost_price'] * (float) $item['qty'] - (float) $item['discount_amount'], 2),
                        (float) $item['tax_amount'],
                        'input',
                        false,
                        'purchase',
                        $purchaseId,
                        $purchase['store_id'] ? (int) $purchase['store_id'] : null
                    );
                }
            }

            $db->prepare("UPDATE purchases SET status = 'received' WHERE id = :id")->execute(['id' => $purchaseId]);

            AuditLog::record($userId, 'purchase.receive', 'purchase', $purchaseId, null, null);

            $db->commit();
            return self::find($purchaseId);
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, s.name AS supplier_name, s.code AS supplier_code, u.full_name AS created_by_name
             FROM purchases p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $purchase = $stmt->fetch();
        if (!$purchase) {
            return null;
        }

        $itemsStmt = Database::connection()->prepare(
            'SELECT pi.*, pr.name AS product_name, pr.sku FROM purchase_items pi
             JOIN products pr ON pr.id = pi.product_id
             WHERE pi.purchase_id = :id ORDER BY pi.id ASC'
        );
        $itemsStmt->execute(['id' => $id]);
        $purchase['items'] = $itemsStmt->fetchAll();

        $paymentsStmt = Database::connection()->prepare('SELECT * FROM purchase_payments WHERE purchase_id = :id ORDER BY id ASC');
        $paymentsStmt->execute(['id' => $id]);
        $purchase['payments'] = $paymentsStmt->fetchAll();

        return $purchase;
    }

    public static function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(p.purchase_no LIKE :search OR p.supplier_invoice_no LIKE :search OR s.name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['supplier_id'])) {
            $where[] = 'p.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'p.purchase_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'p.purchase_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM purchases p LEFT JOIN suppliers s ON s.id = p.supplier_id WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = Database::connection()->prepare(
            "SELECT p.*, s.name AS supplier_name, u.full_name AS created_by_name
             FROM purchases p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE {$whereSql}
             ORDER BY p.created_at DESC
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
     * All matching rows (no LIMIT) for /reports/purchases CSV export.
     */
    public static function reportAll(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(p.purchase_no LIKE :search OR p.supplier_invoice_no LIKE :search OR s.name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['supplier_id'])) {
            $where[] = 'p.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'p.purchase_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'p.purchase_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        $whereSql = implode(' AND ', $where);

        $stmt = Database::connection()->prepare(
            "SELECT p.*, s.name AS supplier_name, u.full_name AS created_by_name
             FROM purchases p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE {$whereSql}
             ORDER BY p.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function todayTotal(): float
    {
        $stmt = Database::connection()->query(
            "SELECT COALESCE(SUM(total),0) FROM purchases WHERE DATE(created_at) = CURDATE() AND status = 'received'"
        );
        return (float) $stmt->fetchColumn();
    }

    /**
     * HPP basis: weighted moving-average cost.
     * newAvg = ((oldStock * oldAvg) + (qtyIn * costIn)) / (oldStock + qtyIn)
     * Falls back to the incoming cost if there is no prior stock to weigh
     * against (e.g. first-ever purchase of a product, or stock was zero).
     */
    public static function weightedAverageCost(float $oldStock, float $oldAvg, float $qtyIn, float $costIn): float
    {
        $newStock = $oldStock + $qtyIn;
        if ($newStock <= 0) {
            return $costIn;
        }
        return round((($oldStock * $oldAvg) + ($qtyIn * $costIn)) / $newStock, 4);
    }

    public static function totalBetween(string $startDate, string $endDate, ?int $storeId = null): float
    {
        $sql = "SELECT COALESCE(SUM(total),0) FROM purchases
                WHERE status = 'received' AND created_at >= :start AND created_at < :end";
        $params = ['start' => $startDate, 'end' => $endDate];
        if ($storeId) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = $storeId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Cash-out breakdown by payment method for the period (used to
     * attribute purchase spending to the Kas/Bank accounts in the cash
     * flow report).
     */
    public static function paymentMethodBreakdownBetween(string $startDate, string $endDate, ?int $storeId = null): array
    {
        $sql = "SELECT pp.method, COALESCE(SUM(pp.amount),0) AS total
                FROM purchase_payments pp
                JOIN purchases p ON p.id = pp.purchase_id
                WHERE p.created_at >= :start AND p.created_at < :end";
        $params = ['start' => $startDate, 'end' => $endDate];
        if ($storeId) {
            $sql .= ' AND p.store_id = :store_id';
            $params['store_id'] = $storeId;
        }
        $sql .= ' GROUP BY pp.method';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
