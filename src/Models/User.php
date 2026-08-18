<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;

final class User
{
    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM users WHERE username = :username AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Fetch a user's roles (slugs) as a flat array.
     */
    public static function roles(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.id, r.slug, r.name FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Fetch all permission slugs granted to a user via their role(s).
     * @return string[]
     */
    public static function permissions(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT p.slug FROM user_roles ur
             JOIN role_permissions rp ON rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        return array_column($stmt->fetchAll(), 'slug');
    }

    public static function storeAccess(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.id, s.name, s.code, usa.is_primary FROM user_store_access usa
             JOIN stores s ON s.id = usa.store_id
             WHERE usa.user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function all(int $limit = 50, int $offset = 0): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.full_name, u.username, u.email, u.status, u.last_login_at,
                    GROUP_CONCAT(DISTINCT r.name SEPARATOR ", ") AS role_names
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.deleted_at IS NULL
             GROUP BY u.id
             ORDER BY u.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Lightweight id/name list for report filter dropdowns (kasir, dsb).
     */
    public static function simpleList(): array
    {
        $stmt = Database::connection()->query(
            "SELECT id, full_name FROM users WHERE deleted_at IS NULL AND status = 'active' ORDER BY full_name ASC"
        );
        return $stmt->fetchAll();
    }

    public static function usernameExists(string $username): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM users WHERE username = :username'
        );
        $stmt->execute(['username' => $username]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        if ($email === '') {
            return false;
        }
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = ['email' => $email];
        if ($exceptId !== null) {
            $sql .= ' AND id != :except_id';
            $params['except_id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Create a new user. Only ever call this from a controller that has
     * already checked the `users.create` permission server-side.
     */
    public static function create(array $data, int $createdBy): int
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'INSERT INTO users (full_name, username, email, phone, password_hash, status, must_change_password, created_by)
                 VALUES (:full_name, :username, :email, :phone, :password_hash, :status, 1, :created_by)'
            );
            $stmt->execute([
                'full_name'     => $data['full_name'],
                'username'      => $data['username'],
                'email'         => $data['email'] !== '' ? $data['email'] : null,
                'phone'         => $data['phone'] !== '' ? $data['phone'] : null,
                'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
                'status'        => $data['status'],
                'created_by'    => $createdBy,
            ]);

            $userId = (int) $db->lastInsertId();

            $roleStmt = $db->prepare(
                'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
            );
            $roleStmt->execute(['user_id' => $userId, 'role_id' => (int) $data['role_id']]);

            if (!empty($data['store_ids']) && is_array($data['store_ids'])) {
                $storeStmt = $db->prepare(
                    'INSERT INTO user_store_access (user_id, store_id, is_primary) VALUES (:user_id, :store_id, :is_primary)'
                );
                foreach ($data['store_ids'] as $index => $storeId) {
                    $storeStmt->execute([
                        'user_id'    => $userId,
                        'store_id'   => (int) $storeId,
                        'is_primary' => $index === 0 ? 1 : 0,
                    ]);
                }
            }

            $db->commit();
            return $userId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function updateProfile(int $userId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET full_name = :full_name, email = :email, phone = :phone, status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'full_name' => $data['full_name'],
            'email'     => $data['email'] !== '' ? $data['email'] : null,
            'phone'     => $data['phone'] !== '' ? $data['phone'] : null,
            'status'    => $data['status'],
            'id'        => $userId,
        ]);
    }

    public static function updateRole(int $userId, int $roleId): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute(['user_id' => $userId]);
        $db->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute(['user_id' => $userId, 'role_id' => $roleId]);
    }

    public static function updateStoreAccess(int $userId, array $storeIds): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM user_store_access WHERE user_id = :user_id')->execute(['user_id' => $userId]);
        $stmt = $db->prepare(
            'INSERT INTO user_store_access (user_id, store_id, is_primary) VALUES (:user_id, :store_id, :is_primary)'
        );
        foreach ($storeIds as $index => $storeId) {
            $stmt->execute([
                'user_id'    => $userId,
                'store_id'   => (int) $storeId,
                'is_primary' => $index === 0 ? 1 : 0,
            ]);
        }
    }

    public static function resetPassword(int $userId, string $newPassword): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = 1 WHERE id = :id'
        );
        $stmt->execute([
            'hash' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            'id'   => $userId,
        ]);
    }

    public static function setStatus(int $userId, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $userId]);
    }

    public static function recordSuccessfulLogin(int $userId, string $ip): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET last_login_at = NOW(), last_login_ip = :ip, failed_login_attempts = 0, locked_until = NULL
             WHERE id = :id'
        );
        $stmt->execute(['ip' => $ip, 'id' => $userId]);
    }
}
