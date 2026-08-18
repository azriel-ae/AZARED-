<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Finance;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\TaxTransaction;
use App\Models\User;

final class ReportController
{
    private const PER_PAGE = 25;

    // =========================== SALES ===========================

    private static function salesFilters(): array
    {
        return [
            'search'         => trim((string) ($_GET['search'] ?? '')),
            'status'         => (string) ($_GET['status'] ?? ''),
            'date_from'      => (string) ($_GET['date_from'] ?? ''),
            'date_to'        => (string) ($_GET['date_to'] ?? ''),
            'store_id'       => (int) ($_GET['store_id'] ?? 0),
            'user_id'        => (int) ($_GET['user_id'] ?? 0),
            'customer_id'    => (int) ($_GET['customer_id'] ?? 0),
            'payment_method' => (string) ($_GET['payment_method'] ?? ''),
        ];
    }

    public static function sales(): void
    {
        $filters = self::salesFilters();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = Sale::report($filters, $page, self::PER_PAGE);
        $sales = $result['rows'];
        $total = $result['total'];
        $totalPages = (int) max(1, ceil($total / self::PER_PAGE));

        $allRows = Sale::reportAll($filters);
        $summary = [
            'subtotal' => array_sum(array_column($allRows, 'subtotal')),
            'discount' => array_sum(array_column($allRows, 'discount_amount')),
            'tax'      => array_sum(array_column($allRows, 'tax_amount')),
            'total'    => array_sum(array_column($allRows, 'grand_total')),
            'count'    => count($allRows),
        ];

        $stores = Store::all();
        $cashiers = User::simpleList();
        $customers = Customer::all();

        require dirname(__DIR__, 2) . '/views/reports/sales.php';
    }

