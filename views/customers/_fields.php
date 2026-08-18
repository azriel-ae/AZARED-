<?php
/** @var array $c Pre-filled values (empty array for create) */
use App\Helpers\Response;

$c = $c ?? [];
?>
<div class="azr-form-group">
    <label class="azr-label">Nama Pelanggan *</label>
    <input class="azr-input" type="text" name="name" value="<?= Response::e($c['name'] ?? '') ?>" required maxlength="150">
    <p class="azr-error-text" data-azr-error="name"></p>
</div>
<div class="azr-form-group">
    <label class="azr-label">Nama Legal (untuk faktur pajak)</label>
    <input class="azr-input" type="text" name="legal_name" value="<?= Response::e($c['legal_name'] ?? '') ?>" maxlength="150" placeholder="Kosongkan jika sama dengan nama pelanggan">
</div>
<div class="azr-form-group">
    <label class="azr-label">No. Telepon</label>
    <input class="azr-input" type="text" name="phone" value="<?= Response::e($c['phone'] ?? '') ?>" maxlength="30">
</div>
<div class="azr-form-group">
    <label class="azr-label">Email</label>
    <input class="azr-input" type="email" name="email" value="<?= Response::e($c['email'] ?? '') ?>" maxlength="150">
    <p class="azr-error-text" data-azr-error="email"></p>
</div>
<div class="azr-form-group">
    <label class="azr-label">Alamat</label>
    <textarea class="azr-textarea" name="address"><?= Response::e($c['address'] ?? '') ?></textarea>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
    <div class="azr-form-group">
        <label class="azr-label">NPWP</label>
        <input class="azr-input" type="text" name="npwp" value="<?= Response::e($c['npwp'] ?? '') ?>" maxlength="30">
    </div>
    <div class="azr-form-group">
        <label class="azr-label">NIK</label>
        <input class="azr-input" type="text" name="nik" value="<?= Response::e($c['nik'] ?? '') ?>" maxlength="20">
    </div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
    <div class="azr-form-group">
        <label class="azr-label">Status Perpajakan</label>
        <select class="azr-select" name="tax_status">
            <option value="" <?= empty($c['tax_status']) ? 'selected' : '' ?>>- Tidak Diketahui -</option>
            <option value="pkp" <?= ($c['tax_status'] ?? '') === 'pkp' ? 'selected' : '' ?>>PKP (Pengusaha Kena Pajak)</option>
            <option value="non_pkp" <?= ($c['tax_status'] ?? '') === 'non_pkp' ? 'selected' : '' ?>>Non-PKP</option>
        </select>
    </div>
    <div class="azr-form-group">
        <label class="azr-label">Alamat Pajak</label>
        <input class="azr-input" type="text" name="tax_address" value="<?= Response::e($c['tax_address'] ?? '') ?>" maxlength="255" placeholder="Kosongkan jika sama dengan alamat di atas">
    </div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
    <div class="azr-form-group">
        <label class="azr-label">Tipe Pelanggan</label>
        <select class="azr-select" name="type">
            <?php foreach (['retail' => 'Retail', 'member' => 'Member', 'wholesale' => 'Grosir', 'corporate' => 'Korporat'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($c['type'] ?? 'retail') === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="azr-form-group">
        <label class="azr-label">Status</label>
        <select class="azr-select" name="status">
            <option value="active" <?= ($c['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
            <option value="inactive" <?= ($c['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
        </select>
    </div>
</div>
