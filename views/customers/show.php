<?php
use App\Helpers\Response;

$pageTitle = 'Detail Pelanggan';
$activeMenu = 'customers';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Pelanggan', 'url' => '/customers/index.php'],
    ['label' => $customer['name']],
];
$typeLabel = ['retail' => 'Retail', 'member' => 'Member', 'wholesale' => 'Grosir', 'corporate' => 'Korporat'];

require __DIR__ . '/../layouts/main_top.php';
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
    <div class="azr-card">
        <div class="azr-card-header"><h2 class="azr-card-title"><?= Response::e($customer['name']) ?></h2></div>
        <table class="azr-table">
            <tr><td style="color:var(--azr-gray-600);">Kode</td><td><?= Response::e($customer['code']) ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Telepon</td><td><?= Response::e($customer['phone'] ?: '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Email</td><td><?= Response::e($customer['email'] ?: '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Alamat</td><td><?= nl2br(Response::e($customer['address'] ?: '-')) ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">NPWP</td><td><?= Response::e($customer['npwp'] ?: '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">NIK</td><td><?= Response::e($customer['nik'] ?: '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Nama Legal</td><td><?= Response::e($customer['legal_name'] ?: '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Status Perpajakan</td><td><?= $customer['tax_status'] === 'pkp' ? 'PKP' : ($customer['tax_status'] === 'non_pkp' ? 'Non-PKP' : '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Alamat Pajak</td><td><?= Response::e($customer['tax_address'] ?: '-') ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Tipe</td><td><?= $typeLabel[$customer['type']] ?? Response::e($customer['type']) ?></td></tr>
            <tr><td style="color:var(--azr-gray-600);">Status</td><td>
                <span class="azr-badge <?= $customer['status'] === 'active' ? 'azr-badge-active' : 'azr-badge-inactive' ?>">
                    <?= $customer['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                </span>
            </td></tr>
        </table>
    </div>

    <div class="azr-stats-grid" style="grid-template-columns:1fr;gap:14px;">
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#128179;</div>
            <div class="azr-stat-label">Total Transaksi</div>
            <div class="azr-stat-value"><?= (int) $history['total_transactions'] ?></div>
        </div>
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#128181;</div>
            <div class="azr-stat-label">Total Belanja</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $history['total_spend'], 0, ',', '.') ?></div>
        </div>
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#128197;</div>
            <div class="azr-stat-label">Transaksi Terakhir</div>
            <div class="azr-stat-value" style="font-size:1rem;"><?= Response::e($history['last_transaction_at'] ?: '-') ?></div>
        </div>
    </div>
</div>

<div style="margin-top:18px;">
    <a href="/customers/index.php" class="azr-btn azr-btn-outline">&larr; Kembali ke Daftar Pelanggan</a>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
