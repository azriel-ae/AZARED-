<?php
use App\Auth\AuthService;
use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Models\Tax;

$pageTitle = 'Pengaturan Pajak';
$activeMenu = 'tax';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Perpajakan', 'url' => '/tax'],
    ['label' => 'Pengaturan'],
];
$typeLabel = ['ppn' => 'PPN', 'pph' => 'PPh', 'other' => 'Lainnya'];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-alert azr-alert-info" style="margin-bottom:18px;">
    Tarif pajak <strong>tidak pernah ditimpa</strong> - setiap perubahan tarif membuat catatan riwayat baru, sehingga transaksi lama tetap memakai tarif yang berlaku saat transaksi itu terjadi.
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Jenis Pajak</h2>
        <div style="display:flex;gap:8px;">
            <a href="/tax" class="azr-btn azr-btn-outline">&larr; Kembali</a>
            <button type="button" class="azr-btn azr-btn-primary" data-azr-modal-open="taxCreateModal">+ Tambah Jenis Pajak</button>
        </div>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead><tr><th>Nama</th><th>Kode</th><th>Jenis</th><th>Tarif Aktif</th><th>Inclusive/Exclusive</th><th>Status</th><th style="text-align:right;">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($taxes as $t): ?>
                <tr>
                    <td><?= Response::e($t['name']) ?></td>
                    <td><span class="azr-badge azr-badge-info"><?= Response::e($t['code']) ?></span></td>
                    <td><?= $typeLabel[$t['tax_type']] ?? Response::e($t['tax_type']) ?></td>
                    <td style="font-weight:700;"><?= $t['current_rate'] !== null ? number_format((float) $t['current_rate'], 2) . '%' : '-' ?></td>
                    <td><?= $t['tax_inclusive'] ? 'Inclusive' : 'Exclusive' ?></td>
                    <td>
                        <span class="azr-badge <?= $t['status'] === 'active' ? 'azr-badge-active' : 'azr-badge-inactive' ?>">
                            <?= $t['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" data-azr-modal-open="taxRateModal<?= (int) $t['id'] ?>">Riwayat Tarif</button>
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm" data-azr-modal-open="taxEditModal<?= (int) $t['id'] ?>">Edit</button>
                        <?php if ($t['status'] === 'active'): ?>
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm"
                                data-azr-ajax-action="/tax/deactivate-tax.php?id=<?= (int) $t['id'] ?>"
                                data-azr-confirm="Nonaktifkan pajak '<?= Response::e($t['name']) ?>'? Pajak nonaktif tidak bisa dipilih pada produk/transaksi baru.">Nonaktifkan</button>
                        <?php endif; ?>
                        <button type="button" class="azr-btn azr-btn-danger azr-btn-sm"
                                data-azr-ajax-action="/tax/destroy-tax.php?id=<?= (int) $t['id'] ?>"
                                data-azr-confirm="Hapus jenis pajak '<?= Response::e($t['name']) ?>'? Hanya dapat dihapus jika belum pernah digunakan.">Hapus</button>
                    </td>
                </tr>

                <!-- Edit modal -->
                <div class="azr-modal-backdrop" id="taxEditModal<?= (int) $t['id'] ?>">
                    <div class="azr-modal">
                        <h3 class="azr-modal-title">Edit Jenis Pajak</h3>
                        <form action="/tax/update-tax.php?id=<?= (int) $t['id'] ?>" method="post" data-azr-ajax>
                            <?= Csrf::field() ?>
                            <div class="azr-form-group">
                                <label class="azr-label">Nama Pajak</label>
                                <input class="azr-input" type="text" name="name" value="<?= Response::e($t['name']) ?>" required maxlength="100">
                                <p class="azr-error-text" data-azr-error="name"></p>
                            </div>
                            <div class="azr-form-group">
                                <label class="azr-label">Kode Pajak</label>
                                <input class="azr-input" type="text" name="code" value="<?= Response::e($t['code']) ?>" required maxlength="30" style="text-transform:uppercase;">
                                <p class="azr-error-text" data-azr-error="code"></p>
                            </div>
                            <div class="azr-form-group">
                                <label class="azr-label">Jenis Pajak</label>
                                <select class="azr-select" name="tax_type">
                                    <?php foreach ($typeLabel as $val => $label): ?>
                                        <option value="<?= $val ?>" <?= $t['tax_type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="azr-form-group">
                                <label class="azr-checkbox-row">
                                    <input type="checkbox" name="tax_inclusive" value="1" <?= $t['tax_inclusive'] ? 'checked' : '' ?>>
                                    Tax Inclusive (harga produk sudah termasuk pajak ini)
                                </label>
                            </div>
                            <div class="azr-form-group">
                                <label class="azr-label">Status</label>
                                <select class="azr-select" name="status">
                                    <option value="active" <?= $t['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="inactive" <?= $t['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                            </div>
                            <div class="azr-modal-actions">
                                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                                <button type="submit" class="azr-btn azr-btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Rate history + add-rate modal -->
                <div class="azr-modal-backdrop" id="taxRateModal<?= (int) $t['id'] ?>">
                    <div class="azr-modal" style="max-width:480px;">
                        <h3 class="azr-modal-title">Riwayat Tarif: <?= Response::e($t['name']) ?></h3>

                        <?php if (AuthService::hasPermission('tax.settings')): ?>
                        <form action="/tax/add-rate.php?id=<?= (int) $t['id'] ?>" method="post" data-azr-ajax style="margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid var(--azr-gray-300);">
                            <?= Csrf::field() ?>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <div class="azr-form-group">
                                    <label class="azr-label">Tarif Baru (%)</label>
                                    <input class="azr-input" type="number" step="0.001" min="0" max="100" name="rate" required>
                                    <p class="azr-error-text" data-azr-error="rate"></p>
                                </div>
                                <div class="azr-form-group">
                                    <label class="azr-label">Berlaku Mulai</label>
                                    <input class="azr-input" type="date" name="effective_from" value="<?= date('Y-m-d') ?>" required>
                                    <p class="azr-error-text" data-azr-error="effective_from"></p>
                                </div>
                            </div>
                            <button type="submit" class="azr-btn azr-btn-primary azr-btn-sm">Simpan Tarif Baru</button>
                        </form>
                        <?php endif; ?>

                        <table class="azr-table">
                            <thead><tr><th>Tarif</th><th>Berlaku Dari</th><th>Berlaku Sampai</th></tr></thead>
                            <tbody>
                            <?php foreach ($rateHistoryByTax[(int) $t['id']] ?? [] as $rh): ?>
                                <tr>
                                    <td style="font-weight:700;"><?= number_format((float) $rh['rate'], 2) ?>%</td>
                                    <td><?= Response::e($rh['effective_from']) ?></td>
                                    <td><?= $rh['effective_to'] ? Response::e($rh['effective_to']) : '<span class="azr-badge azr-badge-active">Aktif</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="azr-modal-actions">
                            <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Tutup</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($taxes)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--azr-gray-600);">Belum ada jenis pajak.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="azr-modal-backdrop" id="taxCreateModal">
    <div class="azr-modal">
        <h3 class="azr-modal-title">Tambah Jenis Pajak</h3>
        <form action="/tax/store-tax.php" method="post" data-azr-ajax>
            <?= Csrf::field() ?>
            <div class="azr-form-group">
                <label class="azr-label">Nama Pajak</label>
                <input class="azr-input" type="text" name="name" required maxlength="100" placeholder="mis. PPN">
                <p class="azr-error-text" data-azr-error="name"></p>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Kode Pajak</label>
                <input class="azr-input" type="text" name="code" required maxlength="30" placeholder="mis. PPN11" style="text-transform:uppercase;">
                <p class="azr-error-text" data-azr-error="code"></p>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div class="azr-form-group">
                    <label class="azr-label">Jenis Pajak</label>
                    <select class="azr-select" name="tax_type">
                        <?php foreach ($typeLabel as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="azr-form-group">
                    <label class="azr-label">Tarif Awal (%)</label>
                    <input class="azr-input" type="number" step="0.001" min="0" max="100" name="rate" required>
                    <p class="azr-error-text" data-azr-error="rate"></p>
                </div>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Berlaku Mulai</label>
                <input class="azr-input" type="date" name="effective_from" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="azr-form-group">
                <label class="azr-checkbox-row">
                    <input type="checkbox" name="tax_inclusive" value="1">
                    Tax Inclusive (harga produk sudah termasuk pajak ini)
                </label>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Status</label>
                <select class="azr-select" name="status">
                    <option value="active" selected>Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </div>
            <div class="azr-modal-actions">
                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                <button type="submit" class="azr-btn azr-btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
