<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class Unit
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM units';
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        }
        $sql .= ' ORDER BY name ASC';
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM units WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function symbolExists(string $symbol, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM units WHERE symbol = :symbol';
        $params = ['symbol' => $symbol];
        if ($exceptId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public static function create(string $name, string $symbol): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO units (name, symbol) VALUES (:name, :symbol)'
        );
        $stmt->execute(['name' => $name, 'symbol' => $symbol]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $name, string $symbol, string $status): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE units SET name = :name, symbol = :symbol, status = :status WHERE id = :id'
        );
        $stmt->execute(['name' => $name, 'symbol' => $symbol, 'status' => $status, 'id' => $id]);
    }

    public static function inUse(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM products WHERE unit_id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public static function delete(int $id): bool
    {
        if (self::inUse($id)) {
            return false;
        }
        $stmt = Database::connection()->prepare('DELETE FROM units WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
