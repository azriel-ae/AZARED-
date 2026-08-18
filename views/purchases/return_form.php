<?php
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Retur Pembelian';
$activeMenu = 'purchases';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Pembelian', 'url' => '/purchases/index.php'],
    ['label' => $purchase['purchase_no'], 'url' => '/purchases/show.php?id=' . (int) $purchase['id']],
    ['label' => 'Retur'],
];

require __DIR__ . '/../layouts/main_top.php';
?>

<?php if (isset($_GET['error'])): ?>
<div class="azr-alert azr-alert-error" data-azr-autodismiss>
    <?= $_GET['error'] === 'empty' ? 'Pilih minimal satu item dengan jumlah retur yang valid.' : Response::e($_GET['error']) ?>
</div>
<?php endif; ?>

<form action="/purchases/store-return.php?id=<?= (int) $purchase['id'] ?>" method="post">
    <?= Csrf::field() ?>
    <div class="azr-card">
        <div class="azr-card-header">
            <h2 class="azr-card-title">Retur Pembelian: <?= Response::e($purchase['purchase_no']) ?></h2>
        </div>
        <div class="azr-table-wrap">
            <table class="azr-table">
                <thead><tr><th>Produk</th><th>Qty Dibeli</th><th>Sudah Diretur</th><th>Sisa</th><th style="width:140px;">Qty Retur</th></tr></thead>
                <tbody>
                <?php foreach ($purchase['items'] as $it): ?>
                    <?php $remaining = (float) $it['qty'] - (float) $it['returned_qty']; ?>
                    <tr>
                        <td><?= Response::e($it['product_name']) ?><br><span style="color:var(--azr-gray-600);font-size:0.78rem;"><?= Response::e($it['sku']) ?></span></td>
                        <td><?= rtrim(rtrim(number_format((float) $it['qty'], 3, ',', '.'), '0'), ',') ?></td>
                        <td><?= rtrim(rtrim(number_format((float) $it['returned_qty'], 3, ',', '.'), '0'), ',') ?></td>
                        <td><?= rtrim(rtrim(number_format($remaining, 3, ',', '.'), '0'), ',') ?></td>
                        <td>
                            <input class="azr-input" type="number" name="items[<?= (int) $it['id'] ?>]" min="0" max="<?= $remaining ?>" step="any"
                                   value="0" <?= $remaining <= 0 ? 'disabled' : '' ?>>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="azr-form-group" style="margin-top:14px;">
            <label class="azr-label">Alasan Retur</label>
            <textarea class="azr-textarea" name="reason" placeholder="mis. Barang rusak / tidak sesuai pesanan"></textarea>
        </div>

        <div class="azr-modal-actions" style="justify-content:flex-start;">
            <button type="submit" class="azr-btn azr-btn-primary">Proses Retur</button>
            <a href="/purchases/show.php?id=<?= (int) $purchase['id'] ?>" class="azr-btn azr-btn-outline">Batal</a>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
