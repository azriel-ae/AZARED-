<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;

/**
 * AZARED - tax_transactions is the single source of truth every tax
 * report reads from. Rows are written once (from Sale::checkout() /
 * Purchase::create()/markReceived()) and only the manual invoice
 * reference fields (invoice_no/invoice_date/invoice_status) may be
 * edited afterwards - the financial fields themselves are immutable.
 */
final class TaxTransaction
{
    private const PER_PAGE = 25;

    public static function record(
        PDO $db,
        ?int $taxId,
        string $taxName,
        float $taxRate,
        float $taxableAmount,
        float $taxAmount,
        string $taxType,
        bool $taxInclusive,
        string $transactionType,
        int $transactionId,
        ?int $storeId
    ): void {
        $stmt = $db->prepare(
            'INSERT INTO tax_transactions
                (tax_id, tax_name, tax_rate, taxable_amount, tax_amount, tax_type, tax_inclusive,
                 transaction_type, transaction_id, store_id)
             VALUES
                (:tax_id, :tax_name, :tax_rate, :taxable_amount, :tax_amount, :tax_type, :tax_inclusive,
                 :transaction_type, :transaction_id, :store_id)'
        );
        $stmt->execute([
            'tax_id'            => $taxId,
            'tax_name'          => $taxName,
            'tax_rate'          => $taxRate,
            'taxable_amount'    => round($taxableAmount, 2),
            'tax_amount'        => round($taxAmount, 2),
            'tax_type'          => $taxType,
            'tax_inclusive'     => $taxInclusive ? 1 : 0,
            'transaction_type'  => $transactionType,
            'transaction_id'    => $transactionId,
            'store_id'          => $storeId,
        ]);
    }

