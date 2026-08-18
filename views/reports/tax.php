<?php
use App\Helpers\Response;

$pageTitle = 'Laporan Pajak';
$activeMenu = 'reports';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Laporan Pajak']];
$statusLabel = ['none' => 'Belum Ada', 'draft' => 'Draft', 'issued' => 'Terbit'];
$statusBadge = ['none' => 'azr-badge-neutral', 'draft' => 'azr-badge-warning', 'issued' => 'azr-badge-active'];

require __DIR__ . '/../layouts/main_top.php';

$selisih = $summary['output']['tax'] - $summary['input']['tax'];
?>

<div class="azr-alert azr-alert-info azr-no-print" style="margin-bottom:18px;">
    Laporan ini adalah rekapitulasi internal AZARED berdasarkan transaksi tersimpan di sistem - bukan laporan resmi ke otoritas pajak.
</div>

<div class="azr-filter-bar azr-no-print">
    <form method="get" action="/reports/tax" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Dari Tanggal</label>
            <input class="azr-input" type="date" name="date_from" value="<?= Response::e($_GET['date_from'] ?? date('Y-m-01')) ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Sampai Tanggal</label>
            <input class="azr-input" type="date" name="date_to" value="<?= Response::e($_GET['date_to'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Toko</label>
            <select class="azr-select" name="store_id">
                <option value="">Semua Toko</option>
                <?php foreach ($stores as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($_GET['store_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= Response::e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Jenis Pajak</label>
            <select class="azr-select" name="tax_id">
                <option value="">Semua</option>
                <?php foreach ($taxes as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= (int) ($_GET['tax_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= Response::e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Status Faktur</label>
            <select class="azr-select" name="invoice_status">
                <option value="">Semua</option>
                <?php foreach ($statusLabel as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($_GET['invoice_status'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="azr-btn azr-btn-primary">Filter</button>
        <div class="azr-no-print" style="display:flex;gap:8px;">
            <a href="/reports/tax-export.php?<?= Response::e(http_build_query($_GET)) ?>" class="azr-btn azr-btn-outline">Export CSV</a>
            <button type="button" class="azr-btn azr-btn-outline" data-azr-print>Cetak</button>
        </div>
    </form>
</div>

<div class="azr-stats-grid">
    <div class="azr-stat-card">
        <div class="azr-stat-label">DPP Penjualan</div>
        <div class="azr-stat-value">Rp <?= number_format($summary['output']['taxable'], 0, ',', '.') ?></div>
    </div>
    <div class="azr-stat-card">
        <div class="azr-stat-label">Pajak Keluaran</div>
        <div class="azr-stat-value">Rp <?= number_format($summary['output']['tax'], 0, ',', '.') ?></div>
    </div>
    <div class="azr-stat-card">
        <div class="azr-stat-label">DPP Pembelian</div>
        <div class="azr-stat-value">Rp <?= number_format($summary['input']['taxable'], 0, ',', '.') ?></div>
    </div>
    <div class="azr-stat-card">
        <div class="azr-stat-label">Pajak Masukan</div>
        <div class="azr-stat-value">Rp <?= number_format($summary['input']['tax'], 0, ',', '.') ?></div>
    </div>
    <div class="azr-stat-card <?= $selisih >= 0 ? 'azr-stat-warn' : 'azr-stat-good' ?>">
        <div class="azr-stat-label">Estimasi Selisih</div>
        <div class="azr-stat-value">Rp <?= number_format($selisih, 0, ',', '.') ?></div>
    </div>
</div>

<div class="azr-card">
    <div class="azr-card-header"><h2 class="azr-card-title">Detail Pajak Keluaran (Penjualan)</h2></div>
    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead><tr><th>Invoice</th><th>Tanggal</th><th>Customer</th><th>Toko</th><th>Jenis</th><th>DPP</th><th>Tarif</th><th>Pajak</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($outputRows as $r): ?>
                <tr>
                    <td><?= Response::e($r['invoice_no']) ?></td>
                    <td><?= Response::e($r['transaction_date']) ?></td>
                    <td><?= Response::e($r['customer_name'] ?: 'Umum') ?></td>
                    <td><?= Response::e($r['store_name'] ?: '-') ?></td>
                    <td><?= Response::e($r['tax_name']) ?></td>
                    <td>Rp <?= number_format((float) $r['taxable_amount'], 0, ',', '.') ?></td>
                    <td><?= number_format((float) $r['tax_rate'], 2) ?>%</td>
                    <td style="font-weight:700;">Rp <?= number_format((float) $r['tax_amount'], 0, ',', '.') ?></td>
                    <td><span class="azr-badge <?= $statusBadge[$r['invoice_status']] ?? '' ?>"><?= $statusLabel[$r['invoice_status']] ?? Response::e($r['invoice_status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($outputRows)): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--azr-gray-600);">Tidak ada data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="azr-card">
    <div class="azr-card-header"><h2 class="azr-card-title">Detail Pajak Masukan (Pembelian)</h2></div>
    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead><tr><th>No. Pembelian</th><th>Tanggal</th><th>Supplier</th><th>Toko</th><th>Jenis</th><th>DPP</th><th>Tarif</th><th>Pajak</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($inputRows as $r): ?>
                <tr>
                    <td><?= Response::e($r['purchase_no']) ?></td>
                    <td><?= Response::e($r['transaction_date']) ?></td>
                    <td><?= Response::e($r['supplier_name'] ?: '-') ?></td>
                    <td><?= Response::e($r['store_name'] ?: '-') ?></td>
                    <td><?= Response::e($r['tax_name']) ?></td>
                    <td>Rp <?= number_format((float) $r['taxable_amount'], 0, ',', '.') ?></td>
                    <td><?= number_format((float) $r['tax_rate'], 2) ?>%</td>
                    <td style="font-weight:700;">Rp <?= number_format((float) $r['tax_amount'], 0, ',', '.') ?></td>
                    <td><span class="azr-badge <?= $statusBadge[$r['invoice_status']] ?? '' ?>"><?= $statusLabel[$r['invoice_status']] ?? Response::e($r['invoice_status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($inputRows)): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--azr-gray-600);">Tidak ada data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
