<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class Category
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS product_count
                FROM categories c WHERE c.deleted_at IS NULL';
        if ($activeOnly) {
            $sql .= " AND c.status = 'active'";
        }
        $sql .= ' ORDER BY c.name ASC';
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM categories WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM categories WHERE slug = :slug AND deleted_at IS NULL';
        $params = ['slug' => $slug];
        if ($exceptId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public static function create(string $name, string $slug): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO categories (name, slug) VALUES (:name, :slug)'
        );
        $stmt->execute(['name' => $name, 'slug' => $slug]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $name, string $slug, string $status): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE categories SET name = :name, slug = :slug, status = :status WHERE id = :id'
        );
        $stmt->execute(['name' => $name, 'slug' => $slug, 'status' => $status, 'id' => $id]);
    }

    public static function inUse(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM products WHERE category_id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Soft-delete. Refused when the category is still referenced by any
     * product, unless the caller explicitly reassigns those products first
     * (see ProductController::bulkReassignCategory) -- this keeps product
     * data from ever pointing at a "ghost" category.
     */
    public static function delete(int $id): bool
    {
        if (self::inUse($id)) {
            return false;
        }
        $stmt = Database::connection()->prepare('UPDATE categories SET deleted_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
