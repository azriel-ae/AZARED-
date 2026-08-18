<?php
use App\Helpers\Response;

$pageTitle = 'Detail Supplier';
$activeMenu = 'suppliers';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Supplier', 'url' => '/suppliers/index.php'],
    ['label' => $supplier['name']],
];

require __DIR__ . '/../layouts/main_top.php';
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
    <div class="azr-card">
        <div class="azr-card-header"><h2 class="azr-card-title"><?= Response::e($supplier['name']) ?></h2></div>
        <table class="azr-table">
            <tr><td style="color:var(--azr-gray-600);">Kode</td><td><?= Response::e($supplier['code']) ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Kontak</td><td><?= Response::e($supplier['contact_person'] ?: '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Telepon</td><td><?= Response::e($supplier['phone'] ?: '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Email</td><td><?= Response::e($supplier['email'] ?: '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Alamat</td><td><?= nl2br(Response::e($supplier['address'] ?: '-')) ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">NPWP</td><td><?= Response::e($supplier['npwp'] ?: '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">NIK</td><td><?= Response::e($supplier['nik'] ?: '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Nama Legal</td><td><?= Response::e($supplier['legal_name'] ?: '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Status Perpajakan</td><td><?= $supplier['tax_status'] === 'pkp' ? 'PKP' : ($supplier['tax_status'] === 'non_pkp' ? 'Non-PKP' : '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Alamat Pajak</td><td><?= Response::e($supplier['tax_address'] ?: '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Status</td><td>
                <span class="azr-badge <?= $supplier['status'] === 'active' ? 'azr-badge-active' : 'azr-badge-inactive' ?>">
                    <?= $supplier['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                </span>
            </td></tr>
        </table>
    </div>

    <div class="azr-stats-grid" style="grid-template-columns:1fr;gap:14px;">
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#128722;</div>
            <div class="azr-stat-label">Total Pembelian</div>
            <div class="azr-stat-value"><?= (int) $history['total_purchases'] ?></div>
        </div>
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#128181;</div>
            <div class="azr-stat-label">Total Nilai Pembelian</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $history['total_spend'], 0, ',', '.') ?></div>
        </div>
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#128197;</div>
            <div class="azr-stat-label">Pembelian Terakhir</div>
            <div class="azr-stat-value" style="font-size:1rem;"><?= Response::e($history['last_purchase_at'] ?: '-') ?></div>
        </div>
    </div>
</div>

<div style="margin-top:18px;">
    <a href="/suppliers/index.php" class="azr-btn azr-btn-outline">&larr; Kembali ke Daftar Supplier</a>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