    private static function buildWhere(array $filters, string $taxType): array
    {
        $where = ["tt.tax_type = :tax_type"];
        $params = ['tax_type' => $taxType];

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(tt.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(tt.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['store_id'])) {
            $where[] = 'tt.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }
        if (!empty($filters['tax_id'])) {
            $where[] = 'tt.tax_id = :tax_id';
            $params['tax_id'] = (int) $filters['tax_id'];
        }
        if (!empty($filters['invoice_status'])) {
            $where[] = 'tt.invoice_status = :invoice_status';
            $params['invoice_status'] = $filters['invoice_status'];
        }

        return [$where, $params];
    }

    /**
     * Pajak Keluaran (/tax/output): one row per taxed sale line, joined
     * back to the sale for invoice/customer/store context.
     */
    public static function outputReport(array $filters, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        [$where, $params] = self::buildWhere($filters, 'output');

        if (!empty($filters['search'])) {
            $where[] = '(s.invoice_no LIKE :search OR c.name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 's.customer_id = :customer_id';
            $params['customer_id'] = (int) $filters['customer_id'];
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM tax_transactions tt
             JOIN sales s ON s.id = tt.transaction_id AND tt.transaction_type = 'sale'
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = Database::connection()->prepare(
            "SELECT tt.*, s.invoice_no, s.created_at AS transaction_date, c.name AS customer_name, st.name AS store_name
             FROM tax_transactions tt
             JOIN sales s ON s.id = tt.transaction_id AND tt.transaction_type = 'sale'
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN stores st ON st.id = tt.store_id
             WHERE {$whereSql}
             ORDER BY tt.created_at DESC
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

    public static function outputReportAll(array $filters): array
    {
        [$where, $params] = self::buildWhere($filters, 'output');
        if (!empty($filters['search'])) {
            $where[] = '(s.invoice_no LIKE :search OR c.name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 's.customer_id = :customer_id';
            $params['customer_id'] = (int) $filters['customer_id'];
        }
        $whereSql = implode(' AND ', $where);

        $stmt = Database::connection()->prepare(
            "SELECT tt.*, s.invoice_no, s.created_at AS transaction_date, c.name AS customer_name, st.name AS store_name
             FROM tax_transactions tt
             JOIN sales s ON s.id = tt.transaction_id AND tt.transaction_type = 'sale'
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN stores st ON st.id = tt.store_id
             WHERE {$whereSql}
             ORDER BY tt.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Pajak Masukan (/tax/input): one row per taxed purchase line.
     */
    public static function inputReport(array $filters, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        [$where, $params] = self::buildWhere($filters, 'input');

        if (!empty($filters['search'])) {
            $where[] = '(p.purchase_no LIKE :search OR sup.name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['supplier_id'])) {
            $where[] = 'p.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM tax_transactions tt
             JOIN purchases p ON p.id = tt.transaction_id AND tt.transaction_type = 'purchase'
             LEFT JOIN suppliers sup ON sup.id = p.supplier_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = Database::connection()->prepare(
            "SELECT tt.*, p.purchase_no, p.purchase_date AS transaction_date, sup.name AS supplier_name, st.name AS store_name
             FROM tax_transactions tt
             JOIN purchases p ON p.id = tt.transaction_id AND tt.transaction_type = 'purchase'
             LEFT JOIN suppliers sup ON sup.id = p.supplier_id
             LEFT JOIN stores st ON st.id = tt.store_id
             WHERE {$whereSql}
             ORDER BY tt.created_at DESC
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

    public static function inputReportAll(array $filters): array
    {
        [$where, $params] = self::buildWhere($filters, 'input');
        if (!empty($filters['search'])) {
            $where[] = '(p.purchase_no LIKE :search OR sup.name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['supplier_id'])) {
            $where[] = 'p.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }
        $whereSql = implode(' AND ', $where);

        $stmt = Database::connection()->prepare(
            "SELECT tt.*, p.purchase_no, p.purchase_date AS transaction_date, sup.name AS supplier_name, st.name AS store_name
             FROM tax_transactions tt
             JOIN purchases p ON p.id = tt.transaction_id AND tt.transaction_type = 'purchase'
             LEFT JOIN suppliers sup ON sup.id = p.supplier_id
             LEFT JOIN stores st ON st.id = tt.store_id
             WHERE {$whereSql}
             ORDER BY tt.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Summary totals for /tax (dashboard) and /reports/tax: total DPP +
     * tax amount, split by output (penjualan) vs input (pembelian).
     */
    public static function summary(array $filters): array
    {
        $result = ['output' => ['taxable' => 0.0, 'tax' => 0.0, 'count' => 0], 'input' => ['taxable' => 0.0, 'tax' => 0.0, 'count' => 0]];

        foreach (['output', 'input'] as $type) {
            [$where, $params] = self::buildWhere($filters, $type);
            $joinTable = $type === 'output'
                ? "JOIN sales s ON s.id = tt.transaction_id AND tt.transaction_type = 'sale'"
                : "JOIN purchases p ON p.id = tt.transaction_id AND tt.transaction_type = 'purchase'";
            $stmt = Database::connection()->prepare(
                "SELECT COALESCE(SUM(tt.taxable_amount),0) AS taxable, COALESCE(SUM(tt.tax_amount),0) AS tax, COUNT(*) AS cnt
                 FROM tax_transactions tt {$joinTable}
                 WHERE " . implode(' AND ', $where)
            );
            $stmt->execute($params);
            $row = $stmt->fetch();
            $result[$type] = [
                'taxable' => (float) ($row['taxable'] ?? 0),
                'tax'     => (float) ($row['tax'] ?? 0),
                'count'   => (int) ($row['cnt'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Update the manual tax-invoice reference fields for every
     * tax_transactions row belonging to one sale/purchase at once
     * (a transaction is usually invoiced as a whole, not per line).
     * Blocked if the transaction's date falls inside a CLOSED tax period.
     */
    public static function updateInvoice(string $transactionType, int $transactionId, array $data): bool
    {
        $db = Database::connection();

        $dateCol = $transactionType === 'sale' ? 's.created_at' : 'p.purchase_date';
        $joinTable = $transactionType === 'sale'
            ? "JOIN sales s ON s.id = tt.transaction_id"
            : "JOIN purchases p ON p.id = tt.transaction_id";
        $dateStmt = $db->prepare(
            "SELECT DATE({$dateCol}) FROM tax_transactions tt {$joinTable}
             WHERE tt.transaction_type = :type AND tt.transaction_id = :id LIMIT 1"
        );
        $dateStmt->execute(['type' => $transactionType, 'id' => $transactionId]);
        $txDate = $dateStmt->fetchColumn();

        if ($txDate && TaxPeriod::isDateClosed((string) $txDate)) {
            return false;
        }

        $stmt = $db->prepare(
            'UPDATE tax_transactions SET invoice_no = :invoice_no, invoice_date = :invoice_date, invoice_status = :invoice_status
             WHERE transaction_type = :type AND transaction_id = :id'
        );
        $stmt->execute([
            'invoice_no'     => $data['invoice_no'] !== '' ? $data['invoice_no'] : null,
            'invoice_date'   => $data['invoice_date'] !== '' ? $data['invoice_date'] : null,
            'invoice_status' => $data['invoice_status'],
            'type'           => $transactionType,
            'id'             => $transactionId,
        ]);
        return true;
    }

    public static function forTransaction(string $transactionType, int $transactionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM tax_transactions WHERE transaction_type = :type AND transaction_id = :id ORDER BY id ASC'
        );
        $stmt->execute(['type' => $transactionType, 'id' => $transactionId]);
        return $stmt->fetchAll();
    }
}
