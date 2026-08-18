<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\User;
use RuntimeException;
use Throwable;

final class PurchaseController
{
    private const PER_PAGE = 20;

    private static function primaryStoreId(): ?int
    {
        $access = User::storeAccess((int) AuthService::id());
        foreach ($access as $a) {
            if (!empty($a['is_primary'])) {
                return (int) $a['id'];
            }
        }
        return $access[0]['id'] ?? null;
    }

    public static function index(): void
    {
        $filters = [
            'search'      => trim((string) ($_GET['search'] ?? '')),
            'status'      => (string) ($_GET['status'] ?? ''),
            'supplier_id' => (int) ($_GET['supplier_id'] ?? 0),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = Purchase::paginate($filters, $page, self::PER_PAGE);
        $purchases = $result['rows'];
        $total = $result['total'];
        $totalPages = (int) max(1, ceil($total / self::PER_PAGE));
        $suppliers = Supplier::all();

        require dirname(__DIR__, 2) . '/views/purchases/index.php';
    }

    public static function createForm(): void
    {
        $suppliers = Supplier::all();
        $products = Product::allActive();
        $taxes = Tax::all(true);
        require dirname(__DIR__, 2) . '/views/purchases/form.php';
    }

    public static function show(int $id): void
    {
        $purchase = Purchase::find($id);
        if (!$purchase) {
            Response::redirect('/purchases/index.php?error=notfound');
        }
        require dirname(__DIR__, 2) . '/views/purchases/show.php';
    }

    public static function store(): void
    {
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $purchaseDate = (string) ($_POST['purchase_date'] ?? date('Y-m-d'));
        $supplierInvoiceNo = trim((string) ($_POST['supplier_invoice_no'] ?? ''));
        $discountAmount = (float) ($_POST['discount_amount'] ?? 0);
        $note = trim((string) ($_POST['note'] ?? ''));
        $status = ($_POST['status'] ?? 'received') === 'draft' ? 'draft' : 'received';

        $productIds = $_POST['product_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $costs = $_POST['cost_price'] ?? [];
        $itemDiscounts = $_POST['item_discount'] ?? [];
        $itemTaxes = $_POST['item_tax'] ?? [];
        $itemTaxIds = $_POST['tax_id'] ?? [];

        $items = [];
        if (is_array($productIds)) {
            foreach ($productIds as $i => $pid) {
                $pid = (int) $pid;
                $qty = (float) ($qtys[$i] ?? 0);
                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }
                $items[] = [
                    'product_id'      => $pid,
                    'qty'             => $qty,
                    'cost_price'      => (float) ($costs[$i] ?? 0),
                    'discount_amount' => (float) ($itemDiscounts[$i] ?? 0),
                    'tax_percent'     => (float) ($itemTaxes[$i] ?? 0),
                    'tax_id'          => !empty($itemTaxIds[$i]) ? (int) $itemTaxIds[$i] : null,
                ];
            }
        }

        $paymentMethods = $_POST['payment_method'] ?? [];
        $paymentAmounts = $_POST['payment_amount'] ?? [];
        $payments = [];
        if (is_array($paymentMethods)) {
            foreach ($paymentMethods as $i => $method) {
                $amount = (float) ($paymentAmounts[$i] ?? 0);
                if ($amount > 0) {
                    $payments[] = ['method' => $method, 'amount' => $amount];
                }
            }
        }

        if ($supplierId <= 0 || empty($items)) {
            Response::redirect('/purchases/create.php?error=invalid');
        }

        try {
            $purchase = Purchase::create(
                $items,
                $payments,
                $supplierId,
                (int) AuthService::id(),
                self::primaryStoreId(),
                $purchaseDate,
                $supplierInvoiceNo,
                $discountAmount,
                $note,
                $status
            );
            Response::redirect('/purchases/show.php?id=' . $purchase['id'] . '&created=1');
        } catch (Throwable $e) {
            error_log('[AZARED][Purchase] create failed: ' . $e->getMessage());
            Response::redirect('/purchases/create.php?error=failed');
        }
    }

    public static function receive(int $id): void
    {
        try {
            Purchase::markReceived($id, (int) AuthService::id());
            Response::jsonSuccess([], 'Pembelian ditandai sebagai diterima dan stok telah bertambah.');
        } catch (RuntimeException $e) {
            Response::jsonError('Pembelian tidak dapat ditandai diterima (status bukan draft).', 422);
        } catch (Throwable $e) {
            error_log('[AZARED][Purchase] receive failed: ' . $e->getMessage());
            Response::jsonError('Gagal memproses penerimaan pembelian.', 500);
        }
    }

    public static function returnForm(int $id): void
    {
        $purchase = Purchase::find($id);
        if (!$purchase) {
            Response::redirect('/purchases/index.php?error=notfound');
        }
        require dirname(__DIR__, 2) . '/views/purchases/return_form.php';
    }

    public static function storeReturn(int $id): void
    {
        $items = [];
        $rawItems = $_POST['items'] ?? [];
        if (is_array($rawItems)) {
            foreach ($rawItems as $purchaseItemId => $qty) {
                $qty = (float) $qty;
                if ($qty > 0) {
                    $items[] = ['purchase_item_id' => (int) $purchaseItemId, 'qty' => $qty];
                }
            }
        }
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if (empty($items)) {
            Response::redirect("/purchases/return-form.php?id={$id}&error=empty");
        }

        try {
            PurchaseReturn::create($id, $items, $reason, (int) AuthService::id());
            Response::redirect("/purchases/show.php?id={$id}&returned=1");
        } catch (RuntimeException $e) {
            Response::redirect("/purchases/return-form.php?id={$id}&error=" . urlencode($e->getMessage()));
        } catch (Throwable $e) {
            error_log('[AZARED][PurchaseReturn] failed: ' . $e->getMessage());
            Response::redirect("/purchases/return-form.php?id={$id}&error=failed");
        }
    }
}
