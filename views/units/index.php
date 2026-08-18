<?php
use App\Auth\AuthService;
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Satuan Produk';
$activeMenu = 'products';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Produk', 'url' => '/products/index.php'],
    ['label' => 'Satuan'],
];
$canManage = AuthService::hasPermission('units.manage');

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Satuan Produk</h2>
        <div style="display:flex;gap:8px;">
            <a href="/products/index.php" class="azr-btn azr-btn-outline">&larr; Kembali ke Produk</a>
            <?php if ($canManage): ?>
            <button type="button" class="azr-btn azr-btn-primary" data-azr-modal-open="unitCreateModal">+ Tambah Satuan</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead>
            <tr>
                <th>Nama</th>
                <th>Simbol</th>
                <th>Status</th>
                <?php if ($canManage): ?><th style="text-align:right;">Aksi</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($units as $u): ?>
                <tr>
                    <td><?= Response::e($u['name']) ?></td>
                    <td><?= Response::e($u['symbol']) ?></td>
                    <td>
                        <span class="azr-badge <?= $u['status'] === 'active' ? 'azr-badge-active' : 'azr-badge-inactive' ?>">
                            <?= $u['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <?php if ($canManage): ?>
                    <td style="text-align:right;white-space:nowrap;">
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm"
                                data-azr-modal-open="unitEditModal<?= (int) $u['id'] ?>">Edit</button>
                        <button type="button" class="azr-btn azr-btn-danger azr-btn-sm"
                                data-azr-ajax-action="/units/destroy.php?id=<?= (int) $u['id'] ?>"
                                data-azr-confirm="Hapus satuan '<?= Response::e($u['name']) ?>'?">Hapus</button>
                    </td>
                    <?php endif; ?>
                </tr>

                <?php if ($canManage): ?>
                <div class="azr-modal-backdrop" id="unitEditModal<?= (int) $u['id'] ?>">
                    <div class="azr-modal">
                        <h3 class="azr-modal-title">Edit Satuan</h3>
                        <form action="/units/update.php?id=<?= (int) $u['id'] ?>" method="post" data-azr-ajax>
                            <?= Csrf::field() ?>
                            <div class="azr-form-group">
                                <label class="azr-label">Nama Satuan</label>
                                <input class="azr-input" type="text" name="name" value="<?= Response::e($u['name']) ?>" required maxlength="50">
                                <p class="azr-error-text" data-azr-error="name"></p>
                            </div>
                            <div class="azr-form-group">
                                <label class="azr-label">Simbol</label>
                                <input class="azr-input" type="text" name="symbol" value="<?= Response::e($u['symbol']) ?>" required maxlength="10">
                                <p class="azr-error-text" data-azr-error="symbol"></p>
                            </div>
                            <div class="azr-form-group">
                                <label class="azr-label">Status</label>
                                <select class="azr-select" name="status">
                                    <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="inactive" <?= $u['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
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

            <?php if (empty($units)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--azr-gray-600);">Belum ada satuan.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canManage): ?>
<div class="azr-modal-backdrop" id="unitCreateModal">
    <div class="azr-modal">
        <h3 class="azr-modal-title">Tambah Satuan</h3>
        <form action="/units/store.php" method="post" data-azr-ajax>
            <?= Csrf::field() ?>
            <div class="azr-form-group">
                <label class="azr-label">Nama Satuan</label>
                <input class="azr-input" type="text" name="name" required maxlength="50" placeholder="mis. Pieces">
                <p class="azr-error-text" data-azr-error="name"></p>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Simbol</label>
                <input class="azr-input" type="text" name="symbol" required maxlength="10" placeholder="mis. pcs">
                <p class="azr-error-text" data-azr-error="symbol"></p>
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
