<?php
use App\Auth\AuthService;
use App\Helpers\Response;

$pageTitle = 'Detail Pembelian';
$activeMenu = 'purchases';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Pembelian', 'url' => '/purchases/index.php'],
    ['label' => $purchase['purchase_no']],
];
$statusLabel = ['draft' => 'Draft', 'received' => 'Diterima', 'cancelled' => 'Dibatalkan'];
$statusBadge = ['draft' => 'azr-badge-warning', 'received' => 'azr-badge-active', 'cancelled' => 'azr-badge-inactive'];

require __DIR__ . '/../layouts/main_top.php';
?>

<?php if (isset($_GET['created'])): ?>
<div class="azr-alert azr-alert-success" data-azr-autodismiss>Pembelian berhasil disimpan.</div>
<?php elseif (isset($_GET['returned'])): ?>
<div class="azr-alert azr-alert-success" data-azr-autodismiss>Retur pembelian berhasil diproses.</div>
<?php endif; ?>

<div class="azr-card">
    <div class="azr-card-header">
        <div>
            <h2 class="azr-card-title"><?= Response::e($purchase['purchase_no']) ?></h2>
            <span class="azr-badge <?= $statusBadge[$purchase['status']] ?? '' ?>"><?= $statusLabel[$purchase['status']] ?? Response::e($purchase['status']) ?></span>
        </div>
        <div style="display:flex;gap:8px;">
            <?php if ($purchase['status'] === 'draft' && AuthService::hasPermission('purchases.edit')): ?>
            <button type="button" class="azr-btn azr-btn-primary"
                    data-azr-ajax-action="/purchases/receive.php?id=<?= (int) $purchase['id'] ?>"
                    data-azr-confirm="Tandai pembelian ini sebagai diterima? Stok akan bertambah otomatis.">Tandai Diterima</button>
            <?php endif; ?>
            <?php if ($purchase['status'] === 'received' && AuthService::hasPermission('purchases.return')): ?>
            <a href="/purchases/return-form.php?id=<?= (int) $purchase['id'] ?>" class="azr-btn azr-btn-outline">Retur ke Supplier</a>
            <?php endif; ?>
            <a href="/purchases/index.php" class="azr-btn azr-btn-outline">&larr; Kembali</a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px;">
        <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Supplier</div><div style="font-weight:700;"><?= Response::e($purchase['supplier_name']) ?></div></div>
        <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Tanggal</div><div style="font-weight:700;"><?= Response::e($purchase['purchase_date']) ?></div></div>
        <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">No. Invoice Supplier</div><div style="font-weight:700;"><?= Response::e($purchase['supplier_invoice_no'] ?: '-') ?></div></div>
        <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Dibuat Oleh</div><div style="font-weight:700;"><?= Response::e($purchase['created_by_name'] ?: '-') ?></div></div>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead><tr><th>Produk</th><th>Qty</th><th>Harga Beli</th><th>Diskon</th><th>Pajak</th><th>Subtotal</th><th>Retur</th></tr></thead>
            <tbody>
            <?php foreach ($purchase['items'] as $it): ?>
                <tr>
                    <td><?= Response::e($it['product_name']) ?><br><span style="color:var(--azr-gray-600);font-size:0.78rem;"><?= Response::e($it['sku']) ?></span></td>
                    <td><?= rtrim(rtrim(number_format((float) $it['qty'], 3, ',', '.'), '0'), ',') ?></td>
                    <td>Rp <?= number_format((float) $it['cost_price'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format((float) $it['discount_amount'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format((float) $it['tax_amount'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format((float) $it['subtotal'], 0, ',', '.') ?></td>
                    <td><?= (float) $it['returned_qty'] > 0 ? rtrim(rtrim(number_format((float) $it['returned_qty'], 3, ',', '.'), '0'), ',') : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="azr-invoice-totals" style="margin-top:14px;">
        <div class="azr-summary-row"><span>Subtotal</span><span>Rp <?= number_format((float) $purchase['subtotal'], 0, ',', '.') ?></span></div>
        <div class="azr-summary-row"><span>Diskon</span><span>- Rp <?= number_format((float) $purchase['discount_amount'], 0, ',', '.') ?></span></div>
        <div class="azr-summary-row"><span>Pajak</span><span>Rp <?= number_format((float) $purchase['tax_amount'], 0, ',', '.') ?></span></div>
        <div class="azr-summary-row total"><span>TOTAL</span><span>Rp <?= number_format((float) $purchase['total'], 0, ',', '.') ?></span></div>
    </div>

    <?php if (!empty($purchase['payments'])): ?>
    <div style="margin-top:18px;">
        <h3 style="font-size:0.95rem;color:var(--azr-blue-900);">Pembayaran</h3>
        <?php foreach ($purchase['payments'] as $pay): ?>
            <div class="azr-summary-row"><span><?= Response::e(ucfirst($pay['method'])) ?></span><span>Rp <?= number_format((float) $pay['amount'], 0, ',', '.') ?></span></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($purchase['note'])): ?>
    <div style="margin-top:14px;color:var(--azr-gray-600);font-size:0.86rem;">Catatan: <?= Response::e($purchase['note']) ?></div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
