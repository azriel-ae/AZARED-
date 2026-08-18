<?php
use App\Auth\AuthService;
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Pengaturan';
$activeMenu = 'settings';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Pengaturan'],
];
$canEdit = AuthService::hasPermission('settings.edit');

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-card" style="max-width:720px;">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Pengaturan Umum</h2>
    </div>

    <form action="/settings/update.php" method="post" data-azr-ajax>
        <?= Csrf::field() ?>
        <div class="azr-form-group">
            <label class="azr-label">Nama Badan Usaha</label>
            <input class="azr-input" type="text" name="company_legal_name" maxlength="150"
                   value="<?= Response::e($settings['company_legal_name'] ?? '') ?>"
                   placeholder="mis. PT Azared Retail Indonesia" <?= $canEdit ? '' : 'readonly' ?>>
            <p class="azr-help-text">Ditampilkan pada header invoice A4. Kosongkan untuk memakai default "AZARED".</p>
            <p class="azr-error-text" data-azr-error="company_legal_name"></p>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Catatan Kaki Struk</label>
            <input class="azr-input" type="text" name="receipt_footer_note" maxlength="255"
                   value="<?= Response::e($settings['receipt_footer_note'] ?? '') ?>"
                   placeholder="mis. Barang yang sudah dibeli tidak dapat ditukar" <?= $canEdit ? '' : 'readonly' ?>>
            <p class="azr-help-text">Ditampilkan di bagian bawah struk kasir (thermal) dan invoice A4.</p>
            <p class="azr-error-text" data-azr-error="receipt_footer_note"></p>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Catatan Peringatan Stok Menipis</label>
            <input class="azr-input" type="text" name="low_stock_alert_note" maxlength="255"
                   value="<?= Response::e($settings['low_stock_alert_note'] ?? '') ?>"
                   placeholder="mis. Segera hubungi supplier untuk restock" <?= $canEdit ? '' : 'readonly' ?>>
            <p class="azr-help-text">Ditampilkan di widget "Stok Menipis" pada dashboard.</p>
            <p class="azr-error-text" data-azr-error="low_stock_alert_note"></p>
        </div>
        <?php if ($canEdit): ?>
        <div class="azr-modal-actions" style="justify-content:flex-start;">
            <button type="submit" class="azr-btn azr-btn-primary">Simpan Pengaturan</button>
        </div>
        <?php else: ?>
        <p class="azr-help-text">Anda hanya memiliki akses lihat. Hubungi Admin untuk mengubah pengaturan ini.</p>
        <?php endif; ?>
    </form>
</div>

<div class="azr-card" style="max-width:720px;margin-top:18px;">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Pengaturan Lainnya</h2>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;">
        <?php if (AuthService::hasPermission('stores.manage')): ?>
        <a href="/settings/stores.php" class="azr-btn azr-btn-outline" style="justify-content:flex-start;">
            &#127970; Toko / Cabang &mdash; kelola daftar toko, alamat, dan NPWP toko
        </a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('roles.manage')): ?>
        <a href="/roles" class="azr-btn azr-btn-outline" style="justify-content:flex-start;">
            &#128274; Role & Akses &mdash; kelola role dan izin pengguna
        </a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('tax.settings')): ?>
        <a href="/tax/settings" class="azr-btn azr-btn-outline" style="justify-content:flex-start;">
            &#127974; Pengaturan Pajak &mdash; jenis pajak, tarif, dan periode buku
        </a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('users.view')): ?>
        <a href="/users" class="azr-btn azr-btn-outline" style="justify-content:flex-start;">
            &#128101; Pengguna &mdash; kelola akun dan akses toko pengguna
        </a>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
