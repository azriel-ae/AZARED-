<?php
use App\Auth\AuthService;
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Produk';
$activeMenu = 'products';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Produk']];

require __DIR__ . '/../layouts/main_top.php';

$page = max(1, (int) ($_GET['page'] ?? 1));
?>

<?php if (isset($_GET['created'])): ?>
<div class="azr-alert azr-alert-success" data-azr-autodismiss>Produk baru berhasil ditambahkan.</div>
<?php elseif (isset($_GET['updated'])): ?>
<div class="azr-alert azr-alert-success" data-azr-autodismiss>Produk berhasil diperbarui.</div>
<?php endif; ?>

<div class="azr-filter-bar">
    <form method="get" action="/products/index.php" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Cari</label>
            <input class="azr-input" type="text" name="search" placeholder="Nama, SKU, atau barcode"
                   value="<?= Response::e($filters['search']) ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Kategori</label>
            <select class="azr-select" name="category_id">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $filters['category_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= Response::e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Status</label>
            <select class="azr-select" name="status">
                <option value="">Semua</option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Stok</label>
            <select class="azr-select" name="stock_filter">
                <option value="">Semua</option>
                <option value="available" <?= $filters['stock_filter'] === 'available' ? 'selected' : '' ?>>Tersedia</option>
                <option value="low" <?= $filters['stock_filter'] === 'low' ? 'selected' : '' ?>>Stok Menipis</option>
                <option value="empty" <?= $filters['stock_filter'] === 'empty' ? 'selected' : '' ?>>Stok Habis</option>
            </select>
        </div>
        <button type="submit" class="azr-btn azr-btn-primary">Filter</button>
        <a href="/products/index.php" class="azr-btn azr-btn-outline">Reset</a>
    </form>
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Daftar Produk <span style="color:var(--azr-gray-600);font-weight:400;">(<?= (int) $total ?> produk)</span></h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="/categories/index.php" class="azr-btn azr-btn-outline azr-btn-sm">Kategori</a>
            <a href="/units/index.php" class="azr-btn azr-btn-outline azr-btn-sm">Satuan</a>
            <?php if (AuthService::hasPermission('products.export')): ?>
            <a href="/products/export.php" class="azr-btn azr-btn-outline azr-btn-sm">Export CSV</a>
            <?php endif; ?>
            <?php if (AuthService::hasPermission('products.import')): ?>
            <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" data-azr-modal-open="importModal">Import CSV</button>
            <?php endif; ?>
            <?php if (AuthService::hasPermission('products.create')): ?>
            <a href="/products/create.php" class="azr-btn azr-btn-primary">+ Tambah Produk</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead>
            <tr>
                <th>Produk</th>
                <th>SKU / Barcode</th>
                <th>Kategori</th>
                <th>Harga Jual</th>
                <th>Stok</th>
                <th>Status</th>
                <th style="text-align:right;">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <?php $lowStock = (float) $p['stock'] <= (float) $p['min_stock']; ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <img class="azr-table-thumb" src="<?= Response::e($p['image_path'] ?: '/assets/img/placeholder.png') ?>"
                                 onerror="this.style.visibility='hidden'" alt="">
                            <span><?= Response::e($p['name']) ?></span>
                        </div>
                    </td>
                    <td>
                        <?= Response::e($p['sku']) ?><br>
                        <span style="color:var(--azr-gray-600);font-size:0.8rem;"><?= Response::e($p['barcode'] ?: '-') ?></span>
                    </td>
                    <td><?= Response::e($p['category_name'] ?: '-') ?></td>
                    <td>Rp <?= number_format((float) $p['sell_price'], 0, ',', '.') ?></td>
                    <td>
                        <?= rtrim(rtrim(number_format((float) $p['stock'], 3, ',', '.'), '0'), ',') ?> <?= Response::e($p['unit_symbol'] ?: '') ?>
                        <?php if ($lowStock): ?><br><span class="azr-badge azr-badge-warning">Stok Menipis</span><?php endif; ?>
                    </td>
                    <td>
                        <span class="azr-badge <?= $p['status'] === 'active' ? 'azr-badge-active' : 'azr-badge-inactive' ?>">
                            <?= $p['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <?php if (AuthService::hasPermission('products.edit')): ?>
                        <a href="/products/edit.php?id=<?= (int) $p['id'] ?>" class="azr-btn azr-btn-outline azr-btn-sm">Edit</a>
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm"
                                data-azr-ajax-action="/products/toggle-status.php?id=<?= (int) $p['id'] ?>"
                                data-azr-confirm="Ubah status produk ini?">
                            <?= $p['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </button>
                        <?php endif; ?>
                        <?php if (AuthService::hasPermission('products.delete')): ?>
                        <button type="button" class="azr-btn azr-btn-danger azr-btn-sm"
                                data-azr-ajax-action="/products/destroy.php?id=<?= (int) $p['id'] ?>"
                                data-azr-confirm="Hapus produk '<?= Response::e($p['name']) ?>'? Produk akan dinonaktifkan dan disembunyikan dari daftar.">Hapus</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($products)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--azr-gray-600);">Tidak ada produk yang cocok dengan filter.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="azr-pagination">
        <?php
        $qs = $_GET;
        for ($i = 1; $i <= $totalPages; $i++):
            $qs['page'] = $i;
            $url = '/products/index.php?' . Response::e(http_build_query($qs));
        ?>
            <a href="<?= Response::e($url) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (AuthService::hasPermission('products.import')): ?>
<div class="azr-modal-backdrop" id="importModal">
    <div class="azr-modal">
        <h3 class="azr-modal-title">Import Produk (CSV)</h3>
        <p class="azr-help-text">Header CSV: sku,barcode,name,category,unit,cost_price,sell_price,stock,min_stock,tax_percent,status</p>
        <form action="/products/import.php" method="post" enctype="multipart/form-data" data-azr-ajax>
            <?= Csrf::field() ?>
            <div class="azr-form-group">
                <input class="azr-input" type="file" name="file" accept=".csv" required>
            </div>
            <div class="azr-modal-actions">
                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                <button type="submit" class="azr-btn azr-btn-primary">Import</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
