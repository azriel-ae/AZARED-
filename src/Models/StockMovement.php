<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;
use RuntimeException;

/**
 * AZARED - the single gateway through which product stock is ever
 * changed. No other code in the app may run `UPDATE products SET stock`
 * directly - every change MUST go through here so we always keep a full,
 * trustworthy history in stock_movements (before/after, who, why, when).
 *
 * MUST be called from inside a transaction the caller already opened,
 * and the product row MUST already be locked with SELECT ... FOR UPDATE
 * by the caller (see Product::lockForUpdate()) to prevent two concurrent
 * requests from racing on the same product's stock.
 */
final class StockMovement
{
    /**
     * @param float $quantity Signed delta. Positive = stock in, negative = stock out.
     */
    public static function apply(
        PDO $db,
        int $productId,
        string $type,
        float $quantity,
        float $currentStock,
        ?string $referenceType,
        ?int $referenceId,
        ?int $userId,
        ?string $note = null,
        ?int $storeId = null,
        bool $allowNegative = false
    ): float {
        $after = round($currentStock + $quantity, 3);

        if ($after < 0 && !$allowNegative) {
            throw new RuntimeException('STOCK_INSUFFICIENT');
        }

        $update = $db->prepare('UPDATE products SET stock = :stock WHERE id = :id');
        $update->execute(['stock' => $after, 'id' => $productId]);

        $insert = $db->prepare(
            'INSERT INTO stock_movements
                (product_id, store_id, type, quantity, before_stock, after_stock, reference_type, reference_id, note, user_id)
             VALUES
                (:product_id, :store_id, :type, :quantity, :before_stock, :after_stock, :reference_type, :reference_id, :note, :user_id)'
        );
        $insert->execute([
            'product_id'     => $productId,
            'store_id'       => $storeId,
            'type'           => $type,
            'quantity'       => $quantity,
            'before_stock'   => $currentStock,
            'after_stock'    => $after,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'note'           => $note,
            'user_id'        => $userId,
        ]);

        return $after;
    }

    public static function history(int $productId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT sm.*, u.full_name AS user_name
             FROM stock_movements sm
             LEFT JOIN users u ON u.id = sm.user_id
             WHERE sm.product_id = :product_id
             ORDER BY sm.created_at DESC, sm.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function recent(int $limit = 100, array $filters = []): array
    {
        $sql = 'SELECT sm.*, p.name AS product_name, p.sku, u.full_name AS user_name
                FROM stock_movements sm
                JOIN products p ON p.id = sm.product_id
                LEFT JOIN users u ON u.id = sm.user_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['type'])) {
            $sql .= ' AND sm.type = :type';
            $params['type'] = $filters['type'];
        }
        if (!empty($filters['product_id'])) {
            $sql .= ' AND sm.product_id = :product_id';
            $params['product_id'] = (int) $filters['product_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (p.name LIKE :search OR p.sku LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $sql .= ' AND sm.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND sm.created_at < :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= ' ORDER BY sm.created_at DESC, sm.id DESC LIMIT :limit';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
