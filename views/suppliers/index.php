<?php
use App\Auth\AuthService;
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Supplier';
$activeMenu = 'suppliers';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Supplier']];
$page = max(1, (int) ($_GET['page'] ?? 1));

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-filter-bar">
    <form method="get" action="/suppliers/index.php" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Cari</label>
            <input class="azr-input" type="text" name="search" placeholder="Nama, kode, atau telepon" value="<?= Response::e($filters['search']) ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Status</label>
            <select class="azr-select" name="status">
                <option value="">Semua</option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>
        <button type="submit" class="azr-btn azr-btn-primary">Filter</button>
        <a href="/suppliers/index.php" class="azr-btn azr-btn-outline">Reset</a>
    </form>
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Daftar Supplier <span style="color:var(--azr-gray-600);font-weight:400;">(<?= (int) $total ?>)</span></h2>
        <?php if (AuthService::hasPermission('suppliers.create')): ?>
        <button type="button" class="azr-btn azr-btn-primary" data-azr-modal-open="supplierCreateModal">+ Tambah Supplier</button>
        <?php endif; ?>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead>
            <tr><th>Kode</th><th>Nama</th><th>Kontak</th><th>Telepon</th><th>Status</th><th style="text-align:right;">Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td><?= Response::e($s['code']) ?></td>
                    <td><a href="/suppliers/show.php?id=<?= (int) $s['id'] ?>"><?= Response::e($s['name']) ?></a></td>
                    <td><?= Response::e($s['contact_person'] ?: '-') ?></td>
                    <td><?= Response::e($s['phone'] ?: '-') ?></td>
                    <td>
                        <span class="azr-badge <?= $s['status'] === 'active' ? 'azr-badge-active' : 'azr-badge-inactive' ?>">
                            <?= $s['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a href="/suppliers/show.php?id=<?= (int) $s['id'] ?>" class="azr-btn azr-btn-outline azr-btn-sm">Detail</a>
                        <?php if (AuthService::hasPermission('suppliers.edit')): ?>
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" data-azr-modal-open="supplierEditModal<?= (int) $s['id'] ?>">Edit</button>
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm"
                                data-azr-ajax-action="/suppliers/toggle-status.php?id=<?= (int) $s['id'] ?>"
                                data-azr-confirm="Ubah status supplier ini?">
                            <?= $s['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>

                <?php if (AuthService::hasPermission('suppliers.edit')): ?>
                <div class="azr-modal-backdrop" id="supplierEditModal<?= (int) $s['id'] ?>">
                    <div class="azr-modal">
                        <h3 class="azr-modal-title">Edit Supplier</h3>
                        <form action="/suppliers/update.php?id=<?= (int) $s['id'] ?>" method="post" data-azr-ajax>
                            <?= Csrf::field() ?>
                            <?php require __DIR__ . '/_fields.php'; ?>
                            <div class="azr-modal-actions">
                                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                                <button type="submit" class="azr-btn azr-btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if (empty($suppliers)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--azr-gray-600);">Belum ada supplier.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="azr-pagination">
        <?php $qs = $_GET; for ($i = 1; $i <= $totalPages; $i++): $qs['page'] = $i; ?>
            <a href="/suppliers/index.php?<?= Response::e(http_build_query($qs)) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (AuthService::hasPermission('suppliers.create')): ?>
<div class="azr-modal-backdrop" id="supplierCreateModal">
    <div class="azr-modal">
        <h3 class="azr-modal-title">Tambah Supplier</h3>
        <form action="/suppliers/store.php" method="post" data-azr-ajax>
            <?= Csrf::field() ?>
            <?php $s = []; require __DIR__ . '/_fields.php'; ?>
            <div class="azr-modal-actions">
                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                <button type="submit" class="azr-btn azr-btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
