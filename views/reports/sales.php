<?php
use App\Helpers\Response;

$pageTitle = 'Laporan Penjualan';
$activeMenu = 'reports';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Laporan Penjualan']];
$page = max(1, (int) ($_GET['page'] ?? 1));

$statusLabel = ['completed' => 'Selesai', 'held' => 'Ditahan', 'cancelled' => 'Dibatalkan', 'returned' => 'Diretur Penuh', 'partially_returned' => 'Diretur Sebagian'];
$statusBadge = ['completed' => 'azr-badge-active', 'held' => 'azr-badge-warning', 'cancelled' => 'azr-badge-inactive', 'returned' => 'azr-badge-danger', 'partially_returned' => 'azr-badge-warning'];
$payLabel = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'debit' => 'Debit', 'credit' => 'Kredit', 'ewallet' => 'E-Wallet', 'qris' => 'QRIS', 'other' => 'Lainnya'];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-filter-bar azr-no-print">
    <form method="get" action="/reports/sales" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Cari</label>
            <input class="azr-input" type="text" name="search" placeholder="No. invoice / customer" value="<?= Response::e($_GET['search'] ?? '') ?>">
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
            <label class="azr-label">Toko</label>
            <select class="azr-select" name="store_id">
                <option value="">Semua</option>
                <?php foreach ($stores as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($_GET['store_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= Response::e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Kasir</label>
            <select class="azr-select" name="user_id">
                <option value="">Semua</option>
                <?php foreach ($cashiers as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= (int) ($_GET['user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= Response::e($u['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Customer</label>
            <select class="azr-select" name="customer_id">
                <option value="">Semua</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($_GET['customer_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= Response::e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Metode Bayar</label>
            <select class="azr-select" name="payment_method">
                <option value="">Semua</option>
                <?php foreach ($payLabel as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($_GET['payment_method'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
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
        <a href="/reports/sales" class="azr-btn azr-btn-outline">Reset</a>
    </form>
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Laporan Penjualan <span style="color:var(--azr-gray-600);font-weight:400;">(<?= (int) $total ?> transaksi)</span></h2>
        <div class="azr-no-print" style="display:flex;gap:8px;">
            <a href="/reports/sales-export.php?<?= Response::e(http_build_query($_GET)) ?>" class="azr-btn azr-btn-outline azr-btn-sm">Export CSV</a>
            <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" data-azr-print>Cetak</button>
        </div>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead><tr><th>Invoice</th><th>Tanggal</th><th>Customer</th><th>Kasir</th><th>Subtotal</th><th>Diskon</th><th>Pajak</th><th>Total</th><th>Pembayaran</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($sales as $s): ?>
                <tr>
                    <td><a href="/sales/show.php?id=<?= (int) $s['id'] ?>"><?= Response::e($s['invoice_no']) ?></a></td>
                    <td><?= Response::e($s['created_at']) ?></td>
                    <td><?= Response::e($s['customer_name'] ?: 'Umum') ?></td>
                    <td><?= Response::e($s['cashier_name'] ?: '-') ?></td>
                    <td>Rp <?= number_format((float) $s['subtotal'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format((float) $s['discount_amount'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format((float) $s['tax_amount'], 0, ',', '.') ?></td>
                    <td style="font-weight:700;">Rp <?= number_format((float) $s['grand_total'], 0, ',', '.') ?></td>
                    <td><?= Response::e($s['payment_methods'] ?: '-') ?></td>
                    <td><span class="azr-badge <?= $statusBadge[$s['status']] ?? '' ?>"><?= $statusLabel[$s['status']] ?? Response::e($s['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($sales)): ?>
                <tr><td colspan="10" style="text-align:center;color:var(--azr-gray-600);">Tidak ada data yang cocok dengan filter.</td></tr>
            <?php else: ?>
                <tr style="background:var(--azr-blue-50);font-weight:700;">
                    <td colspan="4" style="text-align:right;">Total (<?= $summary['count'] ?> transaksi)</td>
                    <td>Rp <?= number_format($summary['subtotal'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($summary['discount'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($summary['tax'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($summary['total'], 0, ',', '.') ?></td>
                    <td colspan="2"></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="azr-pagination azr-no-print">
        <?php $qs = $_GET; for ($i = 1; $i <= $totalPages; $i++): $qs['page'] = $i; ?>
            <a href="/reports/sales?<?= Response::e(http_build_query($qs)) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
