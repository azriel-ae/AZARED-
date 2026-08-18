<?php
use App\Auth\AuthService;
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Stok / Inventory';
$activeMenu = 'inventory';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Stok']];
$canAdjust = AuthService::hasPermission('inventory.adjust');

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

<div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;align-items:start;">
    <div>
        <div class="azr-filter-bar">
            <form method="get" action="/inventory/index.php" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
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
                <button type="submit" class="azr-btn azr-btn-primary">Filter</button>
                <a href="/inventory/index.php" class="azr-btn azr-btn-outline">Reset</a>
            </form>
        </div>

        <div class="azr-card">
            <div class="azr-card-header">
                <h2 class="azr-card-title">Riwayat Pergerakan Stok</h2>
                <?php if ($canAdjust): ?>
                <button type="button" class="azr-btn azr-btn-primary" data-azr-modal-open="adjustModal">+ Penyesuaian Stok</button>
                <?php endif; ?>
            </div>
            <div class="azr-table-wrap">
                <table class="azr-table">
                    <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Produk</th>
                        <th>Tipe</th>
                        <th>Qty</th>
                        <th>Stok Sebelum</th>
                        <th>Stok Sesudah</th>
                        <th>Oleh</th>
                    </tr>
                    </thead>
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
                        <tr><td colspan="7" style="text-align:center;color:var(--azr-gray-600);">Belum ada pergerakan stok.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="azr-card">
        <div class="azr-card-header"><h2 class="azr-card-title">Stok Menipis</h2></div>
        <?php foreach ($lowStock as $ls): ?>
            <div class="azr-lowstock-item">
                <span><?= Response::e($ls['name']) ?></span>
                <span style="font-weight:700;color:var(--azr-amber);">
                    <?= rtrim(rtrim(number_format((float) $ls['stock'], 3, ',', '.'), '0'), ',') ?> / <?= rtrim(rtrim(number_format((float) $ls['min_stock'], 3, ',', '.'), '0'), ',') ?> <?= Response::e($ls['unit_symbol'] ?: '') ?>
                </span>
            </div>
        <?php endforeach; ?>
        <?php if (empty($lowStock)): ?>
            <p style="color:var(--azr-gray-600);font-size:0.86rem;">Semua stok produk dalam batas aman.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($canAdjust): ?>
<div class="azr-modal-backdrop" id="adjustModal">
    <div class="azr-modal">
        <h3 class="azr-modal-title">Penyesuaian Stok Manual</h3>
        <form action="/inventory/adjust.php" method="post" data-azr-ajax>
            <?= Csrf::field() ?>
            <div class="azr-form-group">
                <label class="azr-label">Produk</label>
                <select class="azr-select" name="product_id" required>
                    <option value="">- Pilih Produk -</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"><?= Response::e($p['name']) ?> (<?= Response::e($p['sku']) ?>) - Stok: <?= rtrim(rtrim(number_format((float) $p['stock'], 3, ',', '.'), '0'), ',') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Arah Penyesuaian</label>
                <select class="azr-select" name="direction" required>
                    <option value="in">Tambah Stok (Masuk)</option>
                    <option value="out">Kurangi Stok (Keluar)</option>
                </select>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Jumlah</label>
                <input class="azr-input" type="number" step="0.001" min="0.001" name="quantity" required>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Alasan / Catatan Opname *</label>
                <textarea class="azr-textarea" name="reason" required placeholder="mis. Hasil stock opname, barang rusak, dsb."></textarea>
                <p class="azr-error-text" data-azr-error="reason"></p>
            </div>
            <div class="azr-modal-actions">
                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                <button type="submit" class="azr-btn azr-btn-primary">Simpan Penyesuaian</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
