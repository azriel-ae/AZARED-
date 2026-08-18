<?php
use App\Auth\AuthService;
use App\Helpers\Response;

$pageTitle = 'Perpajakan';
$activeMenu = 'tax';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Perpajakan']];

require __DIR__ . '/../layouts/main_top.php';

$selisih = $summary['output']['tax'] - $summary['input']['tax'];
?>

<div class="azr-alert azr-alert-info" style="margin-bottom:18px;">
    <strong>Catatan:</strong> Angka pada halaman ini adalah hasil pencatatan internal AZARED berdasarkan transaksi penjualan &amp; pembelian yang tersimpan di sistem. Ini <strong>bukan</strong> laporan resmi ke otoritas pajak dan tidak terhubung otomatis dengan sistem DJP/e-Faktur. Gunakan sebagai alat bantu, lalu verifikasi kembali sebelum pelaporan resmi.
</div>

<div class="azr-filter-bar">
    <form method="get" action="/tax" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Dari Tanggal</label>
            <input class="azr-input" type="date" name="date_from" value="<?= Response::e($_GET['date_from'] ?? date('Y-m-01')) ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Sampai Tanggal</label>
            <input class="azr-input" type="date" name="date_to" value="<?= Response::e($_GET['date_to'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Toko</label>
            <select class="azr-select" name="store_id">
                <option value="">Semua Toko</option>
                <?php foreach ($stores as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($_GET['store_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= Response::e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Jenis Pajak</label>
            <select class="azr-select" name="tax_id">
                <option value="">Semua</option>
                <?php foreach ($taxes as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= (int) ($_GET['tax_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= Response::e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="azr-btn azr-btn-primary">Terapkan</button>
    </form>
</div>

<div class="azr-stats-grid">
    <div class="azr-stat-card">
        <div class="azr-stat-icon">&#128181;</div>
        <div class="azr-stat-label">Total Penjualan Kena Pajak (DPP)</div>
        <div class="azr-stat-value">Rp <?= number_format($summary['output']['taxable'], 0, ',', '.') ?></div>
    </div>
    <div class="azr-stat-card">
        <div class="azr-stat-icon">&#11015;&#65039;</div>
        <div class="azr-stat-label">Total Pajak Keluaran</div>
        <div class="azr-stat-value">Rp <?= number_format($summary['output']['tax'], 0, ',', '.') ?></div>
    </div>
    <div class="azr-stat-card">
        <div class="azr-stat-icon">&#128722;</div>
        <div class="azr-stat-label">Total Pembelian Kena Pajak (DPP)</div>
        <div class="azr-stat-value">Rp <?= number_format($summary['input']['taxable'], 0, ',', '.') ?></div>
    </div>
    <div class="azr-stat-card">
        <div class="azr-stat-icon">&#11014;&#65039;</div>
        <div class="azr-stat-label">Total Pajak Masukan</div>
        <div class="azr-stat-value">Rp <?= number_format($summary['input']['tax'], 0, ',', '.') ?></div>
    </div>
    <div class="azr-stat-card <?= $selisih >= 0 ? 'azr-stat-warn' : 'azr-stat-good' ?>">
        <div class="azr-stat-icon">&#9878;&#65039;</div>
        <div class="azr-stat-label">Estimasi Selisih Pajak (Keluaran - Masukan)</div>
        <div class="azr-stat-value">Rp <?= number_format($selisih, 0, ',', '.') ?></div>
        <div style="font-size:0.72rem;color:var(--azr-gray-600);margin-top:2px;">
            <?= $selisih >= 0 ? 'Estimasi kurang bayar (pajak keluaran lebih besar)' : 'Estimasi lebih bayar (pajak masukan lebih besar)' ?>
        </div>
    </div>
</div>

<div class="azr-card" style="margin-top:4px;">
    <div class="azr-card-header"><h2 class="azr-card-title">Navigasi Modul Pajak</h2></div>
    <div class="azr-shortcuts">
        <a href="/tax/output" class="azr-shortcut"><div class="azr-shortcut-icon">&#128184;</div>Pajak Keluaran</a>
        <a href="/tax/input" class="azr-shortcut"><div class="azr-shortcut-icon">&#128176;</div>Pajak Masukan</a>
        <?php if (AuthService::hasPermission('tax.settings')): ?>
        <a href="/tax/settings" class="azr-shortcut"><div class="azr-shortcut-icon">&#9881;&#65039;</div>Pengaturan Pajak</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('tax.manage')): ?>
        <a href="/tax/periods" class="azr-shortcut"><div class="azr-shortcut-icon">&#128197;</div>Periode Pajak</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('tax.report')): ?>
        <a href="/reports/tax" class="azr-shortcut"><div class="azr-shortcut-icon">&#128202;</div>Laporan Pajak</a>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
