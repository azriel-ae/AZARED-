<?php
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Kasir (POS)';
$activeMenu = 'pos';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Kasir']];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-pos" id="azrPos">
    <!-- ============ LEFT: product search + grid ============ -->
    <div class="azr-pos-left">
        <div class="azr-pos-search">
            <input type="text" id="azrPosSearch" class="azr-input" placeholder="Cari nama atau SKU produk...">
            <input type="text" id="azrPosBarcode" class="azr-input" placeholder="Scan barcode lalu Enter" style="max-width:220px;" autofocus>
        </div>
        <div class="azr-pos-categories" id="azrPosCategories">
            <div class="azr-pos-cat-chip active" data-category-id="">Semua</div>
            <?php foreach ($categories as $cat): ?>
                <div class="azr-pos-cat-chip" data-category-id="<?= (int) $cat['id'] ?>"><?= Response::e($cat['name']) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="azr-pos-products" id="azrPosProducts">
            <!-- populated by pos.js -->
        </div>
    </div>

    <!-- ============ RIGHT: cart + payment ============ -->
    <div class="azr-pos-right">
        <div class="azr-pos-right-header">
            <select class="azr-select" id="azrPosCustomer">
                <option value="">Pelanggan Umum</option>
                <?php foreach ($customers as $cust): ?>
                    <option value="<?= (int) $cust['id'] ?>"><?= Response::e($cust['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" data-azr-modal-open="heldCartModal">
                Hold (<?= count($heldCarts) ?>)
            </button>
        </div>

        <div class="azr-cart" id="azrPosCart"></div>

        <div style="padding:0 14px;">
            <div class="azr-form-group">
                <label class="azr-label">Catatan Transaksi</label>
                <input type="text" class="azr-input" id="azrPosNote" placeholder="Opsional">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <div class="azr-form-group">
                    <label class="azr-label">Diskon Transaksi</label>
                    <select class="azr-select" id="azrPosDiscountType">
                        <option value="amount">Rp</option>
                        <option value="percent">%</option>
                    </select>
                </div>
                <div class="azr-form-group">
                    <label class="azr-label">&nbsp;</label>
                    <input type="number" min="0" step="any" class="azr-input" id="azrPosDiscountValue" value="0">
                </div>
            </div>
        </div>

        <div class="azr-pos-summary">
            <div class="azr-summary-row"><span>Subtotal</span><span id="azrPosSubtotal">Rp 0</span></div>
            <div class="azr-summary-row"><span>Diskon</span><span id="azrPosDiscountAmt">- Rp 0</span></div>
            <div class="azr-summary-row"><span>Pajak</span><span id="azrPosTax">Rp 0</span></div>
            <div class="azr-summary-row total"><span>TOTAL</span><span id="azrPosTotal">Rp 0</span></div>
        </div>

        <div class="azr-pos-actions">
            <button type="button" class="azr-btn azr-btn-outline" id="azrPosClear">Kosongkan</button>
            <button type="button" class="azr-btn azr-btn-outline" id="azrPosHold">Hold</button>
            <button type="button" class="azr-btn azr-btn-primary" id="azrPosCharge" disabled>Bayar</button>
        </div>
    </div>
</div>

<!-- ============ Held carts modal ============ -->
<div class="azr-modal-backdrop" id="heldCartModal">
    <div class="azr-modal" style="max-width:480px;">
        <h3 class="azr-modal-title">Keranjang yang Ditahan (Hold)</h3>
        <div id="azrPosHeldList">
            <?php if (empty($heldCarts)): ?>
                <p style="color:var(--azr-gray-600);font-size:0.86rem;">Tidak ada keranjang yang ditahan.</p>
            <?php endif; ?>
            <?php foreach ($heldCarts as $hc): ?>
                <div class="azr-lowstock-item">
                    <div>
                        <strong><?= Response::e($hc['code']) ?></strong><br>
                        <span style="font-size:0.78rem;color:var(--azr-gray-600);">
                            <?= Response::e($hc['user_name'] ?: '-') ?> &middot;
                            <?= Response::e($hc['customer_name'] ?: 'Umum') ?> &middot;
                            <?= Response::e($hc['created_at']) ?>
                        </span>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <button type="button" class="azr-btn azr-btn-primary azr-btn-sm" data-resume-id="<?= (int) $hc['id'] ?>"
                                data-azr-modal-close>Muat</button>
                        <button type="button" class="azr-btn azr-btn-danger azr-btn-sm" data-discard-id="<?= (int) $hc['id'] ?>">Buang</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="azr-modal-actions">
            <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Tutup</button>
        </div>
    </div>
</div>

<!-- ============ Payment modal ============ -->
<div class="azr-modal-backdrop" id="azrPayModal">
    <div class="azr-modal" style="max-width:440px;">
        <h3 class="azr-modal-title">Pembayaran</h3>
        <div class="azr-summary-row total" style="margin-bottom:14px;">
            <span>Total Tagihan</span><span id="azrPayTotalDue">Rp 0</span>
        </div>

        <div id="azrPaySplitRows"></div>
        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" id="azrPayAddRow" style="margin-bottom:14px;">+ Tambah Metode Pembayaran (Split)</button>

        <div class="azr-summary-row"><span>Total Dibayar</span><span id="azrPayTotalPaid">Rp 0</span></div>
        <div class="azr-summary-row total"><span>Kembalian</span><span id="azrPayChange">Rp 0</span></div>

        <div class="azr-modal-actions">
            <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close-pay>Batal</button>
            <button type="button" class="azr-btn azr-btn-primary" id="azrPayConfirm">Proses Pembayaran</button>
        </div>
    </div>
</div>

<!-- ============ Receipt modal ============ -->
<div class="azr-modal-backdrop" id="azrReceiptModal">
    <div class="azr-modal" style="max-width:380px;">
        <div id="azrReceiptBody"></div>
        <div class="azr-receipt-actions azr-no-print">
            <button type="button" class="azr-btn azr-btn-outline" data-azr-print-receipt>Cetak (80mm/58mm)</button>
            <button type="button" class="azr-btn azr-btn-primary" data-azr-modal-close-receipt>Transaksi Baru</button>
        </div>
    </div>
</div>

<script src="/assets/js/pos.js"></script>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
