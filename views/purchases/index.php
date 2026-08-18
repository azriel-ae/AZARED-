<?php
use App\Auth\AuthService;
use App\Helpers\Response;

$pageTitle = 'Pembelian';
$activeMenu = 'purchases';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Pembelian']];
$page = max(1, (int) ($_GET['page'] ?? 1));

$statusLabel = ['draft' => 'Draft', 'received' => 'Diterima', 'cancelled' => 'Dibatalkan'];
$statusBadge = ['draft' => 'azr-badge-warning', 'received' => 'azr-badge-active', 'cancelled' => 'azr-badge-inactive'];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-filter-bar">
    <form method="get" action="/purchases/index.php" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Cari</label>
            <input class="azr-input" type="text" name="search" placeholder="No. pembelian / invoice supplier" value="<?= Response::e($filters['search']) ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Supplier</label>
            <select class="azr-select" name="supplier_id">
                <option value="">Semua Supplier</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) $filters['supplier_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= Response::e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Status</label>
            <select class="azr-select" name="status">
                <option value="">Semua</option>
                <?php foreach ($statusLabel as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $filters['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="azr-btn azr-btn-primary">Filter</button>
        <a href="/purchases/index.php" class="azr-btn azr-btn-outline">Reset</a>
    </form>
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Daftar Pembelian <span style="color:var(--azr-gray-600);font-weight:400;">(<?= (int) $total ?>)</span></h2>
        <?php if (AuthService::hasPermission('purchases.create')): ?>
        <a href="/purchases/create.php" class="azr-btn azr-btn-primary">+ Pembelian Baru</a>
        <?php endif; ?>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead>
            <tr><th>No. Pembelian</th><th>Tanggal</th><th>Supplier</th><th>Total</th><th>Status</th><th style="text-align:right;">Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($purchases as $p): ?>
                <tr>
                    <td><a href="/purchases/show.php?id=<?= (int) $p['id'] ?>"><?= Response::e($p['purchase_no']) ?></a></td>
                    <td><?= Response::e($p['purchase_date']) ?></td>
                    <td><?= Response::e($p['supplier_name'] ?: '-') ?></td>
                    <td>Rp <?= number_format((float) $p['total'], 0, ',', '.') ?></td>
                    <td><span class="azr-badge <?= $statusBadge[$p['status']] ?? '' ?>"><?= $statusLabel[$p['status']] ?? Response::e($p['status']) ?></span></td>
                    <td style="text-align:right;"><a href="/purchases/show.php?id=<?= (int) $p['id'] ?>" class="azr-btn azr-btn-outline azr-btn-sm">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($purchases)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--azr-gray-600);">Belum ada data pembelian.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="azr-pagination">
        <?php $qs = $_GET; for ($i = 1; $i <= $totalPages; $i++): $qs['page'] = $i; ?>
            <a href="/purchases/index.php?<?= Response::e(http_build_query($qs)) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
