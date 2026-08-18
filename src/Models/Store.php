<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class Store
{
    /**
     * Active stores only - used to populate dropdowns (POS, user store
     * access, report filters) across the app.
     */
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            "SELECT id, name, code FROM stores WHERE status = 'active' AND deleted_at IS NULL ORDER BY name ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Every non-deleted store (active + inactive) - used by the Toko/Cabang
     * management screen under Pengaturan, so an admin can also see and
     * reactivate a disabled store.
     */
    public static function allIncludingInactive(): array
    {
        $stmt = Database::connection()->query(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM user_store_access usa WHERE usa.store_id = s.id) AS user_count
             FROM stores s
             WHERE s.deleted_at IS NULL
             ORDER BY s.name ASC"
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM stores WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM stores WHERE code = :code AND deleted_at IS NULL';
        $params = ['code' => $code];
        if ($exceptId) {
            $sql .= ' AND id != :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO stores (name, code, address, phone, tax_id, status)
             VALUES (:name, :code, :address, :phone, :tax_id, :status)'
        );
        $stmt->execute([
            'name'    => $data['name'],
            'code'    => $data['code'],
            'address' => $data['address'] ?: null,
            'phone'   => $data['phone'] ?: null,
            'tax_id'  => $data['tax_id'] ?: null,
            'status'  => $data['status'] ?? 'active',
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE stores SET name = :name, code = :code, address = :address,
                phone = :phone, tax_id = :tax_id, status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'id'      => $id,
            'name'    => $data['name'],
            'code'    => $data['code'],
            'address' => $data['address'] ?: null,
            'phone'   => $data['phone'] ?: null,
            'tax_id'  => $data['tax_id'] ?: null,
            'status'  => $data['status'] ?? 'active',
        ]);
    }

    public static function toggleStatus(int $id): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE stores SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * The app assumes at least one active store exists for POS / user
     * assignment to function - used to block deactivating the last one.
     */
    public static function activeCount(): int
    {
        $stmt = Database::connection()->query("SELECT COUNT(*) FROM stores WHERE status = 'active' AND deleted_at IS NULL");
        return (int) $stmt->fetchColumn();
    }
}
