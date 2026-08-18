<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\TaxPeriod;
use App\Models\TaxTransaction;

final class TaxController
{
    private const PER_PAGE = 25;

    // =========================== DASHBOARD /tax ===========================

    private static function summaryFilters(): array
    {
        return [
            'date_from' => (string) ($_GET['date_from'] ?? date('Y-m-01')),
            'date_to'   => (string) ($_GET['date_to'] ?? date('Y-m-d')),
            'store_id'  => (int) ($_GET['store_id'] ?? 0),
            'tax_id'    => (int) ($_GET['tax_id'] ?? 0),
        ];
    }

    public static function dashboard(): void
    {
        $filters = self::summaryFilters();
        $summary = TaxTransaction::summary($filters);
        $stores = Store::all();
        $taxes = Tax::all(true);

        require dirname(__DIR__, 2) . '/views/tax/dashboard.php';
    }

    // =========================== SETTINGS /tax/settings ===========================

    public static function settings(): void
    {
        $taxes = Tax::all(false);
        $rateHistoryByTax = Tax::rateHistoryBatch(array_column($taxes, 'id'));
        require dirname(__DIR__, 2) . '/views/tax/settings.php';
    }

    public static function rateHistory(int $taxId): void
    {
        $tax = Tax::find($taxId);
        if (!$tax) {
            Response::jsonError('Jenis pajak tidak ditemukan.', 404);
        }
        Response::jsonSuccess(['tax' => $tax, 'history' => Tax::rateHistory($taxId)]);
    }

    public static function storeTax(): void
    {
        $data = [
            'name'           => trim((string) ($_POST['name'] ?? '')),
            'code'           => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'tax_type'       => (string) ($_POST['tax_type'] ?? 'ppn'),
            'tax_inclusive'  => isset($_POST['tax_inclusive']),
            'status'         => (string) ($_POST['status'] ?? 'active'),
            'rate'           => (float) ($_POST['rate'] ?? 0),
            'effective_from' => (string) ($_POST['effective_from'] ?? date('Y-m-d')),
        ];

        $validator = new Validator($data);
        $validator->required('name', 'Nama Pajak')->maxLength('name', 100, 'Nama Pajak')
            ->required('code', 'Kode Pajak')->maxLength('code', 30, 'Kode Pajak')
            ->in('tax_type', ['ppn', 'pph', 'other'], 'Jenis Pajak')
            ->in('status', ['active', 'inactive'], 'Status');

        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }
        if ($data['rate'] < 0 || $data['rate'] > 100) {
            Response::jsonError('Data tidak valid.', 422, ['rate' => 'Tarif harus antara 0-100%.']);
        }
        if (Tax::codeExists($data['code'])) {
            Response::jsonError('Kode pajak sudah digunakan.', 422, ['code' => 'Kode pajak sudah digunakan.']);
        }

        $id = Tax::create($data, (int) AuthService::id());
        AuditLog::record(AuthService::id(), 'tax.create', 'tax', $id, null, $data);

