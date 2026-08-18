<?php
use App\Helpers\Response;

$pageTitle = 'Laporan HPP';
$activeMenu = 'reports';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Laporan', 'url' => '/reports'],
    ['label' => 'HPP (Harga Pokok Penjualan)'],
];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-filter-bar azr-no-print">
    <form method="get" action="/reports/hpp" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Dari Tanggal</label>
            <input class="azr-input" type="date" name="date_from" value="<?= Response::e($filters['date_from']) ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Sampai Tanggal</label>
            <input class="azr-input" type="date" name="date_to" value="<?= Response::e($filters['date_to']) ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Toko</label>
            <select class="azr-select" name="store_id">
                <option value="">Semua Toko</option>
                <?php foreach ($stores as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) $filters['store_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= Response::e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="azr-btn azr-btn-primary">Filter</button>
        <a href="/reports/hpp" class="azr-btn azr-btn-outline">Reset</a>
    </form>
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Laporan HPP (Harga Pokok Penjualan) <span style="color:var(--azr-gray-600);font-weight:400;">(<?= (int) $summary['product_count'] ?> produk)</span></h2>
        <div class="azr-no-print" style="display:flex;gap:8px;">
            <a href="/reports/hpp-export.php?<?= Response::e(http_build_query($_GET)) ?>" class="azr-btn azr-btn-outline azr-btn-sm">Export CSV</a>
            <a href="/finance/profit-loss" class="azr-btn azr-btn-outline azr-btn-sm">Lihat di Laba Rugi</a>
            <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" data-azr-print>Cetak</button>
        </div>
    </div>
    <p class="azr-help-text" style="margin-bottom:14px;">
        HPP dihitung dari <code>cost_price</code> yang dibekukan (snapshot) pada setiap baris penjualan saat
        transaksi terjadi, dikurangi kuantitas yang sudah diretur - sama persis dengan angka HPP pada Laporan
        Laba Rugi, hanya dirinci per produk di sini.
    </p>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px;">
        <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Total HPP</div><div style="font-weight:800;font-size:1.1rem;color:var(--azr-red);">Rp <?= number_format($summary['total_hpp'], 0, ',', '.') ?></div></div>
        <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Total Penjualan (Bersih)</div><div style="font-weight:800;font-size:1.1rem;color:var(--azr-blue-900);">Rp <?= number_format($summary['total_revenue'], 0, ',', '.') ?></div></div>
        <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Laba Kotor</div><div style="font-weight:800;font-size:1.1rem;color:var(--azr-green);">Rp <?= number_format($summary['gross_profit'], 0, ',', '.') ?></div></div>
        <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Margin Kotor</div><div style="font-weight:800;font-size:1.1rem;color:var(--azr-blue-900);"><?= $summary['gross_margin'] ?>%</div></div>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead>
            <tr>
                <th>Produk</th><th>SKU</th><th>Kategori</th><th>Qty Terjual (Bersih)</th>
                <th>Rata-rata HPP</th><th>Total HPP</th><th>Total Penjualan</th><th>Laba Kotor</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php $gp = (float) $r['total_revenue'] - (float) $r['total_hpp']; ?>
                <tr>
                    <td><?= Response::e($r['product_name']) ?></td>
                    <td><?= Response::e($r['sku']) ?></td>
                    <td><?= Response::e($r['category_name']) ?></td>
                    <td><?= rtrim(rtrim(number_format((float) $r['qty_net'], 3, ',', '.'), '0'), ',') ?></td>
                    <td>Rp <?= number_format((float) $r['avg_cost'], 0, ',', '.') ?></td>
                    <td style="color:var(--azr-red);">Rp <?= number_format((float) $r['total_hpp'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format((float) $r['total_revenue'], 0, ',', '.') ?></td>
                    <td style="color:<?= $gp >= 0 ? 'var(--azr-green)' : 'var(--azr-red)' ?>;">Rp <?= number_format($gp, 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--azr-gray-600);">Tidak ada penjualan pada periode ini.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
