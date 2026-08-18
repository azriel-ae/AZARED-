<?php
use App\Helpers\Response;

$pageTitle = 'Riwayat Stock Movement';
$activeMenu = 'reports';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Laporan Inventory', 'url' => '/reports/inventory'],
    ['label' => 'Stock Movement'],
];

$typeLabel = [
    'initial'          => 'Stok Awal',
    'purchase'         => 'Pembelian',
    'sale'             => 'Penjualan',
    'sale_return'      => 'Retur Penjualan',
    'purchase_return'  => 'Retur Pembelian',
    'adjustment'       => 'Penyesuaian',
    'transfer_in'      => 'Transfer Masuk',
    'transfer_out'     => 'Transfer Keluar',
];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-filter-bar azr-no-print">
    <form method="get" action="/reports/stock-movements.php" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Cari Produk</label>
            <input class="azr-input" type="text" name="search" value="<?= Response::e($_GET['search'] ?? '') ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Tipe</label>
            <select class="azr-select" name="type">
                <option value="">Semua Tipe</option>
                <?php foreach ($typeLabel as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($_GET['type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Dari Tanggal</label>
            <input class="azr-input" type="date" name="date_from" value="<?= Response::e($_GET['date_from'] ?? '') ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Sampai Tanggal</label>
            <input class="azr-input" type="date" name="date_to" value="<?= Response::e($_GET['date_to'] ?? '') ?>">
        </div>
        <button type="submit" class="azr-btn azr-btn-primary">Filter</button>
        <a href="/reports/stock-movements.php" class="azr-btn azr-btn-outline">Reset</a>
    </form>
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Riwayat Pergerakan Stok</h2>
        <div class="azr-no-print">
            <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" data-azr-print>Cetak</button>
        </div>
    </div>
    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead><tr><th>Waktu</th><th>Produk</th><th>Tipe</th><th>Qty</th><th>Stok Sebelum</th><th>Stok Sesudah</th><th>Oleh</th></tr></thead>
            <tbody>
            <?php foreach ($movements as $m): ?>
                <tr>
                    <td style="white-space:nowrap;"><?= Response::e($m['created_at']) ?></td>
                    <td><?= Response::e($m['product_name']) ?><br><span style="color:var(--azr-gray-600);font-size:0.78rem;"><?= Response::e($m['sku']) ?></span></td>
                    <td><span class="azr-badge azr-badge-info"><?= $typeLabel[$m['type']] ?? Response::e($m['type']) ?></span></td>
                    <td style="font-weight:700;color:<?= (float) $m['quantity'] >= 0 ? 'var(--azr-green)' : 'var(--azr-red)' ?>;">
                        <?= (float) $m['quantity'] >= 0 ? '+' : '' ?><?= rtrim(rtrim(number_format((float) $m['quantity'], 3, ',', '.'), '0'), ',') ?>
                    </td>
                    <td><?= rtrim(rtrim(number_format((float) $m['before_stock'], 3, ',', '.'), '0'), ',') ?></td>
                    <td><?= rtrim(rtrim(number_format((float) $m['after_stock'], 3, ',', '.'), '0'), ',') ?></td>
                    <td><?= Response::e($m['user_name'] ?: 'Sistem') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($movements)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--azr-gray-600);">Tidak ada data pergerakan stok.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
