<?php
use App\Auth\AuthService;
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Pelanggan';
$activeMenu = 'customers';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Pelanggan']];
$page = max(1, (int) ($_GET['page'] ?? 1));

$typeLabel = ['retail' => 'Retail', 'member' => 'Member', 'wholesale' => 'Grosir', 'corporate' => 'Korporat'];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-filter-bar">
    <form method="get" action="/customers/index.php" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
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
        <a href="/customers/index.php" class="azr-btn azr-btn-outline">Reset</a>
    </form>
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Daftar Pelanggan <span style="color:var(--azr-gray-600);font-weight:400;">(<?= (int) $total ?>)</span></h2>
        <?php if (AuthService::hasPermission('customers.create')): ?>
        <button type="button" class="azr-btn azr-btn-primary" data-azr-modal-open="customerCreateModal">+ Tambah Pelanggan</button>
        <?php endif; ?>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead>
            <tr>
                <th>Kode</th><th>Nama</th><th>Telepon</th><th>Tipe</th><th>Status</th><th style="text-align:right;">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?= Response::e($c['code']) ?></td>
                    <td><a href="/customers/show.php?id=<?= (int) $c['id'] ?>"><?= Response::e($c['name']) ?></a></td>
                    <td><?= Response::e($c['phone'] ?: '-') ?></td>
                    <td><?= $typeLabel[$c['type']] ?? Response::e($c['type']) ?></td>
                    <td>
                        <span class="azr-badge <?= $c['status'] === 'active' ? 'azr-badge-active' : 'azr-badge-inactive' ?>">
                            <?= $c['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a href="/customers/show.php?id=<?= (int) $c['id'] ?>" class="azr-btn azr-btn-outline azr-btn-sm">Detail</a>
                        <?php if (AuthService::hasPermission('customers.edit')): ?>
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" data-azr-modal-open="customerEditModal<?= (int) $c['id'] ?>">Edit</button>
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm"
                                data-azr-ajax-action="/customers/toggle-status.php?id=<?= (int) $c['id'] ?>"
                                data-azr-confirm="Ubah status pelanggan ini?">
                            <?= $c['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>

                <?php if (AuthService::hasPermission('customers.edit')): ?>
                <div class="azr-modal-backdrop" id="customerEditModal<?= (int) $c['id'] ?>">
                    <div class="azr-modal">
                        <h3 class="azr-modal-title">Edit Pelanggan</h3>
                        <form action="/customers/update.php?id=<?= (int) $c['id'] ?>" method="post" data-azr-ajax>
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

            <?php if (empty($customers)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--azr-gray-600);">Belum ada pelanggan.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="azr-pagination">
        <?php $qs = $_GET; for ($i = 1; $i <= $totalPages; $i++): $qs['page'] = $i; ?>
            <a href="/customers/index.php?<?= Response::e(http_build_query($qs)) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (AuthService::hasPermission('customers.create')): ?>
<div class="azr-modal-backdrop" id="customerCreateModal">
    <div class="azr-modal">
        <h3 class="azr-modal-title">Tambah Pelanggan</h3>
        <form action="/customers/store.php" method="post" data-azr-ajax>
            <?= Csrf::field() ?>
            <?php $c = []; require __DIR__ . '/_fields.php'; ?>
            <div class="azr-modal-actions">
                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                <button type="submit" class="azr-btn azr-btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
