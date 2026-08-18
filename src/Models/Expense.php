<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;

final class Expense
{
    private const PER_PAGE = 20;

    public static function generateNo(): string
    {
        $db = Database::connection();
        $prefix = 'EXP-' . date('Ymd');
        do {
            $no = $prefix . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $db->prepare('SELECT COUNT(*) FROM expenses WHERE expense_no = :no');
            $stmt->execute(['no' => $no]);
        } while (((int) $stmt->fetchColumn()) > 0);
        return $no;
    }

    public static function paginate(array $filters, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        [$whereSql, $params] = self::buildWhere($filters);

        $countStmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM expenses e
             LEFT JOIN expense_categories c ON c.id = e.category_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = Database::connection()->prepare(
            "SELECT e.*, c.name AS category_name, u.full_name AS user_name
             FROM expenses e
             LEFT JOIN expense_categories c ON c.id = e.category_id
             LEFT JOIN users u ON u.id = e.user_id
             WHERE {$whereSql}
             ORDER BY e.expense_date DESC, e.id DESC
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

    public static function reportAll(array $filters): array
    {
        [$whereSql, $params] = self::buildWhere($filters);
        $stmt = Database::connection()->prepare(
            "SELECT e.*, c.name AS category_name, u.full_name AS user_name
             FROM expenses e
             LEFT JOIN expense_categories c ON c.id = e.category_id
             LEFT JOIN users u ON u.id = e.user_id
             WHERE {$whereSql}
             ORDER BY e.expense_date DESC, e.id DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function buildWhere(array $filters): array
    {
        $where = ['e.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(e.description LIKE :search OR e.expense_no LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'e.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['payment_method'])) {
            $where[] = 'e.payment_method = :payment_method';
            $params['payment_method'] = $filters['payment_method'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'e.expense_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'e.expense_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['store_id'])) {
            $where[] = 'e.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        return [implode(' AND ', $where), $params];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.*, c.name AS category_name, u.full_name AS user_name
             FROM expenses e
             LEFT JOIN expense_categories c ON c.id = e.category_id
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.id = :id AND e.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data, int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO expenses (expense_no, store_id, category_id, description, amount, payment_method, expense_date, notes, user_id)
             VALUES (:expense_no, :store_id, :category_id, :description, :amount, :payment_method, :expense_date, :notes, :user_id)'
        );
        $stmt->execute([
            'expense_no'     => self::generateNo(),
            'store_id'       => $data['store_id'] ?: null,
            'category_id'    => $data['category_id'],
            'description'    => $data['description'],
            'amount'         => $data['amount'],
            'payment_method' => $data['payment_method'],
            'expense_date'   => $data['expense_date'],
            'notes'          => $data['notes'] !== '' ? $data['notes'] : null,
            'user_id'        => $userId,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE expenses SET category_id = :category_id, description = :description, amount = :amount,
                payment_method = :payment_method, expense_date = :expense_date, notes = :notes
             WHERE id = :id'
        );
        $stmt->execute([
            'category_id'    => $data['category_id'],
            'description'    => $data['description'],
            'amount'         => $data['amount'],
            'payment_method' => $data['payment_method'],
            'expense_date'   => $data['expense_date'],
            'notes'          => $data['notes'] !== '' ? $data['notes'] : null,
            'id'             => $id,
        ]);
    }

    public static function softDelete(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE expenses SET deleted_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function totalBetween(string $startDate, string $endDate, ?int $storeId = null): float
    {
        $sql = 'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE deleted_at IS NULL AND expense_date >= :start AND expense_date < :end';
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
     * Breakdown by category for the period - used to render the
     * "Biaya Operasional" line items on the Laba Rugi report.
     */
    public static function categoryBreakdownBetween(string $startDate, string $endDate, ?int $storeId = null): array
    {
        $sql = "SELECT c.id, c.name, COALESCE(SUM(e.amount),0) AS total
                FROM expenses e
                JOIN expense_categories c ON c.id = e.category_id
                WHERE e.deleted_at IS NULL AND e.expense_date >= :start AND e.expense_date < :end";
        $params = ['start' => $startDate, 'end' => $endDate];
        if ($storeId) {
            $sql .= ' AND e.store_id = :store_id';
            $params['store_id'] = $storeId;
        }
        $sql .= ' GROUP BY c.id, c.name ORDER BY total DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Cash-out breakdown by payment method for the period (kas/bank
     * attribution in the cash flow report).
     */
    public static function paymentMethodBreakdownBetween(string $startDate, string $endDate, ?int $storeId = null): array
    {
        $sql = 'SELECT payment_method AS method, COALESCE(SUM(amount),0) AS total
                FROM expenses WHERE deleted_at IS NULL AND expense_date >= :start AND expense_date < :end';
        $params = ['start' => $startDate, 'end' => $endDate];
        if ($storeId) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = $storeId;
        }
        $sql .= ' GROUP BY payment_method';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
