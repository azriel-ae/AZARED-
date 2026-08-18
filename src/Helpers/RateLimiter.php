<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;

/**
 * AZARED - simple brute-force protection for login.
 * Uses the login_logs table + a per-account lockout column on users,
 * so it works correctly across stateless serverless invocations
 * (no in-memory counters, which would not persist on Vercel).
 */
final class RateLimiter
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Returns true if the given username is currently locked out.
     */
    public function isLocked(string $username): bool
    {
        $stmt = $this->db->prepare(
            'SELECT locked_until FROM users WHERE username = :username LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();

        if (!$row || !$row['locked_until']) {
            return false;
        }

        return strtotime((string) $row['locked_until']) > time();
    }

    public function registerFailedAttempt(string $username): void
    {
        $maxAttempts = (int) config('login.max_attempts', 5);
        $lockoutMinutes = (int) config('login.lockout_minutes', 15);

        $stmt = $this->db->prepare(
            'UPDATE users
             SET failed_login_attempts = failed_login_attempts + 1,
                 locked_until = CASE
                    WHEN failed_login_attempts + 1 >= :max_attempts
                        THEN DATE_ADD(NOW(), INTERVAL :lockout_minutes MINUTE)
                    ELSE locked_until
                 END
             WHERE username = :username'
        );
        $stmt->execute([
            'max_attempts'     => $maxAttempts,
            'lockout_minutes'  => $lockoutMinutes,
            'username'         => $username,
        ]);
    }

    public function resetAttempts(string $username): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE username = :username'
        );
        $stmt->execute(['username' => $username]);
    }
}
