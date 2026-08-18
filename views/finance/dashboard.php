<?php
use App\Auth\AuthService;
use App\Helpers\Response;

$pageTitle = 'Dashboard Keuangan';
$activeMenu = 'finance';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Keuangan']];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-card" style="margin-bottom:0;">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Ringkasan Omzet</h2>
        <div style="display:flex;gap:8px;">
            <?php if (AuthService::hasPermission('reports.finance')): ?>
            <a href="/finance/profit-loss" class="azr-btn azr-btn-outline azr-btn-sm">Laba Rugi</a>
            <a href="/finance/cash-flow" class="azr-btn azr-btn-outline azr-btn-sm">Cash Flow</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="azr-stats-grid">
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#128181;</div>
            <div class="azr-stat-label">Omzet Hari Ini</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $snapshot['omzet_today'], 0, ',', '.') ?></div>
        </div>
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#128200;</div>
            <div class="azr-stat-label">Omzet Minggu Ini</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $snapshot['omzet_week'], 0, ',', '.') ?></div>
        </div>
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#128197;</div>
            <div class="azr-stat-label">Omzet Bulan Ini</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $snapshot['omzet_month'], 0, ',', '.') ?></div>
        </div>
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#128198;</div>
            <div class="azr-stat-label">Omzet Tahun Ini</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $snapshot['omzet_year'], 0, ',', '.') ?></div>
        </div>
    </div>
</div>

<div class="azr-card" style="margin-bottom:0;margin-top:20px;">
    <div class="azr-card-header"><h2 class="azr-card-title">Profitabilitas (Bulan Ini)</h2></div>
    <div class="azr-stats-grid">
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#128722;</div>
            <div class="azr-stat-label">Total Pembelian</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $snapshot['purchases_month'], 0, ',', '.') ?></div>
        </div>
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#129518;</div>
            <div class="azr-stat-label">HPP</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $snapshot['hpp_month'], 0, ',', '.') ?></div>
        </div>
        <div class="azr-stat-card azr-stat-good">
            <div class="azr-stat-icon">&#128176;</div>
            <div class="azr-stat-label">Laba Kotor</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $snapshot['gross_profit_month'], 0, ',', '.') ?></div>
        </div>
        <div class="azr-stat-card <?= (float) $snapshot['net_profit_month'] >= 0 ? 'azr-stat-good' : 'azr-stat-danger' ?>">
            <div class="azr-stat-icon">&#128184;</div>
            <div class="azr-stat-label">Laba Bersih</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $snapshot['net_profit_month'], 0, ',', '.') ?></div>
        </div>
        <div class="azr-stat-card azr-stat-warn">
            <div class="azr-stat-icon">&#129534;</div>
            <div class="azr-stat-label">Total Pengeluaran</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $snapshot['expenses_month'], 0, ',', '.') ?></div>
        </div>
    </div>
</div>

<div class="azr-card" style="margin-bottom:0;margin-top:20px;">
    <div class="azr-card-header"><h2 class="azr-card-title">Posisi Kas (Hari Ini)</h2></div>
    <div class="azr-stats-grid">
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#11015;&#65039;</div>
            <div class="azr-stat-label">Cash In</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $snapshot['cash_in_today'], 0, ',', '.') ?></div>
        </div>
        <div class="azr-stat-card">
            <div class="azr-stat-icon">&#11014;&#65039;</div>
            <div class="azr-stat-label">Cash Out</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $snapshot['cash_out_today'], 0, ',', '.') ?></div>
        </div>
        <div class="azr-stat-card azr-stat-good">
            <div class="azr-stat-icon">&#128176;</div>
            <div class="azr-stat-label">Saldo Kas</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $snapshot['saldo_kas'], 0, ',', '.') ?></div>
        </div>
        <div class="azr-stat-card azr-stat-good">
            <div class="azr-stat-icon">&#127974;</div>
            <div class="azr-stat-label">Saldo Bank</div>
            <div class="azr-stat-value">Rp <?= number_format((float) $snapshot['saldo_bank'], 0, ',', '.') ?></div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:20px;">
    <div class="azr-card">
        <div class="azr-card-header"><h2 class="azr-card-title">Rincian Saldo per Akun</h2></div>
        <table class="azr-table">
            <thead><tr><th>Akun</th><th>Tipe</th><th style="text-align:right;">Saldo</th></tr></thead>
            <tbody>
            <?php foreach ($accounts as $acc): ?>
                <tr>
                    <td><?= Response::e($acc['name']) ?></td>
                    <td><span class="azr-badge azr-badge-info"><?= $acc['type'] === 'cash' ? 'Kas' : 'Bank' ?></span></td>
                    <td style="text-align:right;font-weight:700;">Rp <?= number_format((float) $acc['balance'], 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="azr-card">
        <div class="azr-card-header"><h2 class="azr-card-title">Info Tambahan</h2></div>
        <div class="azr-summary-row"><span>Nilai Inventory Saat Ini</span><span style="font-weight:700;">Rp <?= number_format((float) $snapshot['inventory_value'], 0, ',', '.') ?></span></div>
        <div class="azr-summary-row"><span>Produk Stok Menipis</span><span style="font-weight:700;color:var(--azr-amber);"><?= (int) $snapshot['low_stock_count'] ?></span></div>
        <?php if (AuthService::hasPermission('expenses.view')): ?>
        <a href="/expenses/index.php" class="azr-btn azr-btn-outline azr-btn-sm" style="margin-top:12px;">Kelola Pengeluaran &rarr;</a>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
