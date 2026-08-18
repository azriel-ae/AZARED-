<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class ExpenseCategory
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM expense_categories WHERE deleted_at IS NULL';
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        $sql .= ' ORDER BY name ASC';
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM expense_categories WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM expense_categories WHERE slug = :slug AND deleted_at IS NULL';
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
        $stmt = Database::connection()->prepare('INSERT INTO expense_categories (name, slug) VALUES (:name, :slug)');
        $stmt->execute(['name' => $name, 'slug' => $slug]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $name, string $slug, string $status): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE expense_categories SET name = :name, slug = :slug, status = :status WHERE id = :id'
        );
        $stmt->execute(['name' => $name, 'slug' => $slug, 'status' => $status, 'id' => $id]);
    }

    public static function inUse(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM expenses WHERE category_id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public static function delete(int $id): bool
    {
        if (self::inUse($id)) {
            return false;
        }
        $stmt = Database::connection()->prepare('UPDATE expense_categories SET deleted_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
