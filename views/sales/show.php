<?php
use App\Auth\AuthService;
use App\Helpers\Response;

$isPrint = !empty($_GET['print']);
$size = $printSize ?: '80mm';
$statusLabel = ['completed' => 'Selesai', 'held' => 'Ditahan', 'cancelled' => 'Dibatalkan', 'returned' => 'Diretur Penuh', 'partially_returned' => 'Diretur Sebagian'];
$statusBadge = ['completed' => 'azr-badge-active', 'held' => 'azr-badge-warning', 'cancelled' => 'azr-badge-inactive', 'returned' => 'azr-badge-danger', 'partially_returned' => 'azr-badge-warning'];
$payLabel = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'debit' => 'Kartu Debit', 'credit' => 'Kartu Kredit', 'ewallet' => 'E-Wallet', 'qris' => 'QRIS', 'other' => 'Lainnya'];

$pageTitle = 'Struk ' . $sale['invoice_no'];
$activeMenu = 'sales';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Penjualan', 'url' => '/sales/index.php'],
    ['label' => $sale['invoice_no']],
];

require __DIR__ . '/../layouts/main_top.php';
?>

<?php if (isset($_GET['returned'])): ?>
<div class="azr-alert azr-alert-success azr-no-print" data-azr-autodismiss>Retur penjualan berhasil diproses.</div>
<?php endif; ?>

