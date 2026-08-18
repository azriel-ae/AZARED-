<?php
use App\Helpers\Response;

$pageTitle = 'Laporan Inventory';
$activeMenu = 'reports';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Laporan Inventory']];

require __DIR__ . '/../layouts/main_top.php';

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
?>

<div class="azr-filter-bar azr-no-print">
    <form method="get" action="/reports/inventory" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Cari Produk</label>
            <input class="azr-input" type="text" name="search" value="<?= Response::e($_GET['search'] ?? '') ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Kategori</label>
            <select class="azr-select" name="category_id">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($_GET['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= Response::e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Stok Masuk/Keluar Dari</label>
            <input class="azr-input" type="date" name="date_from" value="<?= Response::e($dateFrom) ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Sampai</label>
            <input class="azr-input" type="date" name="date_to" value="<?= Response::e($dateTo) ?>">
        </div>
        <button type="submit" class="azr-btn azr-btn-primary">Filter</button>
        <a href="/reports/inventory" class="azr-btn azr-btn-outline">Reset</a>
    </form>
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Laporan Inventory <span style="color:var(--azr-gray-600);font-weight:400;">(<?= (int) $summary['count'] ?> produk)</span></h2>
        <div class="azr-no-print" style="display:flex;gap:8px;">
            <a href="/reports/stock-movements.php" class="azr-btn azr-btn-outline azr-btn-sm">Riwayat Stock Movement</a>
            <a href="/reports/inventory-export.php?<?= Response::e(http_build_query($_GET)) ?>" class="azr-btn azr-btn-outline azr-btn-sm">Export CSV</a>
            <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" data-azr-print>Cetak</button>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px;">
        <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Total Nilai Inventory</div><div style="font-weight:800;font-size:1.1rem;color:var(--azr-blue-900);">Rp <?= number_format((float) $summary['total_value'], 0, ',', '.') ?></div></div>
        <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Total Stok Masuk (Periode)</div><div style="font-weight:800;font-size:1.1rem;color:var(--azr-green);">+<?= rtrim(rtrim(number_format((float) $summary['total_in'], 3, ',', '.'), '0'), ',') ?></div></div>
        <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Total Stok Keluar (Periode)</div><div style="font-weight:800;font-size:1.1rem;color:var(--azr-red);">-<?= rtrim(rtrim(number_format((float) $summary['total_out'], 3, ',', '.'), '0'), ',') ?></div></div>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead><tr><th>Produk</th><th>SKU</th><th>Stok</th><th>Min. Stok</th><th>Nilai Inventory</th><th>Stok Masuk</th><th>Stok Keluar</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php $low = (float) $r['stock'] <= (float) $r['min_stock']; ?>
                <tr>
                    <td><?= Response::e($r['name']) ?><?php if ($low): ?> <span class="azr-badge azr-badge-warning">Menipis</span><?php endif; ?></td>
                    <td><?= Response::e($r['sku']) ?></td>
                    <td><?= rtrim(rtrim(number_format((float) $r['stock'], 3, ',', '.'), '0'), ',') ?> <?= Response::e($r['unit_symbol'] ?: '') ?></td>
                    <td><?= rtrim(rtrim(number_format((float) $r['min_stock'], 3, ',', '.'), '0'), ',') ?></td>
                    <td>Rp <?= number_format((float) $r['inventory_value'], 0, ',', '.') ?></td>
                    <td style="color:var(--azr-green);">+<?= rtrim(rtrim(number_format((float) $r['stock_in'], 3, ',', '.'), '0'), ',') ?></td>
                    <td style="color:var(--azr-red);">-<?= rtrim(rtrim(number_format((float) $r['stock_out'], 3, ',', '.'), '0'), ',') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--azr-gray-600);">Tidak ada data produk.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