    public static function salesExportCsv(): void
    {
        $rows = Sale::reportAll(self::salesFilters());
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="azared-laporan-penjualan-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Invoice', 'Tanggal', 'Customer', 'Kasir', 'Subtotal', 'Diskon', 'Pajak', 'Total', 'Metode Pembayaran', 'Status']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['invoice_no'], $r['created_at'], $r['customer_name'] ?: 'Umum', $r['cashier_name'],
                $r['subtotal'], $r['discount_amount'], $r['tax_amount'], $r['grand_total'],
                $r['payment_methods'], $r['status'],
            ]);
        }
        fclose($out);
        exit;
    }

    // =========================== PURCHASES ===========================

    private static function purchaseFilters(): array
    {
        return [
            'search'      => trim((string) ($_GET['search'] ?? '')),
            'status'      => (string) ($_GET['status'] ?? ''),
            'supplier_id' => (int) ($_GET['supplier_id'] ?? 0),
            'date_from'   => (string) ($_GET['date_from'] ?? ''),
            'date_to'     => (string) ($_GET['date_to'] ?? ''),
        ];
    }

    public static function purchases(): void
    {
        $filters = self::purchaseFilters();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = Purchase::paginate($filters, $page, self::PER_PAGE);
        $purchases = $result['rows'];
        $total = $result['total'];
        $totalPages = (int) max(1, ceil($total / self::PER_PAGE));

        $allRows = Purchase::reportAll($filters);
        $summary = [
            'subtotal' => array_sum(array_column($allRows, 'subtotal')),
            'discount' => array_sum(array_column($allRows, 'discount_amount')),
            'tax'      => array_sum(array_column($allRows, 'tax_amount')),
            'total'    => array_sum(array_column($allRows, 'total')),
            'count'    => count($allRows),
        ];

        $suppliers = Supplier::all();

        require dirname(__DIR__, 2) . '/views/reports/purchases.php';
    }

    public static function purchasesExportCsv(): void
    {
        $rows = Purchase::reportAll(self::purchaseFilters());
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="azared-laporan-pembelian-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['No. Pembelian', 'Tanggal', 'Supplier', 'Subtotal', 'Diskon', 'Pajak', 'Total', 'Status']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['purchase_no'], $r['purchase_date'], $r['supplier_name'],
                $r['subtotal'], $r['discount_amount'], $r['tax_amount'], $r['total'], $r['status'],
            ]);
        }
        fclose($out);
        exit;
    }

    // =========================== INVENTORY ===========================

    private static function inventoryFilters(): array
    {
        return [
            'search'      => trim((string) ($_GET['search'] ?? '')),
            'category_id' => (int) ($_GET['category_id'] ?? 0),
        ];
    }

    public static function inventory(): void
    {
        $dateFrom = (string) ($_GET['date_from'] ?? date('Y-m-01'));
        $dateTo = (string) ($_GET['date_to'] ?? date('Y-m-d'));
        $start = $dateFrom . ' 00:00:00';
        $end = (new \DateTimeImmutable($dateTo))->modify('+1 day')->format('Y-m-d H:i:s');

        $rows = Product::inventoryReport($start, $end, self::inventoryFilters());
        $summary = [
            'total_value' => array_sum(array_column($rows, 'inventory_value')),
            'total_in'    => array_sum(array_column($rows, 'stock_in')),
            'total_out'   => array_sum(array_column($rows, 'stock_out')),
            'count'       => count($rows),
        ];
        $categories = Category::all(true);

        require dirname(__DIR__, 2) . '/views/reports/inventory.php';
    }

    public static function inventoryExportCsv(): void
    {
        $dateFrom = (string) ($_GET['date_from'] ?? date('Y-m-01'));
        $dateTo = (string) ($_GET['date_to'] ?? date('Y-m-d'));
        $start = $dateFrom . ' 00:00:00';
        $end = (new \DateTimeImmutable($dateTo))->modify('+1 day')->format('Y-m-d H:i:s');
        $rows = Product::inventoryReport($start, $end, self::inventoryFilters());

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="azared-laporan-inventory-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Produk', 'SKU', 'Stok', 'Min. Stok', 'Nilai Inventory', 'Stok Masuk', 'Stok Keluar']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['name'], $r['sku'], $r['stock'], $r['min_stock'], $r['inventory_value'], $r['stock_in'], $r['stock_out']]);
        }
        fclose($out);
        exit;
    }

    public static function stockMovements(): void
    {
        $filters = [
            'search'    => trim((string) ($_GET['search'] ?? '')),
            'type'      => (string) ($_GET['type'] ?? ''),
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to'   => (string) ($_GET['date_to'] ?? ''),
        ];
        $movements = StockMovement::recent(300, $filters);
        require dirname(__DIR__, 2) . '/views/reports/stock_movements.php';
    }

    // =========================== TAX ===========================

    private static function taxFilters(): array
    {
        return [
            'date_from'      => (string) ($_GET['date_from'] ?? date('Y-m-01')),
            'date_to'        => (string) ($_GET['date_to'] ?? date('Y-m-d')),
            'store_id'       => (int) ($_GET['store_id'] ?? 0),
            'tax_id'         => (int) ($_GET['tax_id'] ?? 0),
            'invoice_status' => (string) ($_GET['invoice_status'] ?? ''),
        ];
    }

    public static function tax(): void
    {
        $filters = self::taxFilters();
        $summary = TaxTransaction::summary($filters);
        $outputRows = TaxTransaction::outputReportAll($filters);
        $inputRows = TaxTransaction::inputReportAll($filters);
        $stores = Store::all();
        $taxes = Tax::all(true);

        require dirname(__DIR__, 2) . '/views/reports/tax.php';
    }

    public static function taxExportCsv(): void
    {
        $filters = self::taxFilters();
        $outputRows = TaxTransaction::outputReportAll($filters);
        $inputRows = TaxTransaction::inputReportAll($filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="azared-laporan-pajak-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');

        fputcsv($out, ['=== PAJAK KELUARAN (PENJUALAN) ===']);
        fputcsv($out, ['Invoice', 'Tanggal', 'Customer', 'Toko', 'Jenis Pajak', 'DPP', 'Tarif (%)', 'Jumlah Pajak', 'No. Faktur', 'Status Faktur']);
        foreach ($outputRows as $r) {
            fputcsv($out, [
                $r['invoice_no'], $r['transaction_date'], $r['customer_name'] ?: 'Umum', $r['store_name'] ?: '-',
                $r['tax_name'], $r['taxable_amount'], $r['tax_rate'], $r['tax_amount'], $r['invoice_no'] ?: '-', $r['invoice_status'],
            ]);
        }

        fputcsv($out, []);
        fputcsv($out, ['=== PAJAK MASUKAN (PEMBELIAN) ===']);
        fputcsv($out, ['No. Pembelian', 'Tanggal', 'Supplier', 'Toko', 'Jenis Pajak', 'DPP', 'Tarif (%)', 'Jumlah Pajak', 'No. Faktur', 'Status Faktur']);
        foreach ($inputRows as $r) {
            fputcsv($out, [
                $r['purchase_no'], $r['transaction_date'], $r['supplier_name'] ?: '-', $r['store_name'] ?: '-',
                $r['tax_name'], $r['taxable_amount'], $r['tax_rate'], $r['tax_amount'], $r['invoice_no'] ?: '-', $r['invoice_status'],
            ]);
        }

        fclose($out);
        exit;
    }

    // =========================== HPP (COGS) ===========================

    private static function hppFilters(): array
    {
        return [
            'date_from' => (string) ($_GET['date_from'] ?? date('Y-m-01')),
            'date_to'   => (string) ($_GET['date_to'] ?? date('Y-m-d')),
            'store_id'  => (int) ($_GET['store_id'] ?? 0),
        ];
    }

    public static function hpp(): void
    {
        $filters = self::hppFilters();
        // hppByProductBetween()'s date range is a half-open [start, end)
        // window, same convention as every other report here - "sampai
        // tanggal" is inclusive of that whole calendar day.
        $endExclusive = date('Y-m-d', strtotime($filters['date_to'] . ' +1 day'));
        $rows = Sale::hppByProductBetween($filters['date_from'], $endExclusive, $filters['store_id'] ?: null);

        $totalHpp = 0.0;
        $totalRevenue = 0.0;
        foreach ($rows as $r) {
            $totalHpp += (float) $r['total_hpp'];
            $totalRevenue += (float) $r['total_revenue'];
        }
        $summary = [
            'total_hpp'      => $totalHpp,
            'total_revenue'  => $totalRevenue,
            'gross_profit'   => $totalRevenue - $totalHpp,
            'gross_margin'   => $totalRevenue > 0 ? round((($totalRevenue - $totalHpp) / $totalRevenue) * 100, 2) : 0.0,
            'product_count'  => count($rows),
        ];
        $stores = Store::all();

        require dirname(__DIR__, 2) . '/views/reports/hpp.php';
    }

    public static function hppExportCsv(): void
    {
        $filters = self::hppFilters();
        $endExclusive = date('Y-m-d', strtotime($filters['date_to'] . ' +1 day'));
        $rows = Sale::hppByProductBetween($filters['date_from'], $endExclusive, $filters['store_id'] ?: null);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="azared-laporan-hpp-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['SKU', 'Produk', 'Kategori', 'Qty Terjual (Bersih)', 'Rata-rata HPP', 'Total HPP', 'Total Penjualan', 'Laba Kotor']);
        foreach ($rows as $r) {
            $gp = (float) $r['total_revenue'] - (float) $r['total_hpp'];
            fputcsv($out, [
                $r['sku'], $r['product_name'], $r['category_name'], $r['qty_net'],
                round((float) $r['avg_cost'], 2), $r['total_hpp'], $r['total_revenue'], round($gp, 2),
            ]);
        }
        fclose($out);
        exit;
    }

    // =========================== REPORTS HUB ===========================

    /**
     * /reports - a simple hub linking to every report type, so "Laporan"
     * has a landing page of its own instead of only being reachable via
     * sidebar sub-links.
     */
    public static function index(): void
    {
        require dirname(__DIR__, 2) . '/views/reports/index.php';
    }
}
