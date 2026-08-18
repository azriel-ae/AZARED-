<?php

declare(strict_types=1);

namespace App\Auth;

use PDO;
use SessionHandlerInterface;

/**
 * AZARED - MySQL-backed session handler.
 *
 * WHY THIS EXISTS:
 * Vercel PHP functions run in ephemeral, stateless containers. PHP's
 * default file-based session storage (session.save_path) is NOT
 * reliable there: a subsequent request may be routed to a different
 * container instance with a different (or wiped) local disk, so a
 * user's session would randomly appear "logged out". Storing sessions
 * in MySQL (which is external and persistent) makes authentication
 * work correctly across serverless invocations.
 */
final class PdoSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private readonly PDO $db,
        private readonly int $lifetimeSeconds
    ) {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->db->prepare(
            'SELECT payload FROM sessions WHERE id = :id AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? (string) $row['payload'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $userId = $_SESSION['user_id'] ?? null;
        $expiresAt = date('Y-m-d H:i:s', time() + $this->lifetimeSeconds);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

        $stmt = $this->db->prepare(
            'INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity, expires_at)
             VALUES (:id, :user_id, :ip, :ua, :payload, :last_activity, :expires_at)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                payload = VALUES(payload),
                last_activity = VALUES(last_activity),
                expires_at = VALUES(expires_at)'
        );

        return $stmt->execute([
            'id'            => $id,
            'user_id'       => $userId,
            'ip'            => $ip,
            'ua'            => $ua,
            'payload'       => $data,
            'last_activity' => time(),
            'expires_at'    => $expiresAt,
        ]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM sessions WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->db->prepare('DELETE FROM sessions WHERE expires_at < NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    }
}
