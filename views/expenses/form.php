<?php
use App\Helpers\Csrf;
use App\Helpers\Response;

$isEdit = isset($old['id']);
$pageTitle = $isEdit ? 'Edit Pengeluaran' : 'Tambah Pengeluaran';
$activeMenu = 'expenses';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Pengeluaran', 'url' => '/expenses/index.php'],
    ['label' => $isEdit ? 'Edit' : 'Tambah'],
];
$payLabel = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'debit' => 'Kartu Debit', 'credit' => 'Kartu Kredit', 'ewallet' => 'E-Wallet', 'qris' => 'QRIS', 'other' => 'Lainnya'];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-card" style="max-width:640px;">
    <div class="azr-card-header"><h2 class="azr-card-title"><?= $isEdit ? 'Edit Pengeluaran' : 'Tambah Pengeluaran' ?></h2></div>

    <?php if (!empty($errors)): ?>
    <div class="azr-alert azr-alert-error">Periksa kembali data yang Anda masukkan.</div>
    <?php endif; ?>

    <form action="<?= $isEdit ? '/expenses/update.php?id=' . (int) $old['id'] : '/expenses/store.php' ?>" method="post">
        <?= Csrf::field() ?>

        <div class="azr-form-group">
            <label class="azr-label">Kategori *</label>
            <select class="azr-select" name="category_id" required>
                <option value="">- Pilih Kategori -</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($old['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= Response::e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['category_id'])): ?><p class="azr-error-text"><?= Response::e($errors['category_id']) ?></p><?php endif; ?>
        </div>

        <div class="azr-form-group">
            <label class="azr-label">Deskripsi *</label>
            <input class="azr-input" type="text" name="description" value="<?= Response::e($old['description'] ?? '') ?>" required maxlength="255">
            <?php if (!empty($errors['description'])): ?><p class="azr-error-text"><?= Response::e($errors['description']) ?></p><?php endif; ?>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="azr-form-group">
                <label class="azr-label">Jumlah (Rp) *</label>
                <input class="azr-input" type="number" step="0.01" min="0.01" name="amount" value="<?= Response::e((string) ($old['amount'] ?? '')) ?>" required>
                <?php if (!empty($errors['amount'])): ?><p class="azr-error-text"><?= Response::e($errors['amount']) ?></p><?php endif; ?>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Tanggal *</label>
                <input class="azr-input" type="date" name="expense_date" value="<?= Response::e($old['expense_date'] ?? date('Y-m-d')) ?>" required>
                <?php if (!empty($errors['expense_date'])): ?><p class="azr-error-text"><?= Response::e($errors['expense_date']) ?></p><?php endif; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="azr-form-group">
                <label class="azr-label">Metode Pembayaran</label>
                <select class="azr-select" name="payment_method">
                    <?php foreach ($payLabel as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($old['payment_method'] ?? 'cash') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Toko/Cabang</label>
                <select class="azr-select" name="store_id">
                    <option value="">- Umum / Semua Toko -</option>
                    <?php foreach ($stores as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) ($old['store_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= Response::e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="azr-form-group">
            <label class="azr-label">Catatan</label>
            <textarea class="azr-textarea" name="notes"><?= Response::e($old['notes'] ?? '') ?></textarea>
        </div>

        <div class="azr-modal-actions" style="justify-content:flex-start;margin-top:10px;">
            <button type="submit" class="azr-btn azr-btn-primary">Simpan</button>
            <a href="/expenses/index.php" class="azr-btn azr-btn-outline">Batal</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
