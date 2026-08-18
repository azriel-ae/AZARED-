<?php
use App\Auth\AuthService;
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Pajak Masukan';
$activeMenu = 'tax';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Perpajakan', 'url' => '/tax'],
    ['label' => 'Pajak Masukan'],
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$statusLabel = ['none' => 'Belum Ada', 'draft' => 'Draft', 'issued' => 'Terbit'];
$statusBadge = ['none' => 'azr-badge-neutral', 'draft' => 'azr-badge-warning', 'issued' => 'azr-badge-active'];
$canManage = AuthService::hasPermission('tax.manage');

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-filter-bar azr-no-print">
    <form method="get" action="/tax/input" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Cari</label>
            <input class="azr-input" type="text" name="search" placeholder="No. pembelian / supplier" value="<?= Response::e($_GET['search'] ?? '') ?>">
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
            <label class="azr-label">Supplier</label>
            <select class="azr-select" name="supplier_id">
                <option value="">Semua</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($_GET['supplier_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= Response::e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Jenis Pajak</label>
            <select class="azr-select" name="tax_id">
                <option value="">Semua</option>
                <?php foreach ($taxes as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= (int) ($_GET['tax_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= Response::e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="azr-btn azr-btn-primary">Filter</button>
        <a href="/tax/input" class="azr-btn azr-btn-outline">Reset</a>
    </form>
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Pajak Masukan <span style="color:var(--azr-gray-600);font-weight:400;">(<?= (int) $total ?> baris)</span></h2>
        <div class="azr-no-print" style="display:flex;gap:8px;">
            <a href="/tax/input-export.php?<?= Response::e(http_build_query($_GET)) ?>" class="azr-btn azr-btn-outline azr-btn-sm">Export CSV</a>
            <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" data-azr-print>Cetak</button>
        </div>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead><tr><th>No. Pembelian</th><th>Tanggal</th><th>Supplier</th><th>Toko</th><th>Jenis Pajak</th><th>DPP</th><th>Tarif</th><th>Jumlah Pajak</th><th>No. Faktur</th><th>Status</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="/purchases/show.php?id=<?= (int) $r['transaction_id'] ?>"><?= Response::e($r['purchase_no']) ?></a></td>
                    <td><?= Response::e($r['transaction_date']) ?></td>
                    <td><?= Response::e($r['supplier_name'] ?: '-') ?></td>
                    <td><?= Response::e($r['store_name'] ?: '-') ?></td>
                    <td><?= Response::e($r['tax_name']) ?></td>
                    <td>Rp <?= number_format((float) $r['taxable_amount'], 0, ',', '.') ?></td>
                    <td><?= number_format((float) $r['tax_rate'], 2) ?>%</td>
                    <td style="font-weight:700;">Rp <?= number_format((float) $r['tax_amount'], 0, ',', '.') ?></td>
                    <td><?= Response::e($r['invoice_no'] ?: '-') ?></td>
                    <td><span class="azr-badge <?= $statusBadge[$r['invoice_status']] ?? '' ?>"><?= $statusLabel[$r['invoice_status']] ?? Response::e($r['invoice_status']) ?></span></td>
                    <?php if ($canManage): ?>
                    <td class="azr-no-print">
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm"
                                data-azr-modal-open="invModal_purchase_<?= (int) $r['transaction_id'] ?>">Faktur</button>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="11" style="text-align:center;color:var(--azr-gray-600);">Tidak ada data yang cocok dengan filter.</td></tr>
            <?php else: ?>
                <tr style="background:var(--azr-blue-50);font-weight:700;">
                    <td colspan="5" style="text-align:right;">Total (<?= $summary['count'] ?> baris)</td>
                    <td>Rp <?= number_format($summary['taxable'], 0, ',', '.') ?></td>
                    <td></td>
                    <td>Rp <?= number_format($summary['tax'], 0, ',', '.') ?></td>
                    <td colspan="<?= $canManage ? 3 : 2 ?>"></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="azr-pagination azr-no-print">
        <?php $qs = $_GET; for ($i = 1; $i <= $totalPages; $i++): $qs['page'] = $i; ?>
            <a href="/tax/input?<?= Response::e(http_build_query($qs)) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($canManage): foreach ($rows as $r): ?>
<div class="azr-modal-backdrop" id="invModal_purchase_<?= (int) $r['transaction_id'] ?>">
    <div class="azr-modal">
        <h3 class="azr-modal-title">Nomor Faktur Pajak - <?= Response::e($r['purchase_no']) ?></h3>
        <p class="azr-help-text">Nomor ini adalah catatan referensi internal AZARED (mis. nomor faktur pajak dari supplier), bukan nomor resmi dari sistem e-Faktur DJP.</p>
        <form action="/tax/update-invoice.php" method="post" data-azr-ajax>
            <?= Csrf::field() ?>
            <input type="hidden" name="transaction_type" value="purchase">
            <input type="hidden" name="transaction_id" value="<?= (int) $r['transaction_id'] ?>">
            <div class="azr-form-group">
                <label class="azr-label">Nomor Faktur Pajak</label>
                <input class="azr-input" type="text" name="invoice_no" value="<?= Response::e($r['invoice_no'] ?? '') ?>" maxlength="60">
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Tanggal Faktur</label>
                <input class="azr-input" type="date" name="invoice_date" value="<?= Response::e($r['invoice_date'] ?? '') ?>">
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Status Faktur</label>
                <select class="azr-select" name="invoice_status">
                    <?php foreach ($statusLabel as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $r['invoice_status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="azr-modal-actions">
                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                <button type="submit" class="azr-btn azr-btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; endif; ?>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
