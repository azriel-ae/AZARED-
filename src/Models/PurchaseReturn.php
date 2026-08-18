<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use RuntimeException;

/**
 * AZARED - retur pembelian (returning goods back to a supplier).
 * Stock is decreased atomically and the movement is recorded.
 */
final class PurchaseReturn
{
    public static function create(int $purchaseId, array $items, string $reason, int $userId): array
    {
        if (empty($items)) {
            throw new RuntimeException('ITEMS_EMPTY');
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $purchaseStmt = $db->prepare('SELECT * FROM purchases WHERE id = :id FOR UPDATE');
            $purchaseStmt->execute(['id' => $purchaseId]);
            $purchase = $purchaseStmt->fetch();
            if (!$purchase || $purchase['status'] !== 'received') {
                throw new RuntimeException('PURCHASE_NOT_RETURNABLE');
            }

            $returnNo = InvoiceNumber::next($db, 'RTP');
            $refundTotal = 0.0;
            $lineItems = [];

            foreach ($items as $line) {
                $piStmt = $db->prepare('SELECT * FROM purchase_items WHERE id = :id AND purchase_id = :pid FOR UPDATE');
                $piStmt->execute(['id' => (int) $line['purchase_item_id'], 'pid' => $purchaseId]);
                $purchaseItem = $piStmt->fetch();
                if (!$purchaseItem) {
                    throw new RuntimeException('PURCHASE_ITEM_NOT_FOUND');
                }

                $qty = (float) $line['qty'];
                $remaining = (float) $purchaseItem['qty'] - (float) $purchaseItem['returned_qty'];
                if ($qty <= 0 || $qty > $remaining) {
                    throw new RuntimeException('RETURN_QTY_INVALID');
                }

                $product = Product::lockForUpdate($db, (int) $purchaseItem['product_id']);
                if (!$product) {
                    throw new RuntimeException('PRODUCT_NOT_FOUND');
                }

                $currentStock = (float) $product['stock'];
                if (($currentStock - $qty) < 0 && !$product['allow_negative_stock']) {
                    throw new RuntimeException('STOCK_INSUFFICIENT:' . $product['name']);
                }

                $lineRefund = round((float) $purchaseItem['cost_price'] * $qty, 2);
                $refundTotal += $lineRefund;

                $db->prepare('UPDATE purchase_items SET returned_qty = returned_qty + :qty WHERE id = :id')
                    ->execute(['qty' => $qty, 'id' => $purchaseItem['id']]);

                $lineItems[] = [
                    'purchase_item' => $purchaseItem,
                    'product'       => $product,
                    'qty'           => $qty,
                    'subtotal'      => $lineRefund,
                    'current_stock' => $currentStock,
                ];
            }

            $refundTotal = round($refundTotal, 2);

            $stmt = $db->prepare(
                'INSERT INTO purchase_returns (return_no, purchase_id, user_id, reason, refund_amount)
                 VALUES (:return_no, :purchase_id, :user_id, :reason, :refund_amount)'
            );
            $stmt->execute([
                'return_no'     => $returnNo,
                'purchase_id'   => $purchaseId,
                'user_id'       => $userId,
                'reason'        => $reason !== '' ? $reason : null,
                'refund_amount' => $refundTotal,
            ]);
            $returnId = (int) $db->lastInsertId();

            $itemStmt = $db->prepare(
                'INSERT INTO purchase_return_items (purchase_return_id, purchase_item_id, product_id, qty, cost_price, subtotal)
                 VALUES (:return_id, :purchase_item_id, :product_id, :qty, :cost_price, :subtotal)'
            );
            foreach ($lineItems as $li) {
                $itemStmt->execute([
                    'return_id'        => $returnId,
                    'purchase_item_id' => $li['purchase_item']['id'],
                    'product_id'       => $li['product']['id'],
                    'qty'              => $li['qty'],
                    'cost_price'       => $li['purchase_item']['cost_price'],
                    'subtotal'         => $li['subtotal'],
                ]);

                StockMovement::apply(
                    $db,
                    (int) $li['product']['id'],
                    'purchase_return',
                    -1 * $li['qty'],
                    $li['current_stock'],
                    'purchase_return',
                    $returnId,
                    $userId,
                    'Retur pembelian ' . $returnNo,
                    $purchase['store_id'] ? (int) $purchase['store_id'] : null,
                    (bool) $li['product']['allow_negative_stock']
                );
            }

            AuditLog::record($userId, 'purchase.return', 'purchase', $purchaseId, null, [
                'return_no'     => $returnNo,
                'refund_amount' => $refundTotal,
            ]);

            $db->commit();
            return self::find($returnId);
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT pr.*, p.purchase_no, u.full_name AS user_name FROM purchase_returns pr
             JOIN purchases p ON p.id = pr.purchase_id
             LEFT JOIN users u ON u.id = pr.user_id
             WHERE pr.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $ret = $stmt->fetch();
        if (!$ret) {
            return null;
        }
        $itemsStmt = Database::connection()->prepare(
            'SELECT pri.*, prod.name AS product_name, prod.sku FROM purchase_return_items pri
             JOIN products prod ON prod.id = pri.product_id WHERE pri.purchase_return_id = :id'
        );
        $itemsStmt->execute(['id' => $id]);
        $ret['items'] = $itemsStmt->fetchAll();
        return $ret;
    }

    public static function totalBetween(string $startDate, string $endDate): float
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(SUM(refund_amount),0) FROM purchase_returns WHERE created_at >= :start AND created_at < :end'
        );
        $stmt->execute(['start' => $startDate, 'end' => $endDate]);
        return (float) $stmt->fetchColumn();
    }

    public static function recent(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT pr.*, p.purchase_no, u.full_name AS user_name FROM purchase_returns pr
             JOIN purchases p ON p.id = pr.purchase_id
             LEFT JOIN users u ON u.id = pr.user_id
             ORDER BY pr.created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
