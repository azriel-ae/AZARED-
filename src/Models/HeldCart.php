<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * AZARED - "Hold Cart" / "Resume Cart" on the POS screen. Lets a cashier
 * park an in-progress cart (e.g. customer stepped away) and resume it
 * later, or another cashier can pick it up from the same store.
 */
final class HeldCart
{
    public static function hold(int $userId, ?int $storeId, ?int $customerId, array $cartData, ?string $note): array
    {
        $db = Database::connection();
        do {
            $code = 'HOLD-' . strtoupper(bin2hex(random_bytes(3)));
            $exists = $db->prepare('SELECT COUNT(*) FROM held_carts WHERE code = :code');
            $exists->execute(['code' => $code]);
        } while (((int) $exists->fetchColumn()) > 0);

        $stmt = $db->prepare(
            'INSERT INTO held_carts (code, store_id, user_id, customer_id, note, cart_data)
             VALUES (:code, :store_id, :user_id, :customer_id, :note, :cart_data)'
        );
        $stmt->execute([
            'code'        => $code,
            'store_id'    => $storeId,
            'user_id'     => $userId,
            'customer_id' => $customerId,
            'note'        => $note !== '' ? $note : null,
            'cart_data'   => json_encode($cartData, JSON_UNESCAPED_UNICODE),
        ]);

        return self::find((int) $db->lastInsertId());
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM held_carts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['cart_data'] = json_decode((string) $row['cart_data'], true) ?? [];
        return $row;
    }

    public static function listForStore(?int $storeId): array
    {
        $sql = 'SELECT hc.id, hc.code, hc.note, hc.created_at, u.full_name AS user_name, c.name AS customer_name
                FROM held_carts hc
                LEFT JOIN users u ON u.id = hc.user_id
                LEFT JOIN customers c ON c.id = hc.customer_id';
        $params = [];
        if ($storeId) {
            $sql .= ' WHERE hc.store_id = :store_id';
            $params['store_id'] = $storeId;
        }
        $sql .= ' ORDER BY hc.created_at DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Resume (load + delete) a held cart. IDOR protection: only a held
     * cart belonging to the caller's own store can be resumed - without
     * this check, any authenticated cashier could resume or discard
     * another store's held cart just by guessing/incrementing the id.
     */
    public static function resume(int $id, ?int $storeId): ?array
    {
        $cart = self::find($id);
        if (!$cart || (int) $cart['store_id'] !== (int) $storeId) {
            return null;
        }
        Database::connection()->prepare('DELETE FROM held_carts WHERE id = :id')->execute(['id' => $id]);
        return $cart;
    }

    public static function discard(int $id, ?int $storeId): bool
    {
        $cart = self::find($id);
        if (!$cart || (int) $cart['store_id'] !== (int) $storeId) {
            return false;
        }
        Database::connection()->prepare('DELETE FROM held_carts WHERE id = :id')->execute(['id' => $id]);
        return true;
    }
}
