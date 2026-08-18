<?php
use App\Helpers\Response;

$pageTitle = 'Penjualan';
$activeMenu = 'sales';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Penjualan']];
$page = max(1, (int) ($_GET['page'] ?? 1));

$statusLabel = ['completed' => 'Selesai', 'held' => 'Ditahan', 'cancelled' => 'Dibatalkan', 'returned' => 'Diretur Penuh', 'partially_returned' => 'Diretur Sebagian'];
$statusBadge = ['completed' => 'azr-badge-active', 'held' => 'azr-badge-warning', 'cancelled' => 'azr-badge-inactive', 'returned' => 'azr-badge-danger', 'partially_returned' => 'azr-badge-warning'];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-filter-bar">
    <form method="get" action="/sales/index.php" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Cari</label>
            <input class="azr-input" type="text" name="search" placeholder="No. invoice / nama pelanggan" value="<?= Response::e($filters['search']) ?>">
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
        <div class="azr-form-group">
            <label class="azr-label">Dari Tanggal</label>
            <input class="azr-input" type="date" name="date_from" value="<?= Response::e($filters['date_from']) ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Sampai Tanggal</label>
            <input class="azr-input" type="date" name="date_to" value="<?= Response::e($filters['date_to']) ?>">
        </div>
        <button type="submit" class="azr-btn azr-btn-primary">Filter</button>
        <a href="/sales/index.php" class="azr-btn azr-btn-outline">Reset</a>
    </form>
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Daftar Transaksi Penjualan <span style="color:var(--azr-gray-600);font-weight:400;">(<?= (int) $total ?>)</span></h2>
        <a href="/pos/index.php" class="azr-btn azr-btn-primary">+ Transaksi Baru (Kasir)</a>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead><tr><th>No. Invoice</th><th>Tanggal</th><th>Pelanggan</th><th>Kasir</th><th>Total</th><th>Status</th><th style="text-align:right;">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($sales as $s): ?>
                <tr>
                    <td><a href="/sales/show.php?id=<?= (int) $s['id'] ?>"><?= Response::e($s['invoice_no']) ?></a></td>
                    <td><?= Response::e($s['created_at']) ?></td>
                    <td><?= Response::e($s['customer_name'] ?: 'Umum') ?></td>
                    <td><?= Response::e($s['cashier_name'] ?: '-') ?></td>
                    <td>Rp <?= number_format((float) $s['grand_total'], 0, ',', '.') ?></td>
                    <td><span class="azr-badge <?= $statusBadge[$s['status']] ?? '' ?>"><?= $statusLabel[$s['status']] ?? Response::e($s['status']) ?></span></td>
                    <td style="text-align:right;"><a href="/sales/show.php?id=<?= (int) $s['id'] ?>" class="azr-btn azr-btn-outline azr-btn-sm">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($sales)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--azr-gray-600);">Belum ada transaksi penjualan.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="azr-pagination">
        <?php $qs = $_GET; for ($i = 1; $i <= $totalPages; $i++): $qs['page'] = $i; ?>
            <a href="/sales/index.php?<?= Response::e(http_build_query($qs)) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
