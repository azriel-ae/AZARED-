<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\Sale;
use App\Models\SalesReturn;
use RuntimeException;
use Throwable;

final class SaleController
{
    private const PER_PAGE = 20;

    public static function index(): void
    {
        $filters = [
            'search'    => trim((string) ($_GET['search'] ?? '')),
            'status'    => (string) ($_GET['status'] ?? ''),
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to'   => (string) ($_GET['date_to'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = Sale::paginate($filters, $page, self::PER_PAGE);
        $sales = $result['rows'];
        $total = $result['total'];
        $totalPages = (int) max(1, ceil($total / self::PER_PAGE));

        require dirname(__DIR__, 2) . '/views/sales/index.php';
    }

    public static function show(int $id): void
    {
        $sale = Sale::find($id);
        if (!$sale) {
            Response::redirect('/sales/index.php?error=notfound');
        }
        $printSize = (string) ($_GET['size'] ?? '80mm');
        $companyLegalName = \App\Models\AppSetting::get('company_legal_name', '');
        $receiptFooterNote = \App\Models\AppSetting::get('receipt_footer_note', '');
        require dirname(__DIR__, 2) . '/views/sales/show.php';
    }

    public static function returnForm(int $id): void
    {
        $sale = Sale::find($id);
        if (!$sale) {
            Response::redirect('/sales/index.php?error=notfound');
        }
        require dirname(__DIR__, 2) . '/views/sales/return_form.php';
    }

    public static function storeReturn(int $id): void
    {
        $items = [];
        $rawItems = $_POST['items'] ?? [];
        if (is_array($rawItems)) {
            foreach ($rawItems as $saleItemId => $qty) {
                $qty = (float) $qty;
                if ($qty > 0) {
                    $items[] = ['sale_item_id' => (int) $saleItemId, 'qty' => $qty];
                }
            }
        }

        $reason = trim((string) ($_POST['reason'] ?? ''));
        $restock = isset($_POST['restock']);

        $validator = new Validator(['reason' => $reason]);
        $validator->required('reason', 'Alasan retur')->maxLength('reason', 255, 'Alasan');

        if (empty($items)) {
            Response::redirect("/sales/return-form.php?id={$id}&error=empty");
        }
        if ($validator->fails()) {
            Response::redirect("/sales/return-form.php?id={$id}&error=reason");
        }

        try {
            $return = SalesReturn::create($id, $items, $reason, $restock, (int) AuthService::id());
            Response::redirect("/sales/show.php?id={$id}&returned=1");
        } catch (RuntimeException $e) {
            Response::redirect("/sales/return-form.php?id={$id}&error=" . urlencode($e->getMessage()));
        } catch (Throwable $e) {
            error_log('[AZARED][SalesReturn] failed: ' . $e->getMessage());
            Response::redirect("/sales/return-form.php?id={$id}&error=failed");
        }
    }
}
