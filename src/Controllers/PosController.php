<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Models\Category;
use App\Models\Customer;
use App\Models\HeldCart;
use App\Models\Product;
use App\Models\Sale;
use RuntimeException;
use Throwable;

/**
 * AZARED - the cashier / POS screen and its supporting AJAX endpoints.
 * The screen itself only ever *displays* prices/stock; every number that
 * matters financially is recomputed and re-validated server-side inside
 * Sale::checkout() at the moment of payment.
 */
final class PosController
{
    private static function primaryStoreId(): ?int
    {
        $access = \App\Models\User::storeAccess((int) AuthService::id());
        foreach ($access as $a) {
            if (!empty($a['is_primary'])) {
                return (int) $a['id'];
            }
        }
        return $access[0]['id'] ?? null;
    }

    public static function index(): void
    {
        $categories = Category::all(true);
        $products = Product::searchForPos('', null, 60);
        $customers = Customer::all();
        $heldCarts = HeldCart::listForStore(self::primaryStoreId());
        require dirname(__DIR__, 2) . '/views/pos/index.php';
    }

    public static function searchProducts(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));
        $categoryId = (int) ($_GET['category_id'] ?? 0) ?: null;
        Response::jsonSuccess(Product::searchForPos($term, $categoryId, 60));
    }

    public static function checkout(): void
    {
        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (!is_array($payload)) {
            Response::jsonError('Payload tidak valid.', 422);
        }

        $cart = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $payments = is_array($payload['payments'] ?? null) ? $payload['payments'] : [];
        $customerId = !empty($payload['customer_id']) ? (int) $payload['customer_id'] : null;
        $discountType = in_array($payload['discount_type'] ?? 'amount', ['amount', 'percent'], true) ? $payload['discount_type'] : 'amount';
        $discountValue = (float) ($payload['discount_value'] ?? 0);
        $note = trim((string) ($payload['note'] ?? ''));

        $allowedMethods = ['cash', 'transfer', 'debit', 'credit', 'ewallet', 'qris', 'other'];
        foreach ($payments as $p) {
            if (!isset($p['method']) || !in_array($p['method'], $allowedMethods, true)) {
                Response::jsonError('Metode pembayaran tidak valid.', 422);
            }
        }

        try {
            $sale = Sale::checkout(
                $cart,
                $payments,
                (int) AuthService::id(),
                self::primaryStoreId(),
                $customerId,
                $discountType,
                $discountValue,
                $note
            );
            Response::jsonSuccess($sale, 'Transaksi berhasil disimpan.');
        } catch (RuntimeException $e) {
            Response::jsonError(self::translateError($e->getMessage()), 422);
        } catch (Throwable $e) {
            error_log('[AZARED][POS] checkout failed: ' . $e->getMessage());
            Response::jsonError('Transaksi gagal diproses. Silakan coba lagi.', 500);
        }
    }

    public static function holdCart(): void
    {
        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (!is_array($payload) || empty($payload['items'])) {
            Response::jsonError('Keranjang kosong.', 422);
        }

        $held = HeldCart::hold(
            (int) AuthService::id(),
            self::primaryStoreId(),
            !empty($payload['customer_id']) ? (int) $payload['customer_id'] : null,
            $payload,
            (string) ($payload['note'] ?? '')
        );

        Response::jsonSuccess($held, 'Keranjang berhasil ditahan (hold).');
    }

    public static function resumeCart(int $id): void
    {
        $cart = HeldCart::resume($id, self::primaryStoreId());
        if (!$cart) {
            Response::jsonError('Keranjang tidak ditemukan.', 404);
        }
        Response::jsonSuccess($cart);
    }

    public static function discardCart(int $id): void
    {
        if (!HeldCart::discard($id, self::primaryStoreId())) {
            Response::jsonError('Keranjang tidak ditemukan.', 404);
        }
        Response::jsonSuccess([], 'Keranjang dibuang.');
    }

    private static function translateError(string $code): string
    {
        if (str_starts_with($code, 'STOCK_INSUFFICIENT')) {
            $product = explode(':', $code, 2)[1] ?? '';
            return "Stok tidak mencukupi" . ($product ? " untuk produk: {$product}" : '') . '.';
        }
        if (str_starts_with($code, 'PRODUCT_UNAVAILABLE')) {
            return 'Salah satu produk di keranjang sudah tidak tersedia.';
        }
        return match ($code) {
            'CART_EMPTY'           => 'Keranjang masih kosong.',
            'INVALID_QTY'          => 'Jumlah item tidak valid.',
            'INVALID_PAYMENT_AMOUNT' => 'Jumlah pembayaran tidak valid.',
            'PAYMENT_INSUFFICIENT' => 'Total pembayaran kurang dari total belanja.',
            default                 => 'Transaksi tidak dapat diproses.',
        };
    }
}
