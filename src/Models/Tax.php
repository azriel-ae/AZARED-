<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * AZARED - tax definitions (taxes) with a fully historical rate table
 * (tax_rates). The rate is NEVER hardcoded or overwritten in place:
 * changing it closes the previous rate row (effective_to = day before
 * the new rate starts) and opens a new one, so every past transaction
 * that already snapshotted a rate stays accurate forever.
 */
final class Tax
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT t.*,
                    (SELECT tr.rate FROM tax_rates tr WHERE tr.tax_id = t.id AND tr.effective_to IS NULL LIMIT 1) AS current_rate
                FROM taxes t WHERE t.deleted_at IS NULL';
        if ($activeOnly) {
            $sql .= " AND t.status = 'active'";
        }
        $sql .= ' ORDER BY t.name ASC';
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.*,
                (SELECT tr.rate FROM tax_rates tr WHERE tr.tax_id = t.id AND tr.effective_to IS NULL LIMIT 1) AS current_rate
             FROM taxes t WHERE t.id = :id AND t.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM taxes WHERE code = :code AND deleted_at IS NULL';
        $params = ['code' => $code];
        if ($exceptId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Create a new tax definition together with its initial rate,
     * effective from today.
     */
    public static function create(array $data, int $userId): int
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO taxes (name, code, tax_type, tax_inclusive, status)
                 VALUES (:name, :code, :tax_type, :tax_inclusive, :status)'
            );
            $stmt->execute([
                'name'          => $data['name'],
                'code'          => $data['code'],
                'tax_type'      => $data['tax_type'],
                'tax_inclusive' => $data['tax_inclusive'] ? 1 : 0,
                'status'        => $data['status'],
            ]);
            $taxId = (int) $db->lastInsertId();

            $rateStmt = $db->prepare(
                'INSERT INTO tax_rates (tax_id, rate, effective_from, effective_to, created_by)
                 VALUES (:tax_id, :rate, :effective_from, NULL, :created_by)'
            );
            $rateStmt->execute([
                'tax_id'         => $taxId,
                'rate'           => $data['rate'],
                'effective_from' => $data['effective_from'] ?: date('Y-m-d'),
                'created_by'     => $userId,
            ]);

            $db->commit();
            return $taxId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Update the tax's descriptive fields only (name/code/type/
     * inclusive/status). Rate changes always go through addRate().
     */
    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE taxes SET name = :name, code = :code, tax_type = :tax_type,
                tax_inclusive = :tax_inclusive, status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'name'          => $data['name'],
            'code'          => $data['code'],
            'tax_type'      => $data['tax_type'],
            'tax_inclusive' => $data['tax_inclusive'] ? 1 : 0,
            'status'        => $data['status'],
            'id'            => $id,
        ]);
    }

    /**
     * Register a new rate for a tax, effective from a given date. The
     * previously "current" rate (effective_to IS NULL) is closed the day
     * before the new rate begins. Historical tax_transactions already
     * written keep their own snapshotted rate and are unaffected.
     */
    public static function addRate(int $taxId, float $rate, string $effectiveFrom, int $userId): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $closeStmt = $db->prepare(
                "UPDATE tax_rates SET effective_to = DATE_SUB(:effective_from, INTERVAL 1 DAY)
                 WHERE tax_id = :tax_id AND effective_to IS NULL"
            );
            $closeStmt->execute(['effective_from' => $effectiveFrom, 'tax_id' => $taxId]);

            $insertStmt = $db->prepare(
                'INSERT INTO tax_rates (tax_id, rate, effective_from, effective_to, created_by)
                 VALUES (:tax_id, :rate, :effective_from, NULL, :created_by)'
            );
            $insertStmt->execute([
                'tax_id'         => $taxId,
                'rate'           => $rate,
                'effective_from' => $effectiveFrom,
                'created_by'     => $userId,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function rateHistory(int $taxId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT tr.*, u.full_name AS created_by_name
             FROM tax_rates tr
             LEFT JOIN users u ON u.id = tr.created_by
             WHERE tr.tax_id = :tax_id
             ORDER BY tr.effective_from DESC'
        );
        $stmt->execute(['tax_id' => $taxId]);
        return $stmt->fetchAll();
    }

    /**
     * Batch version of rateHistory() for a whole page of taxes at once -
     * avoids one query per row (N+1) when rendering /tax/settings, which
     * loops over every tax to show its rate history in a modal.
     * Returns [tax_id => [rate rows...]].
     */
    public static function rateHistoryBatch(array $taxIds): array
    {
        $taxIds = array_values(array_unique(array_map('intval', $taxIds)));
        if (empty($taxIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($taxIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT tr.*, u.full_name AS created_by_name
             FROM tax_rates tr
             LEFT JOIN users u ON u.id = tr.created_by
             WHERE tr.tax_id IN ({$placeholders})
             ORDER BY tr.tax_id ASC, tr.effective_from DESC"
        );
        $stmt->execute($taxIds);
        $grouped = array_fill_keys($taxIds, []);
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['tax_id']][] = $row;
        }
        return $grouped;
    }

    public static function currentRate(int $taxId): ?float
    {
        $stmt = Database::connection()->prepare(
            'SELECT rate FROM tax_rates WHERE tax_id = :tax_id AND effective_to IS NULL LIMIT 1'
        );
        $stmt->execute(['tax_id' => $taxId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (float) $val : null;
    }

    public static function inUse(int $id): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE tax_id = :id');
        $stmt->execute(['id' => $id]);
        if (((int) $stmt->fetchColumn()) > 0) {
            return true;
        }
        $stmt = $db->prepare('SELECT COUNT(*) FROM tax_transactions WHERE tax_id = :id');
        $stmt->execute(['id' => $id]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Soft-deactivate rather than hard delete when the tax has ever been
     * used, so historical reports keep their labels intact.
     */
    public static function deactivate(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE taxes SET status = 'inactive' WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public static function delete(int $id): bool
    {
        if (self::inUse($id)) {
            return false;
        }
        $stmt = Database::connection()->prepare('UPDATE taxes SET deleted_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
