<?php
/** @var array $s Pre-filled values (empty array for create) */
use App\Helpers\Response;

$s = $s ?? [];
?>
<div class="azr-form-group">
    <label class="azr-label">Nama Supplier *</label>
    <input class="azr-input" type="text" name="name" value="<?= Response::e($s['name'] ?? '') ?>" required maxlength="150">
    <p class="azr-error-text" data-azr-error="name"></p>
</div>
<div class="azr-form-group">
    <label class="azr-label">Nama Legal (untuk faktur pajak)</label>
    <input class="azr-input" type="text" name="legal_name" value="<?= Response::e($s['legal_name'] ?? '') ?>" maxlength="150" placeholder="Kosongkan jika sama dengan nama supplier">
</div>
<div class="azr-form-group">
    <label class="azr-label">Kontak Person</label>
    <input class="azr-input" type="text" name="contact_person" value="<?= Response::e($s['contact_person'] ?? '') ?>" maxlength="150">
</div>
<div class="azr-form-group">
    <label class="azr-label">No. Telepon</label>
    <input class="azr-input" type="text" name="phone" value="<?= Response::e($s['phone'] ?? '') ?>" maxlength="30">
</div>
<div class="azr-form-group">
    <label class="azr-label">Email</label>
    <input class="azr-input" type="email" name="email" value="<?= Response::e($s['email'] ?? '') ?>" maxlength="150">
    <p class="azr-error-text" data-azr-error="email"></p>
</div>
<div class="azr-form-group">
    <label class="azr-label">Alamat</label>
    <textarea class="azr-textarea" name="address"><?= Response::e($s['address'] ?? '') ?></textarea>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
    <div class="azr-form-group">
        <label class="azr-label">NPWP</label>
        <input class="azr-input" type="text" name="npwp" value="<?= Response::e($s['npwp'] ?? '') ?>" maxlength="30">
    </div>
    <div class="azr-form-group">
        <label class="azr-label">NIK</label>
        <input class="azr-input" type="text" name="nik" value="<?= Response::e($s['nik'] ?? '') ?>" maxlength="20">
    </div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
    <div class="azr-form-group">
        <label class="azr-label">Status Perpajakan</label>
        <select class="azr-select" name="tax_status">
            <option value="" <?= empty($s['tax_status']) ? 'selected' : '' ?>>- Tidak Diketahui -</option>
            <option value="pkp" <?= ($s['tax_status'] ?? '') === 'pkp' ? 'selected' : '' ?>>PKP (Pengusaha Kena Pajak)</option>
            <option value="non_pkp" <?= ($s['tax_status'] ?? '') === 'non_pkp' ? 'selected' : '' ?>>Non-PKP</option>
        </select>
    </div>
    <div class="azr-form-group">
        <label class="azr-label">Alamat Pajak</label>
        <input class="azr-input" type="text" name="tax_address" value="<?= Response::e($s['tax_address'] ?? '') ?>" maxlength="255" placeholder="Kosongkan jika sama dengan alamat di atas">
    </div>
</div>
<div class="azr-form-group">
    <label class="azr-label">Status</label>
    <select class="azr-select" name="status">
        <option value="active" <?= ($s['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
        <option value="inactive" <?= ($s['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
    </select>
</div>