        Response::jsonSuccess(['id' => $id], 'Jenis pajak berhasil ditambahkan.');
    }

    public static function updateTax(int $id): void
    {
        $existing = Tax::find($id);
        if (!$existing) {
            Response::jsonError('Jenis pajak tidak ditemukan.', 404);
        }

        $data = [
            'name'          => trim((string) ($_POST['name'] ?? '')),
            'code'          => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'tax_type'      => (string) ($_POST['tax_type'] ?? 'ppn'),
            'tax_inclusive' => isset($_POST['tax_inclusive']),
            'status'        => (string) ($_POST['status'] ?? 'active'),
        ];

        $validator = new Validator($data);
        $validator->required('name', 'Nama Pajak')->maxLength('name', 100, 'Nama Pajak')
            ->required('code', 'Kode Pajak')->maxLength('code', 30, 'Kode Pajak')
            ->in('tax_type', ['ppn', 'pph', 'other'], 'Jenis Pajak')
            ->in('status', ['active', 'inactive'], 'Status');

        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }
        if (Tax::codeExists($data['code'], $id)) {
            Response::jsonError('Kode pajak sudah digunakan.', 422, ['code' => 'Kode pajak sudah digunakan.']);
        }

        Tax::update($id, $data);
        AuditLog::record(AuthService::id(), 'tax.update', 'tax', $id, $existing, $data);

        Response::jsonSuccess([], 'Jenis pajak berhasil diperbarui.');
    }

    public static function addRate(int $taxId): void
    {
        $tax = Tax::find($taxId);
        if (!$tax) {
            Response::jsonError('Jenis pajak tidak ditemukan.', 404);
        }

        $rate = (float) ($_POST['rate'] ?? -1);
        $effectiveFrom = (string) ($_POST['effective_from'] ?? '');

        if ($rate < 0 || $rate > 100) {
            Response::jsonError('Data tidak valid.', 422, ['rate' => 'Tarif harus antara 0-100%.']);
        }
        if ($effectiveFrom === '' || !strtotime($effectiveFrom)) {
            Response::jsonError('Data tidak valid.', 422, ['effective_from' => 'Tanggal mulai berlaku tidak valid.']);
        }

        $oldRate = Tax::currentRate($taxId);
        Tax::addRate($taxId, $rate, $effectiveFrom, (int) AuthService::id());

        AuditLog::record(
            AuthService::id(),
            'tax.rate_change',
            'tax',
            $taxId,
            ['rate' => $oldRate],
            ['rate' => $rate, 'effective_from' => $effectiveFrom]
        );

        Response::jsonSuccess([], 'Tarif pajak baru berhasil disimpan. Tarif lama tetap tersimpan untuk riwayat.');
    }

    public static function deactivateTax(int $id): void
    {
        $existing = Tax::find($id);
        if (!$existing) {
            Response::jsonError('Jenis pajak tidak ditemukan.', 404);
        }
        Tax::deactivate($id);
        AuditLog::record(AuthService::id(), 'tax.deactivate', 'tax', $id, $existing, ['status' => 'inactive']);
        Response::jsonSuccess([], 'Jenis pajak berhasil dinonaktifkan.');
    }

    public static function destroyTax(int $id): void
    {
        $existing = Tax::find($id);
        if (!$existing) {
            Response::jsonError('Jenis pajak tidak ditemukan.', 404);
        }
        if (!Tax::delete($id)) {
            Response::jsonError('Jenis pajak ini sudah pernah digunakan pada produk/transaksi dan tidak dapat dihapus. Nonaktifkan saja.', 422);
        }
        AuditLog::record(AuthService::id(), 'tax.delete', 'tax', $id, $existing, null);
        Response::jsonSuccess([], 'Jenis pajak berhasil dihapus.');
    }

    // =========================== TAX PERIODS ===========================

    public static function periods(): void
    {
        $periods = TaxPeriod::all();
        require dirname(__DIR__, 2) . '/views/tax/periods.php';
    }

    public static function storePeriod(): void
    {
        $data = [
            'name'  => trim((string) ($_POST['name'] ?? '')),
            'type'  => (string) ($_POST['period_type'] ?? 'monthly'),
            'start' => (string) ($_POST['start_date'] ?? ''),
            'end'   => (string) ($_POST['end_date'] ?? ''),
        ];

        $validator = new Validator($data);
        $validator->required('name', 'Nama Periode')->maxLength('name', 100, 'Nama Periode')
            ->in('type', ['monthly', 'yearly'], 'Tipe Periode');

        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }
        if ($data['start'] === '' || $data['end'] === '' || strtotime($data['start']) > strtotime($data['end'])) {
            Response::jsonError('Data tidak valid.', 422, ['start_date' => 'Rentang tanggal tidak valid.']);
        }

        $id = TaxPeriod::create($data['name'], $data['type'], $data['start'], $data['end']);
        AuditLog::record(AuthService::id(), 'tax_period.create', 'tax_period', $id, null, $data);

        Response::jsonSuccess([], 'Periode pajak berhasil dibuat.');
    }

    public static function closePeriod(int $id): void
    {
        $period = TaxPeriod::find($id);
        if (!$period) {
            Response::jsonError('Periode tidak ditemukan.', 404);
        }
        TaxPeriod::close($id, (int) AuthService::id());
        AuditLog::record(AuthService::id(), 'tax_period.close', 'tax_period', $id, $period, ['status' => 'closed']);
        Response::jsonSuccess([], 'Periode pajak berhasil ditutup. Data faktur pada rentang ini terkunci dari perubahan.');
    }

    public static function reopenPeriod(int $id): void
    {
        $period = TaxPeriod::find($id);
        if (!$period) {
            Response::jsonError('Periode tidak ditemukan.', 404);
        }
        TaxPeriod::reopen($id);
        AuditLog::record(AuthService::id(), 'tax_period.reopen', 'tax_period', $id, $period, ['status' => 'open']);
        Response::jsonSuccess([], 'Periode pajak dibuka kembali.');
    }

    // =========================== OUTPUT TAX /tax/output ===========================

    private static function outputFilters(): array
    {
        return [
            'search'    => trim((string) ($_GET['search'] ?? '')),
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to'   => (string) ($_GET['date_to'] ?? ''),
            'store_id'  => (int) ($_GET['store_id'] ?? 0),
            'tax_id'    => (int) ($_GET['tax_id'] ?? 0),
            'customer_id' => (int) ($_GET['customer_id'] ?? 0),
            'invoice_status' => (string) ($_GET['invoice_status'] ?? ''),
        ];
    }

    public static function output(): void
    {
        $filters = self::outputFilters();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = TaxTransaction::outputReport($filters, $page, self::PER_PAGE);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = (int) max(1, ceil($total / self::PER_PAGE));

        $allRows = TaxTransaction::outputReportAll($filters);
        $summary = [
            'taxable' => array_sum(array_column($allRows, 'taxable_amount')),
            'tax'     => array_sum(array_column($allRows, 'tax_amount')),
            'count'   => count($allRows),
        ];

        $stores = Store::all();
        $taxes = Tax::all(true);
        $customers = Customer::all();

        require dirname(__DIR__, 2) . '/views/tax/output.php';
    }

    public static function outputExportCsv(): void
    {
        $rows = TaxTransaction::outputReportAll(self::outputFilters());
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="azared-pajak-keluaran-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Invoice', 'Tanggal', 'Customer', 'Toko', 'Jenis Pajak', 'DPP', 'Tarif (%)', 'Jumlah Pajak', 'No. Faktur', 'Status Faktur']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['invoice_no'], $r['transaction_date'], $r['customer_name'] ?: 'Umum', $r['store_name'] ?: '-',
                $r['tax_name'], $r['taxable_amount'], $r['tax_rate'], $r['tax_amount'], $r['invoice_no'] ?: '-', $r['invoice_status'],
            ]);
        }
        fclose($out);
        exit;
    }

    // =========================== INPUT TAX /tax/input ===========================

    private static function inputFilters(): array
    {
        return [
            'search'    => trim((string) ($_GET['search'] ?? '')),
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to'   => (string) ($_GET['date_to'] ?? ''),
            'store_id'  => (int) ($_GET['store_id'] ?? 0),
            'tax_id'    => (int) ($_GET['tax_id'] ?? 0),
            'supplier_id' => (int) ($_GET['supplier_id'] ?? 0),
            'invoice_status' => (string) ($_GET['invoice_status'] ?? ''),
        ];
    }

    public static function input(): void
    {
        $filters = self::inputFilters();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = TaxTransaction::inputReport($filters, $page, self::PER_PAGE);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = (int) max(1, ceil($total / self::PER_PAGE));

        $allRows = TaxTransaction::inputReportAll($filters);
        $summary = [
            'taxable' => array_sum(array_column($allRows, 'taxable_amount')),
            'tax'     => array_sum(array_column($allRows, 'tax_amount')),
            'count'   => count($allRows),
        ];

        $stores = Store::all();
        $taxes = Tax::all(true);
        $suppliers = Supplier::all();

        require dirname(__DIR__, 2) . '/views/tax/input.php';
    }

    public static function inputExportCsv(): void
    {
        $rows = TaxTransaction::inputReportAll(self::inputFilters());
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="azared-pajak-masukan-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['No. Pembelian', 'Tanggal', 'Supplier', 'Toko', 'Jenis Pajak', 'DPP', 'Tarif (%)', 'Jumlah Pajak', 'No. Faktur', 'Status Faktur']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['purchase_no'], $r['transaction_date'], $r['supplier_name'] ?: '-', $r['store_name'] ?: '-',
                $r['tax_name'], $r['taxable_amount'], $r['tax_rate'], $r['tax_amount'], $r['invoice_no'] ?: '-', $r['invoice_status'],
            ]);
        }
        fclose($out);
        exit;
    }

    // =========================== NOMOR FAKTUR (manual reference) ===========================

    public static function updateInvoice(): void
    {
        $transactionType = (string) ($_POST['transaction_type'] ?? '');
        $transactionId = (int) ($_POST['transaction_id'] ?? 0);

        if (!in_array($transactionType, ['sale', 'purchase'], true) || $transactionId <= 0) {
            Response::jsonError('Data transaksi tidak valid.', 422);
        }

        $data = [
            'invoice_no'     => trim((string) ($_POST['invoice_no'] ?? '')),
            'invoice_date'   => (string) ($_POST['invoice_date'] ?? ''),
            'invoice_status' => (string) ($_POST['invoice_status'] ?? 'none'),
        ];
        $validator = new Validator($data);
        $validator->in('invoice_status', ['none', 'draft', 'issued'], 'Status Faktur');
        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        $before = TaxTransaction::forTransaction($transactionType, $transactionId);
        $ok = TaxTransaction::updateInvoice($transactionType, $transactionId, $data);

        if (!$ok) {
            Response::jsonError('Transaksi ini berada pada periode pajak yang sudah ditutup dan tidak dapat diubah.', 422);
        }

        AuditLog::record(AuthService::id(), 'tax_transaction.invoice_update', 'tax_transaction', $transactionId, $before, $data);

        Response::jsonSuccess([], 'Nomor faktur berhasil disimpan. Catatan: nomor ini bersifat referensi internal dan tidak terhubung otomatis dengan sistem DJP/e-Faktur.');
    }
}
