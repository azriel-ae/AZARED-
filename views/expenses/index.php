<?php
use App\Auth\AuthService;
use App\Helpers\Response;

$pageTitle = 'Pengeluaran';
$activeMenu = 'expenses';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Pengeluaran']];
$page = max(1, (int) ($_GET['page'] ?? 1));

$payLabel = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'debit' => 'Kartu Debit', 'credit' => 'Kartu Kredit', 'ewallet' => 'E-Wallet', 'qris' => 'QRIS', 'other' => 'Lainnya'];

require __DIR__ . '/../layouts/main_top.php';
?>

<?php if (isset($_GET['created'])): ?>
<div class="azr-alert azr-alert-success" data-azr-autodismiss>Pengeluaran berhasil dicatat.</div>
<?php elseif (isset($_GET['updated'])): ?>
<div class="azr-alert azr-alert-success" data-azr-autodismiss>Pengeluaran berhasil diperbarui.</div>
<?php endif; ?>

<div class="azr-filter-bar">
    <form method="get" action="/expenses/index.php" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Cari</label>
            <input class="azr-input" type="text" name="search" placeholder="Deskripsi / no. pengeluaran" value="<?= Response::e($filters['search']) ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Kategori</label>
            <select class="azr-select" name="category_id">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $filters['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= Response::e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Metode Pembayaran</label>
            <select class="azr-select" name="payment_method">
                <option value="">Semua</option>
                <?php foreach ($payLabel as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $filters['payment_method'] === $val ? 'selected' : '' ?>><?= $label ?></option>
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
        <a href="/expenses/index.php" class="azr-btn azr-btn-outline">Reset</a>
    </form>
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Daftar Pengeluaran <span style="color:var(--azr-gray-600);font-weight:400;">(<?= (int) $total ?>)</span></h2>
        <div style="display:flex;gap:8px;">
            <a href="/expense-categories/index.php" class="azr-btn azr-btn-outline azr-btn-sm">Kategori</a>
            <a href="/expenses/export.php?<?= Response::e(http_build_query($_GET)) ?>" class="azr-btn azr-btn-outline azr-btn-sm">Export CSV</a>
            <?php if (AuthService::hasPermission('expenses.create')): ?>
            <a href="/expenses/create.php" class="azr-btn azr-btn-primary">+ Tambah Pengeluaran</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead><tr><th>No.</th><th>Tanggal</th><th>Kategori</th><th>Deskripsi</th><th>Metode</th><th style="text-align:right;">Jumlah</th><th style="text-align:right;">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($expenses as $e): ?>
                <tr>
                    <td><a href="/expenses/show.php?id=<?= (int) $e['id'] ?>"><?= Response::e($e['expense_no']) ?></a></td>
                    <td><?= Response::e($e['expense_date']) ?></td>
                    <td><span class="azr-badge azr-badge-info"><?= Response::e($e['category_name']) ?></span></td>
                    <td><?= Response::e($e['description']) ?></td>
                    <td><?= $payLabel[$e['payment_method']] ?? Response::e($e['payment_method']) ?></td>
                    <td style="text-align:right;font-weight:700;">Rp <?= number_format((float) $e['amount'], 0, ',', '.') ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <?php if (AuthService::hasPermission('expenses.edit')): ?>
                        <a href="/expenses/edit.php?id=<?= (int) $e['id'] ?>" class="azr-btn azr-btn-outline azr-btn-sm">Edit</a>
                        <?php endif; ?>
                        <?php if (AuthService::hasPermission('expenses.delete')): ?>
                        <button type="button" class="azr-btn azr-btn-danger azr-btn-sm"
                                data-azr-ajax-action="/expenses/destroy.php?id=<?= (int) $e['id'] ?>"
                                data-azr-confirm="Hapus pengeluaran '<?= Response::e($e['description']) ?>'?">Hapus</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($expenses)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--azr-gray-600);">Tidak ada data pengeluaran.</td></tr>
            <?php else: ?>
                <tr style="background:var(--azr-blue-50);font-weight:700;">
                    <td colspan="5" style="text-align:right;">Total (halaman ini)</td>
                    <td style="text-align:right;">Rp <?= number_format((float) $periodTotal, 0, ',', '.') ?></td>
                    <td></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="azr-pagination">
        <?php $qs = $_GET; for ($i = 1; $i <= $totalPages; $i++): $qs['page'] = $i; ?>
            <a href="/expenses/index.php?<?= Response::e(http_build_query($qs)) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
