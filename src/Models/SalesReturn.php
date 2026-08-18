<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use RuntimeException;

/**
 * AZARED - retur penjualan (customer returns an item from a completed sale).
 * Restocking (if requested) and refund bookkeeping happen atomically.
 */
final class SalesReturn
{
    /**
     * @param array $items [['sale_item_id'=>int,'qty'=>float], ...]
     */
    public static function create(int $saleId, array $items, string $reason, bool $restock, int $userId): array
    {
        if (empty($items)) {
            throw new RuntimeException('ITEMS_EMPTY');
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $saleStmt = $db->prepare('SELECT * FROM sales WHERE id = :id FOR UPDATE');
            $saleStmt->execute(['id' => $saleId]);
            $sale = $saleStmt->fetch();
            if (!$sale || !in_array($sale['status'], ['completed', 'partially_returned'], true)) {
                throw new RuntimeException('SALE_NOT_RETURNABLE');
            }

            $returnNo = InvoiceNumber::next($db, 'RTS');
            $refundTotal = 0.0;
            $lineItems = [];

            foreach ($items as $line) {
                $saleItemStmt = $db->prepare('SELECT * FROM sale_items WHERE id = :id AND sale_id = :sale_id FOR UPDATE');
                $saleItemStmt->execute(['id' => (int) $line['sale_item_id'], 'sale_id' => $saleId]);
                $saleItem = $saleItemStmt->fetch();
                if (!$saleItem) {
                    throw new RuntimeException('SALE_ITEM_NOT_FOUND');
                }

                $qty = (float) $line['qty'];
                $remaining = (float) $saleItem['qty'] - (float) $saleItem['returned_qty'];
                if ($qty <= 0 || $qty > $remaining) {
                    throw new RuntimeException('RETURN_QTY_INVALID:' . $saleItem['product_name']);
                }

                $unitNet = ((float) $saleItem['subtotal']) / max(0.0001, (float) $saleItem['qty']);
                $lineRefund = round($unitNet * $qty, 2);
                $refundTotal += $lineRefund;

                $db->prepare('UPDATE sale_items SET returned_qty = returned_qty + :qty WHERE id = :id')
                    ->execute(['qty' => $qty, 'id' => $saleItem['id']]);

                $lineItems[] = [
                    'sale_item'  => $saleItem,
                    'qty'        => $qty,
                    'unit_price' => (float) $saleItem['unit_price'],
                    'subtotal'   => $lineRefund,
                ];
            }

            $refundTotal = round($refundTotal, 2);

            $returnStmt = $db->prepare(
                'INSERT INTO sales_returns (return_no, sale_id, user_id, reason, refund_amount, restock)
                 VALUES (:return_no, :sale_id, :user_id, :reason, :refund_amount, :restock)'
            );
            $returnStmt->execute([
                'return_no'     => $returnNo,
                'sale_id'       => $saleId,
                'user_id'       => $userId,
                'reason'        => $reason !== '' ? $reason : null,
                'refund_amount' => $refundTotal,
                'restock'       => $restock ? 1 : 0,
            ]);
            $returnId = (int) $db->lastInsertId();

            $itemStmt = $db->prepare(
                'INSERT INTO sales_return_items (sales_return_id, sale_item_id, product_id, qty, unit_price, subtotal)
                 VALUES (:return_id, :sale_item_id, :product_id, :qty, :unit_price, :subtotal)'
            );
            foreach ($lineItems as $li) {
                $itemStmt->execute([
                    'return_id'    => $returnId,
                    'sale_item_id' => $li['sale_item']['id'],
                    'product_id'   => $li['sale_item']['product_id'],
                    'qty'          => $li['qty'],
                    'unit_price'   => $li['unit_price'],
                    'subtotal'     => $li['subtotal'],
                ]);

                if ($restock) {
                    $product = Product::lockForUpdate($db, (int) $li['sale_item']['product_id']);
                    if ($product) {
                        StockMovement::apply(
                            $db,
                            (int) $product['id'],
                            'sale_return',
                            $li['qty'],
                            (float) $product['stock'],
                            'sales_return',
                            $returnId,
                            $userId,
                            'Retur penjualan ' . $returnNo,
                            $sale['store_id'] ? (int) $sale['store_id'] : null,
                            true
                        );
                    }
                }
            }

            $fullyReturned = true;
            $checkStmt = $db->prepare('SELECT qty, returned_qty FROM sale_items WHERE sale_id = :id');
            $checkStmt->execute(['id' => $saleId]);
            foreach ($checkStmt->fetchAll() as $row) {
                if ((float) $row['returned_qty'] < (float) $row['qty']) {
                    $fullyReturned = false;
                    break;
                }
            }
            $db->prepare('UPDATE sales SET status = :status WHERE id = :id')->execute([
                'status' => $fullyReturned ? 'returned' : 'partially_returned',
                'id'     => $saleId,
            ]);

            AuditLog::record($userId, 'sale.return', 'sale', $saleId, null, [
                'return_no'     => $returnNo,
                'refund_amount' => $refundTotal,
                'restock'       => $restock,
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
            'SELECT sr.*, s.invoice_no, u.full_name AS user_name FROM sales_returns sr
             JOIN sales s ON s.id = sr.sale_id
             LEFT JOIN users u ON u.id = sr.user_id
             WHERE sr.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $ret = $stmt->fetch();
        if (!$ret) {
            return null;
        }
        $itemsStmt = Database::connection()->prepare(
            'SELECT sri.*, p.name AS product_name, p.sku FROM sales_return_items sri
             JOIN products p ON p.id = sri.product_id WHERE sri.sales_return_id = :id'
        );
        $itemsStmt->execute(['id' => $id]);
        $ret['items'] = $itemsStmt->fetchAll();
        return $ret;
    }

    public static function recent(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT sr.*, s.invoice_no, u.full_name AS user_name FROM sales_returns sr
             JOIN sales s ON s.id = sr.sale_id
             LEFT JOIN users u ON u.id = sr.user_id
             ORDER BY sr.created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
