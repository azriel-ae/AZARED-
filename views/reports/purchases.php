<?php
use App\Helpers\Response;

$pageTitle = 'Laporan Pembelian';
$activeMenu = 'reports';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Laporan Pembelian']];
$page = max(1, (int) ($_GET['page'] ?? 1));

$statusLabel = ['draft' => 'Draft', 'received' => 'Diterima', 'cancelled' => 'Dibatalkan'];
$statusBadge = ['draft' => 'azr-badge-warning', 'received' => 'azr-badge-active', 'cancelled' => 'azr-badge-inactive'];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-filter-bar azr-no-print">
    <form method="get" action="/reports/purchases" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Cari</label>
            <input class="azr-input" type="text" name="search" placeholder="No. pembelian / invoice supplier" value="<?= Response::e($_GET['search'] ?? '') ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Dari Tanggal</label>
            <input class="azr-input" type="date" name="date_from" value="<?= Response::e($_GET['date_from'] ?? '') ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Sampai Tanggal</label>
            <input class="azr-input" type="date" name="date_to" value="<?= Response::e($_GET['date_to'] ?? '') ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Supplier</label>
            <select class="azr-select" name="supplier_id">
                <option value="">Semua</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($_GET['supplier_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= Response::e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Status</label>
            <select class="azr-select" name="status">
                <option value="">Semua</option>
                <?php foreach ($statusLabel as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($_GET['status'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="azr-btn azr-btn-primary">Filter</button>
        <a href="/reports/purchases" class="azr-btn azr-btn-outline">Reset</a>
    </form>
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Laporan Pembelian <span style="color:var(--azr-gray-600);font-weight:400;">(<?= (int) $total ?>)</span></h2>
        <div class="azr-no-print" style="display:flex;gap:8px;">
            <a href="/reports/purchases-export.php?<?= Response::e(http_build_query($_GET)) ?>" class="azr-btn azr-btn-outline azr-btn-sm">Export CSV</a>
            <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" data-azr-print>Cetak</button>
        </div>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead><tr><th>No. Pembelian</th><th>Tanggal</th><th>Supplier</th><th>Subtotal</th><th>Diskon</th><th>Pajak</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($purchases as $p): ?>
                <tr>
                    <td><a href="/purchases/show.php?id=<?= (int) $p['id'] ?>"><?= Response::e($p['purchase_no']) ?></a></td>
                    <td><?= Response::e($p['purchase_date']) ?></td>
                    <td><?= Response::e($p['supplier_name'] ?: '-') ?></td>
                    <td>Rp <?= number_format((float) $p['subtotal'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format((float) $p['discount_amount'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format((float) $p['tax_amount'], 0, ',', '.') ?></td>
                    <td style="font-weight:700;">Rp <?= number_format((float) $p['total'], 0, ',', '.') ?></td>
                    <td><span class="azr-badge <?= $statusBadge[$p['status']] ?? '' ?>"><?= $statusLabel[$p['status']] ?? Response::e($p['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($purchases)): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--azr-gray-600);">Tidak ada data yang cocok dengan filter.</td></tr>
            <?php else: ?>
                <tr style="background:var(--azr-blue-50);font-weight:700;">
                    <td colspan="3" style="text-align:right;">Total (<?= $summary['count'] ?>)</td>
                    <td>Rp <?= number_format($summary['subtotal'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($summary['discount'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($summary['tax'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($summary['total'], 0, ',', '.') ?></td>
                    <td></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="azr-pagination azr-no-print">
        <?php $qs = $_GET; for ($i = 1; $i <= $totalPages; $i++): $qs['page'] = $i; ?>
            <a href="/reports/purchases?<?= Response::e(http_build_query($qs)) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
