<?php
use App\Helpers\Csrf;
use App\Helpers\Response;

$isEdit = isset($old['id']);
$pageTitle = $isEdit ? 'Edit Produk' : 'Tambah Produk';
$activeMenu = 'products';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Produk', 'url' => '/products/index.php'],
    ['label' => $isEdit ? 'Edit' : 'Tambah'],
];

require __DIR__ . '/../layouts/main_top.php';

function azrOld(array $old, string $key, $default = '')
{
    return Response::e((string) ($old[$key] ?? $default));
}
?>

<div class="azr-card" style="max-width:920px;">
    <div class="azr-card-header">
        <h2 class="azr-card-title"><?= $isEdit ? 'Edit Produk' : 'Tambah Produk Baru' ?></h2>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="azr-alert azr-alert-error">Periksa kembali data yang Anda masukkan.</div>
    <?php endif; ?>

    <form action="<?= $isEdit ? '/products/update.php?id=' . (int) $old['id'] : '/products/store.php' ?>"
          method="post" enctype="multipart/form-data">
        <?= Csrf::field() ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="azr-form-group">
                <label class="azr-label">Nama Produk *</label>
                <input class="azr-input" type="text" name="name" value="<?= azrOld($old, 'name') ?>" required maxlength="180">
                <?php if (!empty($errors['name'])): ?><p class="azr-error-text"><?= Response::e($errors['name']) ?></p><?php endif; ?>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">SKU</label>
                <input class="azr-input" type="text" name="sku" value="<?= azrOld($old, 'sku') ?>" maxlength="50" placeholder="Kosongkan untuk otomatis">
                <?php if (!empty($errors['sku'])): ?><p class="azr-error-text"><?= Response::e($errors['sku']) ?></p><?php endif; ?>
            </div>

            <div class="azr-form-group">
                <label class="azr-label">Barcode</label>
                <input class="azr-input" type="text" name="barcode" value="<?= azrOld($old, 'barcode') ?>" maxlength="80">
                <?php if (!empty($errors['barcode'])): ?><p class="azr-error-text"><?= Response::e($errors['barcode']) ?></p><?php endif; ?>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Kategori</label>
                <select class="azr-select" name="category_id">
                    <option value="">- Tanpa Kategori -</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($old['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= Response::e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="azr-form-group">
                <label class="azr-label">Satuan</label>
                <select class="azr-select" name="unit_id">
                    <option value="">- Pilih Satuan -</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= (int) ($old['unit_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
                            <?= Response::e($u['name']) ?> (<?= Response::e($u['symbol']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Status</label>
                <select class="azr-select" name="status">
                    <option value="active" <?= ($old['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>

            <div class="azr-form-group">
                <label class="azr-label">Harga Beli (Rp) *</label>
                <input class="azr-input" type="number" step="0.01" min="0" name="cost_price" value="<?= azrOld($old, 'cost_price', 0) ?>" required>
                <?php if (!empty($errors['cost_price'])): ?><p class="azr-error-text"><?= Response::e($errors['cost_price']) ?></p><?php endif; ?>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Harga Jual (Rp) *</label>
                <input class="azr-input" type="number" step="0.01" min="0" name="sell_price" value="<?= azrOld($old, 'sell_price', 0) ?>" required>
                <?php if (!empty($errors['sell_price'])): ?><p class="azr-error-text"><?= Response::e($errors['sell_price']) ?></p><?php endif; ?>
            </div>

            <div class="azr-form-group">
                <label class="azr-label">Harga Grosir (Rp)</label>
                <input class="azr-input" type="number" step="0.01" min="0" name="wholesale_price" value="<?= azrOld($old, 'wholesale_price') ?>" placeholder="Opsional">
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Min. Qty Grosir</label>
                <input class="azr-input" type="number" step="0.001" min="0" name="wholesale_min_qty" value="<?= azrOld($old, 'wholesale_min_qty') ?>" placeholder="mis. 12">
            </div>

            <?php if (!$isEdit): ?>
            <div class="azr-form-group">
                <label class="azr-label">Stok Awal</label>
                <input class="azr-input" type="number" step="0.001" min="0" name="stock" value="<?= azrOld($old, 'stock', 0) ?>">
            </div>
            <?php else: ?>
            <div class="azr-form-group">
                <label class="azr-label">Stok Saat Ini</label>
                <input class="azr-input" type="text" value="<?= azrOld($old, 'stock', 0) ?> <?= azrOld($old, 'unit_symbol', '') ?>" disabled>
                <p class="azr-help-text">Gunakan menu <a href="/inventory/index.php">Stok / Inventory</a> untuk menyesuaikan stok.</p>
            </div>
            <?php endif; ?>

            <div class="azr-form-group">
                <label class="azr-label">Stok Minimum</label>
                <input class="azr-input" type="number" step="0.001" min="0" name="min_stock" value="<?= azrOld($old, 'min_stock', 0) ?>">
            </div>

            <div class="azr-form-group">
                <label class="azr-label">Jenis Pajak</label>
                <select class="azr-select" name="tax_id" id="azrProductTaxSelect">
                    <option value="">- Tanpa Pajak Terdaftar -</option>
                    <?php foreach ($taxes as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" data-rate="<?= (float) $t['current_rate'] ?>" <?= (int) ($old['tax_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= Response::e($t['name']) ?> (<?= (float) $t['current_rate'] ?>%)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="azr-help-text">Opsional - untuk mengelompokkan produk pada laporan pajak. Tarif tetap dari kolom "Pajak (%)" di bawah.</p>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Pajak (%)</label>
                <input class="azr-input" type="number" step="0.01" min="0" max="100" name="tax_percent" id="azrProductTaxPercent" value="<?= azrOld($old, 'tax_percent', 0) ?>">
                <?php if (!empty($errors['tax_percent'])): ?><p class="azr-error-text"><?= Response::e($errors['tax_percent']) ?></p><?php endif; ?>
            </div>
            <div class="azr-form-group" style="align-self:end;">
                <label class="azr-checkbox-row">
                    <input type="checkbox" name="tax_inclusive" value="1" <?= !empty($old['tax_inclusive']) ? 'checked' : '' ?>>
                    Harga sudah termasuk pajak (tax inclusive)
                </label>
            </div>
            <div class="azr-form-group" style="grid-column:1/-1;">
                <label class="azr-checkbox-row">
                    <input type="checkbox" name="allow_negative_stock" value="1" <?= !empty($old['allow_negative_stock']) ? 'checked' : '' ?>>
                    Izinkan stok minus untuk produk ini (mis. produk jasa / pre-order)
                </label>
            </div>

            <div class="azr-form-group" style="grid-column:1/-1;">
                <label class="azr-label">Gambar Produk</label>
                <?php if ($isEdit && !empty($old['image_path'])): ?>
                    <div style="margin-bottom:8px;"><img src="<?= Response::e($old['image_path']) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:8px;"></div>
                <?php endif; ?>
                <input class="azr-input" type="file" name="image" accept="image/png,image/jpeg,image/webp">
                <p class="azr-help-text">JPG/PNG/WebP, maksimal 2MB.</p>
            </div>

            <div class="azr-form-group" style="grid-column:1/-1;">
                <label class="azr-label">Deskripsi</label>
                <textarea class="azr-textarea" name="description"><?= azrOld($old, 'description') ?></textarea>
            </div>
        </div>

        <div class="azr-modal-actions" style="justify-content:flex-start;margin-top:20px;">
            <button type="submit" class="azr-btn azr-btn-primary">Simpan Produk</button>
            <a href="/products/index.php" class="azr-btn azr-btn-outline">Batal</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
