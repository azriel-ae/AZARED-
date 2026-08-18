<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * AZARED - concurrency-safe sequential document numbering.
 *
 * Uses a dedicated invoice_sequences table with SELECT ... FOR UPDATE
 * so two simultaneous checkouts/purchases can never be handed the same
 * number, even under load - this MUST be called from inside a
 * transaction the caller has already started on $db.
 *
 * Format: {PREFIX}-{YYYYMMDD}-{000001}, e.g. AZR-20260811-000001
 */
final class InvoiceNumber
{
    public static function next(PDO $db, string $prefix): string
    {
        $today = date('Ymd');
        $seqKey = $prefix . '-' . $today;

        // Atomic counter via MySQL's upsert + LAST_INSERT_ID(expr) idiom:
        // this is a single statement, so there is no window between
        // "check if the row exists" and "create/increment it" for two
        // concurrent requests to race on - unlike a SELECT ... FOR UPDATE
        // against a row that might not exist yet (which does NOT lock
        // anything and would let two transactions both try to INSERT the
        // same new seq_key, one of them failing on the PRIMARY KEY).
        $stmt = $db->prepare(
            'INSERT INTO invoice_sequences (seq_key, last_number) VALUES (:key, 1)
             ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)'
        );
        $stmt->execute(['key' => $seqKey]);
        $next = (int) $db->lastInsertId();

        return sprintf('%s-%s-%06d', $prefix, $today, $next);
    }
}
