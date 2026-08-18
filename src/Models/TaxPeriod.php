<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class TaxPeriod
{
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT tp.*, u.full_name AS closed_by_name
             FROM tax_periods tp
             LEFT JOIN users u ON u.id = tp.closed_by
             ORDER BY tp.start_date DESC'
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM tax_periods WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $type, string $start, string $end): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO tax_periods (name, period_type, start_date, end_date) VALUES (:name, :type, :start, :end)'
        );
        $stmt->execute(['name' => $name, 'type' => $type, 'start' => $start, 'end' => $end]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function close(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE tax_periods SET status = 'closed', closed_by = :uid, closed_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['uid' => $userId, 'id' => $id]);
    }

    public static function reopen(int $id): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE tax_periods SET status = 'open', closed_by = NULL, closed_at = NULL WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * True if the given date falls inside any CLOSED period. Used to
     * block edits to tax invoice data for a date that has already been
     * locked down for filing.
     */
    public static function isDateClosed(string $date): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM tax_periods WHERE status = 'closed' AND :date BETWEEN start_date AND end_date"
        );
        $stmt->execute(['date' => $date]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
