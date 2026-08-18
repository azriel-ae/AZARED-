<?php
use App\Auth\AuthService;
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Role & Akses';
$activeMenu = 'roles';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Role & Akses'],
];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Role & Akses</h2>
        <div style="display:flex;gap:8px;">
            <a href="/permissions" class="azr-btn azr-btn-outline">Lihat Katalog Izin</a>
            <button type="button" class="azr-btn azr-btn-primary" data-azr-modal-open="roleCreateModal">+ Tambah Role</button>
        </div>
    </div>
    <p class="azr-help-text" style="margin-bottom:14px;">
        Role bawaan sistem (Admin, Owner, Manager, Cashier, Accountant, Tax Officer) tidak dapat dihapus atau
        diganti nama-kode aksesnya, tetapi izin yang dimilikinya tetap dapat diatur ulang lewat tombol "Atur Izin".
        Role Admin selalu memiliki seluruh izin dan tidak dapat dikurangi lewat halaman ini, untuk mencegah semua
        admin terkunci dari sistem.
    </p>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead>
            <tr>
                <th>Nama Role</th>
                <th>Deskripsi</th>
                <th>Jumlah Izin</th>
                <th>Jumlah Pengguna</th>
                <th>Tipe</th>
                <th style="text-align:right;">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($roles as $r): ?>
                <tr>
                    <td><strong><?= Response::e($r['name']) ?></strong><div class="azr-help-text"><?= Response::e($r['slug']) ?></div></td>
                    <td><?= Response::e($r['description'] ?: '-') ?></td>
                    <td><?= (int) $r['permission_count'] ?></td>
                    <td><?= (int) $r['user_count'] ?></td>
                    <td>
                        <span class="azr-badge <?= (int) $r['is_system'] === 1 ? 'azr-badge-info' : 'azr-badge-neutral' ?>">
                            <?= (int) $r['is_system'] === 1 ? 'Sistem' : 'Kustom' ?>
                        </span>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a href="/roles/permissions.php?id=<?= (int) $r['id'] ?>" class="azr-btn azr-btn-outline azr-btn-sm">Atur Izin</a>
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm"
                                data-azr-modal-open="roleEditModal<?= (int) $r['id'] ?>">Edit</button>
                        <?php if ((int) $r['is_system'] === 0): ?>
                        <button type="button" class="azr-btn azr-btn-danger azr-btn-sm"
                                data-azr-ajax-action="/roles/destroy.php?id=<?= (int) $r['id'] ?>"
                                data-azr-confirm="Hapus role '<?= Response::e($r['name']) ?>'? Role yang masih dipakai pengguna tidak dapat dihapus.">Hapus</button>
                        <?php endif; ?>
                    </td>
                </tr>

                <div class="azr-modal-backdrop" id="roleEditModal<?= (int) $r['id'] ?>">
                    <div class="azr-modal">
                        <h3 class="azr-modal-title">Edit Role</h3>
                        <form action="/roles/update.php?id=<?= (int) $r['id'] ?>" method="post" data-azr-ajax>
                            <?= Csrf::field() ?>
                            <div class="azr-form-group">
                                <label class="azr-label">Nama Role</label>
                                <input class="azr-input" type="text" name="name" value="<?= Response::e($r['name']) ?>" required maxlength="50">
                                <p class="azr-error-text" data-azr-error="name"></p>
                            </div>
                            <div class="azr-form-group">
                                <label class="azr-label">Deskripsi</label>
                                <input class="azr-input" type="text" name="description" value="<?= Response::e($r['description'] ?? '') ?>" maxlength="255">
                                <p class="azr-error-text" data-azr-error="description"></p>
                            </div>
                            <?php if ((int) $r['is_system'] === 1): ?>
                            <p class="azr-help-text">Kode akses (<?= Response::e($r['slug']) ?>) tidak dapat diubah karena role ini dipakai oleh sistem.</p>
                            <?php endif; ?>
                            <div class="azr-modal-actions">
                                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                                <button type="submit" class="azr-btn azr-btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="azr-modal-backdrop" id="roleCreateModal">
    <div class="azr-modal">
        <h3 class="azr-modal-title">Tambah Role Kustom</h3>
        <form action="/roles/store.php" method="post" data-azr-ajax>
            <?= Csrf::field() ?>
            <div class="azr-form-group">
                <label class="azr-label">Nama Role</label>
                <input class="azr-input" type="text" name="name" required maxlength="50" placeholder="mis. Supervisor Gudang">
                <p class="azr-error-text" data-azr-error="name"></p>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Deskripsi</label>
                <input class="azr-input" type="text" name="description" maxlength="255" placeholder="mis. Mengawasi stok dan penerimaan barang">
                <p class="azr-error-text" data-azr-error="description"></p>
            </div>
            <p class="azr-help-text">Setelah dibuat, atur izin role ini lewat tombol "Atur Izin" di daftar.</p>
            <div class="azr-modal-actions">
                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                <button type="submit" class="azr-btn azr-btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
