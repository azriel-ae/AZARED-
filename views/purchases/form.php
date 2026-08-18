<?php
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Pembelian Baru';
$activeMenu = 'purchases';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Pembelian', 'url' => '/purchases/index.php'],
    ['label' => 'Baru'],
];

require __DIR__ . '/../layouts/main_top.php';
?>

<?php if (isset($_GET['error'])): ?>
<div class="azr-alert azr-alert-error" data-azr-autodismiss>
    <?= $_GET['error'] === 'invalid' ? 'Pilih supplier dan minimal satu produk dengan jumlah yang valid.' : 'Gagal menyimpan pembelian. Silakan coba lagi.' ?>
</div>
<?php endif; ?>

<form action="/purchases/store.php" method="post" id="azrPurchaseForm">
    <?= Csrf::field() ?>

    <div class="azr-card">
        <div class="azr-card-header"><h2 class="azr-card-title">Informasi Pembelian</h2></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;">
            <div class="azr-form-group">
                <label class="azr-label">Supplier *</label>
                <select class="azr-select" name="supplier_id" required>
                    <option value="">- Pilih Supplier -</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= Response::e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Tanggal Pembelian</label>
                <input class="azr-input" type="date" name="purchase_date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="azr-form-group">
                <label class="azr-label">No. Invoice Supplier</label>
                <input class="azr-input" type="text" name="supplier_invoice_no" maxlength="80">
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Status</label>
                <select class="azr-select" name="status">
                    <option value="received" selected>Diterima (stok langsung bertambah)</option>
                    <option value="draft">Draft (stok belum bertambah)</option>
                </select>
            </div>
        </div>
    </div>

    <div class="azr-card">
        <div class="azr-card-header">
            <h2 class="azr-card-title">Item Pembelian</h2>
            <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" id="azrAddItemRow">+ Tambah Baris</button>
        </div>
        <div class="azr-table-wrap">
            <table class="azr-table" id="azrItemsTable">
                <thead>
                <tr>
                    <th style="min-width:220px;">Produk</th>
                    <th style="width:100px;">Qty</th>
                    <th style="width:140px;">Harga Beli</th>
                    <th style="width:120px;">Diskon (Rp)</th>
                    <th style="width:150px;">Pajak</th>
                    <th style="width:90px;">Tarif (%)</th>
                    <th style="width:40px;"></th>
                </tr>
                </thead>
                <tbody id="azrItemsBody"></tbody>
            </table>
        </div>
        <template id="azrItemRowTpl">
            <tr>
                <td>
                    <select class="azr-select" name="product_id[]" data-role="product" required>
                        <option value="">- Pilih Produk -</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" data-cost="<?= (float) $p['cost_price'] ?>"><?= Response::e($p['name']) ?> (<?= Response::e($p['sku']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input class="azr-input" type="number" name="qty[]" min="0.001" step="any" value="1" data-role="qty" required></td>
                <td><input class="azr-input" type="number" name="cost_price[]" min="0" step="any" value="0" data-role="cost"></td>
                <td><input class="azr-input" type="number" name="item_discount[]" min="0" step="any" value="0"></td>
                <td>
                    <select class="azr-select" name="tax_id[]" data-role="tax">
                        <option value="">- Tanpa Pajak -</option>
                        <?php foreach ($taxes as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" data-rate="<?= (float) $t['current_rate'] ?>"><?= Response::e($t['name']) ?> (<?= (float) $t['current_rate'] ?>%)</option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input class="azr-input" type="number" name="item_tax[]" min="0" max="100" step="any" value="0" data-role="tax-rate"></td>
                <td><button type="button" class="azr-cart-item-remove" data-role="remove-row">&times;</button></td>
            </tr>
        </template>
    </div>

    <div class="azr-card">
        <div class="azr-card-header"><h2 class="azr-card-title">Pembayaran</h2></div>
        <div id="azrPurchasePayments"></div>
        <template id="azrPayRowTpl">
            <div class="azr-pay-split-row">
                <select class="azr-select" name="payment_method[]" style="max-width:180px;">
                    <option value="cash">Tunai</option>
                    <option value="transfer">Transfer</option>
                    <option value="debit">Kartu Debit</option>
                    <option value="credit">Kartu Kredit</option>
                    <option value="ewallet">E-Wallet</option>
                    <option value="qris">QRIS</option>
                    <option value="other">Lainnya</option>
                </select>
                <input class="azr-input" type="number" name="payment_amount[]" min="0" step="any" placeholder="Jumlah dibayar" value="0">
                <button type="button" class="azr-cart-item-remove" data-role="remove-pay">&times;</button>
            </div>
        </template>
        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" id="azrAddPayRow">+ Tambah Metode Pembayaran</button>
    </div>

    <div class="azr-card">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="azr-form-group">
                <label class="azr-label">Diskon Total (Rp)</label>
                <input class="azr-input" type="number" name="discount_amount" min="0" step="any" value="0">
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Catatan</label>
                <input class="azr-input" type="text" name="note" maxlength="255">
            </div>
        </div>
        <div class="azr-modal-actions" style="justify-content:flex-start;">
            <button type="submit" class="azr-btn azr-btn-primary">Simpan Pembelian</button>
            <a href="/purchases/index.php" class="azr-btn azr-btn-outline">Batal</a>
        </div>
    </div>
</form>

<script src="/assets/js/purchases-form.js"></script>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
