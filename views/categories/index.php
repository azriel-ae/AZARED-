<?php
use App\Auth\AuthService;
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Kategori Produk';
$activeMenu = 'products';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Produk', 'url' => '/products/index.php'],
    ['label' => 'Kategori'],
];
$canManage = AuthService::hasPermission('categories.manage');

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Kategori Produk</h2>
        <div style="display:flex;gap:8px;">
            <a href="/products/index.php" class="azr-btn azr-btn-outline">&larr; Kembali ke Produk</a>
            <?php if ($canManage): ?>
            <button type="button" class="azr-btn azr-btn-primary" data-azr-modal-open="categoryCreateModal">+ Tambah Kategori</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead>
            <tr>
                <th>Nama</th>
                <th>Slug</th>
                <th>Jumlah Produk</th>
                <th>Status</th>
                <?php if ($canManage): ?><th style="text-align:right;">Aksi</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td><?= Response::e($c['name']) ?></td>
                    <td><?= Response::e($c['slug']) ?></td>
                    <td><?= (int) $c['product_count'] ?></td>
                    <td>
                        <span class="azr-badge <?= $c['status'] === 'active' ? 'azr-badge-active' : 'azr-badge-inactive' ?>">
                            <?= $c['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <?php if ($canManage): ?>
                    <td style="text-align:right;white-space:nowrap;">
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm"
                                data-azr-modal-open="categoryEditModal<?= (int) $c['id'] ?>">Edit</button>
                        <button type="button" class="azr-btn azr-btn-danger azr-btn-sm"
                                data-azr-ajax-action="/categories/destroy.php?id=<?= (int) $c['id'] ?>"
                                data-azr-confirm="Hapus kategori '<?= Response::e($c['name']) ?>'? Kategori yang masih dipakai produk tidak dapat dihapus.">Hapus</button>
                    </td>
                    <?php endif; ?>
                </tr>

                <?php if ($canManage): ?>
                <div class="azr-modal-backdrop" id="categoryEditModal<?= (int) $c['id'] ?>">
                    <div class="azr-modal">
                        <h3 class="azr-modal-title">Edit Kategori</h3>
                        <form action="/categories/update.php?id=<?= (int) $c['id'] ?>" method="post" data-azr-ajax>
                            <?= Csrf::field() ?>
                            <div class="azr-form-group">
                                <label class="azr-label">Nama Kategori</label>
                                <input class="azr-input" type="text" name="name" value="<?= Response::e($c['name']) ?>" required maxlength="100">
                                <p class="azr-error-text" data-azr-error="name"></p>
                            </div>
                            <div class="azr-form-group">
                                <label class="azr-label">Status</label>
                                <select class="azr-select" name="status">
                                    <option value="active" <?= $c['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="inactive" <?= $c['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                            </div>
                            <div class="azr-modal-actions">
                                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                                <button type="submit" class="azr-btn azr-btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if (empty($categories)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--azr-gray-600);">Belum ada kategori.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canManage): ?>
<div class="azr-modal-backdrop" id="categoryCreateModal">
    <div class="azr-modal">
        <h3 class="azr-modal-title">Tambah Kategori</h3>
        <form action="/categories/store.php" method="post" data-azr-ajax>
            <?= Csrf::field() ?>
            <div class="azr-form-group">
                <label class="azr-label">Nama Kategori</label>
                <input class="azr-input" type="text" name="name" required maxlength="100" placeholder="mis. Minuman">
                <p class="azr-error-text" data-azr-error="name"></p>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Status</label>
                <select class="azr-select" name="status">
                    <option value="active" selected>Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </div>
            <div class="azr-modal-actions">
                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                <button type="submit" class="azr-btn azr-btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
