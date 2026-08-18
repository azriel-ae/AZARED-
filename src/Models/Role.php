<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class Role
{
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            "SELECT r.*, (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS user_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permission_count
             FROM roles r ORDER BY r.name ASC"
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM roles WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM roles WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($exceptId) {
            $sql .= ' AND id != :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public static function create(string $name, string $slug, ?string $description): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO roles (name, slug, description, is_system) VALUES (:name, :slug, :description, 0)'
        );
        $stmt->execute(['name' => $name, 'slug' => $slug, 'description' => $description]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $name, ?string $description): void
    {
        // Deliberately does NOT allow changing `slug` after creation:
        // the slug is what PermissionMiddleware/AuthService::hasRole()
        // and every seeded role_permissions grant is keyed on.
        $stmt = Database::connection()->prepare(
            'UPDATE roles SET name = :name, description = :description WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'name' => $name, 'description' => $description]);
    }

    /**
     * Refuses to delete a system role (is_system = 1) or a role that
     * still has users assigned to it, so nobody can accidentally strand
     * an account with a dangling role_id.
     */
    public static function delete(int $id): bool
    {
        $role = self::find($id);
        if (!$role || (int) $role['is_system'] === 1) {
            return false;
        }
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM user_roles WHERE role_id = :id');
        $stmt->execute(['id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }
        $del = Database::connection()->prepare('DELETE FROM roles WHERE id = :id');
        $del->execute(['id' => $id]);
        return true;
    }

    /** Permission slugs currently granted to this role. */
    public static function permissionSlugs(int $roleId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.slug FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role_id'
        );
        $stmt->execute(['role_id' => $roleId]);
        return array_column($stmt->fetchAll(), 'slug');
    }

    /**
     * Replaces the role's entire permission set atomically. Called from
     * the Role permission-matrix editor - every checked box in the
     * submitted form becomes the new complete grant list.
     */
    public static function syncPermissions(int $roleId, array $permissionIds): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
            $del->execute(['role_id' => $roleId]);

            if (!empty($permissionIds)) {
                $ins = $pdo->prepare(
                    'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
                );
                foreach (array_unique(array_map('intval', $permissionIds)) as $pid) {
                    $ins->execute(['role_id' => $roleId, 'permission_id' => $pid]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
