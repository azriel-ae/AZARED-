<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Database;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockMovement;

/**
 * AZARED - inventory / stock adjustment. Every change goes through
 * StockMovement::apply() so nothing ever touches products.stock directly.
 */
final class InventoryController
{
    public static function index(): void
    {
        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'type'   => (string) ($_GET['type'] ?? ''),
        ];
        $movements = StockMovement::recent(150, $filters);
        $lowStock = Product::lowStockList(50);
        // Small, id-sorted product list to populate the manual-adjustment
        // dropdown (name/SKU/current stock only - no pricing exposed here).
        $products = Product::allActive();
        require dirname(__DIR__, 2) . '/views/inventory/index.php';
    }

    /**
     * Manual stock adjustment (opname / koreksi). Requires inventory.adjust.
     * Locks the product row, applies a signed delta, and records the
     * movement with a mandatory reason so every correction is auditable.
     */
    public static function adjust(): void
    {
        $data = [
            'product_id' => (int) ($_POST['product_id'] ?? 0),
            'direction'  => (string) ($_POST['direction'] ?? 'in'),
            'quantity'   => (float) ($_POST['quantity'] ?? 0),
            'reason'     => trim((string) ($_POST['reason'] ?? '')),
        ];

        $validator = new Validator($data);
        $validator->required('reason', 'Alasan penyesuaian')->maxLength('reason', 255, 'Alasan');

        if ($data['product_id'] <= 0 || $data['quantity'] <= 0 || !in_array($data['direction'], ['in', 'out'], true)) {
            Response::jsonError('Data penyesuaian tidak valid.', 422);
        }
        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $product = Product::lockForUpdate($db, $data['product_id']);
            if (!$product) {
                throw new \RuntimeException('PRODUCT_NOT_FOUND');
            }

            $delta = $data['direction'] === 'in' ? $data['quantity'] : -1 * $data['quantity'];

            $after = StockMovement::apply(
                $db,
                (int) $product['id'],
                'adjustment',
                $delta,
                (float) $product['stock'],
                'manual_adjustment',
                null,
                AuthService::id(),
                $data['reason'],
                null,
                (bool) $product['allow_negative_stock']
            );

            AuditLog::record(AuthService::id(), 'inventory.adjust', 'product', (int) $product['id'], [
                'stock' => $product['stock'],
            ], [
                'stock' => $after,
                'reason' => $data['reason'],
            ]);

            $db->commit();
            Response::jsonSuccess(['after_stock' => $after], 'Stok berhasil disesuaikan.');
        } catch (\RuntimeException $e) {
            $db->rollBack();
            if ($e->getMessage() === 'STOCK_INSUFFICIENT') {
                Response::jsonError('Stok tidak mencukupi untuk pengurangan ini.', 422);
            }
            Response::jsonError('Produk tidak ditemukan.', 404);
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[AZARED][Inventory] adjust failed: ' . $e->getMessage());
            Response::jsonError('Gagal menyesuaikan stok.', 500);
        }
    }

    public static function history(int $productId): void
    {
        $product = Product::find($productId);
        if (!$product) {
            Response::jsonError('Produk tidak ditemukan.', 404);
        }
        Response::jsonSuccess(StockMovement::history($productId, 100));
    }
}
