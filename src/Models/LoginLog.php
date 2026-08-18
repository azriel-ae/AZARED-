<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class LoginLog
{
    public static function record(?int $userId, string $usernameAttempted, string $status, ?string $reason = null): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO login_logs (user_id, username_attempted, ip_address, user_agent, status, reason)
             VALUES (:user_id, :username, :ip, :ua, :status, :reason)'
        );
        $stmt->execute([
            'user_id'  => $userId,
            'username' => $usernameAttempted,
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua'       => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            'status'   => $status,
            'reason'   => $reason,
        ]);
    }
}
