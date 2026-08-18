<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * AZARED - permission catalog.
 *
 * Permissions are NOT freely creatable through the UI: every slug here
 * must correspond to an actual `PermissionMiddleware::require('x.y')`
 * call somewhere in the codebase, or it would grant nothing. This model
 * only reads the catalog (seeded in database/seed.sql /
 * migration_*.sql) and reports which roles currently hold each one.
 * Editing WHICH roles have a permission happens on the Role screen
 * (Role::syncPermissions), not here.
 */
final class Permission
{
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM permissions ORDER BY group_name ASC, slug ASC'
        );
        return $stmt->fetchAll();
    }

    /** Permissions grouped by group_name, e.g. ['products' => [...], 'sales' => [...]] */
    public static function grouped(): array
    {
        $grouped = [];
        foreach (self::all() as $perm) {
            $grouped[$perm['group_name']][] = $perm;
        }
        return $grouped;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM permissions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * role_slug => [permission_slug => true] matrix, used to render the
     * read-only /permissions overview (which roles have which grants).
     */
    public static function roleMatrix(): array
    {
        $stmt = Database::connection()->query(
            'SELECT r.slug AS role_slug, p.slug AS permission_slug
             FROM role_permissions rp
             JOIN roles r ON r.id = rp.role_id
             JOIN permissions p ON p.id = rp.permission_id'
        );
        $matrix = [];
        foreach ($stmt->fetchAll() as $row) {
            $matrix[$row['role_slug']][$row['permission_slug']] = true;
        }
        return $matrix;
    }
}
