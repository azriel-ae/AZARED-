<?php
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Toko / Cabang';
$activeMenu = 'settings';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Pengaturan', 'url' => '/settings'],
    ['label' => 'Toko / Cabang'],
];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Toko / Cabang</h2>
        <div style="display:flex;gap:8px;">
            <a href="/settings" class="azr-btn azr-btn-outline">&larr; Kembali</a>
            <button type="button" class="azr-btn azr-btn-primary" data-azr-modal-open="storeCreateModal">+ Tambah Toko</button>
        </div>
    </div>
    <p class="azr-help-text" style="margin-bottom:14px;">
        Setiap transaksi (penjualan, pembelian, stok) tercatat dan dilaporkan per toko lewat kolom
        <code>store_id</code>. Akses pengguna ke masing-masing toko diatur di halaman Pengguna.
        Minimal harus ada satu toko berstatus aktif.
    </p>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead>
            <tr>
                <th>Nama Toko</th>
                <th>Kode</th>
                <th>Alamat</th>
                <th>Telepon</th>
                <th>NPWP</th>
                <th>Pengguna</th>
                <th>Status</th>
                <th style="text-align:right;">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($stores as $s): ?>
                <tr>
                    <td><strong><?= Response::e($s['name']) ?></strong></td>
                    <td><?= Response::e($s['code']) ?></td>
                    <td><?= Response::e($s['address'] ?: '-') ?></td>
                    <td><?= Response::e($s['phone'] ?: '-') ?></td>
                    <td><?= Response::e($s['tax_id'] ?: '-') ?></td>
                    <td><?= (int) $s['user_count'] ?></td>
                    <td>
                        <span class="azr-badge <?= $s['status'] === 'active' ? 'azr-badge-active' : 'azr-badge-inactive' ?>">
                            <?= $s['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm"
                                data-azr-modal-open="storeEditModal<?= (int) $s['id'] ?>">Edit</button>
                        <form action="/stores/toggle-status.php?id=<?= (int) $s['id'] ?>" method="post"
                              style="display:inline;" data-azr-confirm="Ubah status toko '<?= Response::e($s['name']) ?>'?">
                            <?= Csrf::field() ?>
                            <button type="submit" class="azr-btn azr-btn-outline azr-btn-sm">
                                <?= $s['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                        </form>
                    </td>
                </tr>

                <div class="azr-modal-backdrop" id="storeEditModal<?= (int) $s['id'] ?>">
                    <div class="azr-modal">
                        <h3 class="azr-modal-title">Edit Toko</h3>
                        <form action="/stores/update.php?id=<?= (int) $s['id'] ?>" method="post" data-azr-ajax>
                            <?= Csrf::field() ?>
                            <div class="azr-form-group">
                                <label class="azr-label">Nama Toko</label>
                                <input class="azr-input" type="text" name="name" value="<?= Response::e($s['name']) ?>" required maxlength="150">
                                <p class="azr-error-text" data-azr-error="name"></p>
                            </div>
                            <div class="azr-form-group">
                                <label class="azr-label">Kode Toko</label>
                                <input class="azr-input" type="text" name="code" value="<?= Response::e($s['code']) ?>" required maxlength="30">
                                <p class="azr-error-text" data-azr-error="code"></p>
                            </div>
                            <div class="azr-form-group">
                                <label class="azr-label">Alamat</label>
                                <input class="azr-input" type="text" name="address" value="<?= Response::e($s['address'] ?? '') ?>" maxlength="255">
                            </div>
                            <div class="azr-form-group">
                                <label class="azr-label">Telepon</label>
                                <input class="azr-input" type="text" name="phone" value="<?= Response::e($s['phone'] ?? '') ?>" maxlength="30">
                            </div>
                            <div class="azr-form-group">
                                <label class="azr-label">NPWP Toko</label>
                                <input class="azr-input" type="text" name="tax_id" value="<?= Response::e($s['tax_id'] ?? '') ?>" maxlength="50">
                            </div>
                            <div class="azr-form-group">
                                <label class="azr-label">Status</label>
                                <select class="azr-select" name="status">
                                    <option value="active" <?= $s['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="inactive" <?= $s['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                            </div>
                            <div class="azr-modal-actions">
                                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                                <button type="submit" class="azr-btn azr-btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($stores)): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--azr-gray-600);">Belum ada toko.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="azr-modal-backdrop" id="storeCreateModal">
    <div class="azr-modal">
        <h3 class="azr-modal-title">Tambah Toko / Cabang</h3>
        <form action="/stores/store.php" method="post" data-azr-ajax>
            <?= Csrf::field() ?>
            <div class="azr-form-group">
                <label class="azr-label">Nama Toko</label>
                <input class="azr-input" type="text" name="name" required maxlength="150" placeholder="mis. AZARED Cabang Rungkut">
                <p class="azr-error-text" data-azr-error="name"></p>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Kode Toko</label>
                <input class="azr-input" type="text" name="code" required maxlength="30" placeholder="mis. STORE-002">
                <p class="azr-error-text" data-azr-error="code"></p>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Alamat</label>
                <input class="azr-input" type="text" name="address" maxlength="255">
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Telepon</label>
                <input class="azr-input" type="text" name="phone" maxlength="30">
            </div>
            <div class="azr-form-group">
                <label class="azr-label">NPWP Toko</label>
                <input class="azr-input" type="text" name="tax_id" maxlength="50">
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

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