<?php if ($isPrint && $size === 'a4'): ?>
    <!-- ============ A4 Invoice ============ -->
    <div class="azr-invoice-a4">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">
            <div>
                <div style="font-size:1.4rem;font-weight:800;color:var(--azr-blue-900);"><?= Response::e($companyLegalName ?: 'AZARED') ?></div>
                <div style="color:var(--azr-gray-600);font-size:0.85rem;"><?= Response::e($sale['store_name'] ?: '') ?><br><?= Response::e($sale['store_address'] ?: '') ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-weight:700;font-size:1.1rem;">INVOICE</div>
                <div><?= Response::e($sale['invoice_no']) ?></div>
                <div style="color:var(--azr-gray-600);font-size:0.85rem;"><?= Response::e($sale['created_at']) ?></div>
            </div>
        </div>
        <div style="margin-bottom:12px;">
            <strong>Pelanggan:</strong> <?= Response::e($sale['customer_name'] ?: 'Umum') ?><br>
            <strong>Kasir:</strong> <?= Response::e($sale['cashier_name'] ?: '-') ?>
        </div>
        <table>
            <thead><tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Diskon</th><th>Pajak</th><th>Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($sale['items'] as $it): ?>
                <tr>
                    <td><?= Response::e($it['product_name']) ?></td>
                    <td><?= rtrim(rtrim(number_format((float) $it['qty'], 3, ',', '.'), '0'), ',') ?></td>
                    <td>Rp <?= number_format((float) $it['unit_price'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format((float) $it['discount_amount'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format((float) $it['tax_amount'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format((float) $it['subtotal'], 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="azr-invoice-totals">
            <div class="azr-summary-row"><span>Subtotal</span><span>Rp <?= number_format((float) $sale['subtotal'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row"><span>Diskon</span><span>- Rp <?= number_format((float) $sale['discount_amount'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row"><span>Pajak</span><span>Rp <?= number_format((float) $sale['tax_amount'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row total"><span>TOTAL</span><span>Rp <?= number_format((float) $sale['grand_total'], 0, ',', '.') ?></span></div>
        </div>
        <div style="margin-top:30px;text-align:center;color:var(--azr-gray-600);font-size:0.82rem;"><?= Response::e($receiptFooterNote ?: 'Terima kasih atas kepercayaan Anda · AZARED') ?></div>
    </div>
    <span data-azr-autoprint hidden></span>

<?php elseif ($isPrint): ?>
    <!-- ============ Thermal receipt (58mm/80mm) ============ -->
    <div class="azr-receipt-wrap">
        <div class="azr-receipt size-<?= Response::e($size) ?>">
            <div class="azr-receipt-center">
                <div class="azr-receipt-brand">AZARED</div>
                <div><?= Response::e($sale['store_name'] ?: 'Toko AZARED') ?></div>
                <div><?= Response::e($sale['store_address'] ?: '') ?></div>
            </div>
            <hr class="azr-receipt-hr">
            <div class="azr-receipt-row"><span>No. Invoice</span><span><?= Response::e($sale['invoice_no']) ?></span></div>
            <div class="azr-receipt-row"><span>Tanggal</span><span><?= Response::e($sale['created_at']) ?></span></div>
            <div class="azr-receipt-row"><span>Kasir</span><span><?= Response::e($sale['cashier_name'] ?: '-') ?></span></div>
            <?php if (!empty($sale['customer_name'])): ?>
            <div class="azr-receipt-row"><span>Pelanggan</span><span><?= Response::e($sale['customer_name']) ?></span></div>
            <?php endif; ?>
            <hr class="azr-receipt-hr">
            <?php foreach ($sale['items'] as $it): ?>
                <div class="azr-receipt-item-name"><?= Response::e($it['product_name']) ?></div>
                <div class="azr-receipt-row"><span><?= rtrim(rtrim(number_format((float) $it['qty'], 3, ',', '.'), '0'), ',') ?> x Rp <?= number_format((float) $it['unit_price'], 0, ',', '.') ?></span><span>Rp <?= number_format((float) $it['subtotal'], 0, ',', '.') ?></span></div>
            <?php endforeach; ?>
            <hr class="azr-receipt-hr">
            <div class="azr-receipt-row"><span>Subtotal</span><span>Rp <?= number_format((float) $sale['subtotal'], 0, ',', '.') ?></span></div>
            <div class="azr-receipt-row"><span>Diskon</span><span>-Rp <?= number_format((float) $sale['discount_amount'], 0, ',', '.') ?></span></div>
            <div class="azr-receipt-row"><span>Pajak</span><span>Rp <?= number_format((float) $sale['tax_amount'], 0, ',', '.') ?></span></div>
            <div class="azr-receipt-row" style="font-weight:800;"><span>TOTAL</span><span>Rp <?= number_format((float) $sale['grand_total'], 0, ',', '.') ?></span></div>
            <hr class="azr-receipt-hr">
            <?php foreach ($sale['payments'] as $pay): ?>
                <div class="azr-receipt-row"><span><?= $payLabel[$pay['method']] ?? Response::e($pay['method']) ?></span><span>Rp <?= number_format((float) $pay['amount'], 0, ',', '.') ?></span></div>
            <?php endforeach; ?>
            <div class="azr-receipt-row"><span>Kembali</span><span>Rp <?= number_format((float) $sale['change_amount'], 0, ',', '.') ?></span></div>
            <hr class="azr-receipt-hr">
            <div class="azr-receipt-center"><?= Response::e($receiptFooterNote ?: 'Terima kasih atas kunjungan Anda!') ?><br>*** <?= Response::e($companyLegalName ?: 'AZARED') ?> ***</div>
        </div>
    </div>
    <span data-azr-autoprint hidden></span>

<?php else: ?>
    <!-- ============ Normal on-screen detail ============ -->
    <div class="azr-card">
        <div class="azr-card-header">
            <div>
                <h2 class="azr-card-title"><?= Response::e($sale['invoice_no']) ?></h2>
                <span class="azr-badge <?= $statusBadge[$sale['status']] ?? '' ?>"><?= $statusLabel[$sale['status']] ?? Response::e($sale['status']) ?></span>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="/sales/show.php?id=<?= (int) $sale['id'] ?>&print=1&size=80mm" target="_blank" class="azr-btn azr-btn-outline azr-btn-sm">Cetak Struk 80mm</a>
                <a href="/sales/show.php?id=<?= (int) $sale['id'] ?>&print=1&size=58mm" target="_blank" class="azr-btn azr-btn-outline azr-btn-sm">Cetak Struk 58mm</a>
                <a href="/sales/show.php?id=<?= (int) $sale['id'] ?>&print=1&size=a4" target="_blank" class="azr-btn azr-btn-outline azr-btn-sm">Cetak Invoice A4</a>
                <?php if (in_array($sale['status'], ['completed', 'partially_returned'], true) && AuthService::hasPermission('sales.return')): ?>
                <a href="/sales/return-form.php?id=<?= (int) $sale['id'] ?>" class="azr-btn azr-btn-outline azr-btn-sm">Retur Penjualan</a>
                <?php endif; ?>
                <a href="/sales/index.php" class="azr-btn azr-btn-outline azr-btn-sm">&larr; Kembali</a>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px;">
            <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Tanggal</div><div style="font-weight:700;"><?= Response::e($sale['created_at']) ?></div></div>
            <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Pelanggan</div><div style="font-weight:700;"><?= Response::e($sale['customer_name'] ?: 'Umum') ?></div></div>
            <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Kasir</div><div style="font-weight:700;"><?= Response::e($sale['cashier_name'] ?: '-') ?></div></div>
            <div><div style="color:var(--azr-gray-600);font-size:0.8rem;">Catatan</div><div style="font-weight:700;"><?= Response::e($sale['note'] ?: '-') ?></div></div>
        </div>

        <div class="azr-table-wrap">
            <table class="azr-table">
                <thead><tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Diskon</th><th>Pajak</th><th>Subtotal</th><th>Retur</th></tr></thead>
                <tbody>
                <?php foreach ($sale['items'] as $it): ?>
                    <tr>
                        <td><?= Response::e($it['product_name']) ?><br><span style="color:var(--azr-gray-600);font-size:0.78rem;"><?= Response::e($it['sku']) ?></span></td>
                        <td><?= rtrim(rtrim(number_format((float) $it['qty'], 3, ',', '.'), '0'), ',') ?></td>
                        <td>Rp <?= number_format((float) $it['unit_price'], 0, ',', '.') ?></td>
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
            <div class="azr-summary-row"><span>Subtotal</span><span>Rp <?= number_format((float) $sale['subtotal'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row"><span>Diskon</span><span>- Rp <?= number_format((float) $sale['discount_amount'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row"><span>Pajak</span><span>Rp <?= number_format((float) $sale['tax_amount'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row total"><span>TOTAL</span><span>Rp <?= number_format((float) $sale['grand_total'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row"><span>Dibayar</span><span>Rp <?= number_format((float) $sale['paid_total'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row"><span>Kembali</span><span>Rp <?= number_format((float) $sale['change_amount'], 0, ',', '.') ?></span></div>
        </div>

        <?php if (!empty($sale['payments'])): ?>
        <div style="margin-top:18px;">
            <h3 style="font-size:0.95rem;color:var(--azr-blue-900);">Metode Pembayaran</h3>
            <?php foreach ($sale['payments'] as $pay): ?>
                <div class="azr-summary-row"><span><?= $payLabel[$pay['method']] ?? Response::e($pay['method']) ?></span><span>Rp <?= number_format((float) $pay['amount'], 0, ',', '.') ?></span></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
