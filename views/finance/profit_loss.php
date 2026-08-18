<?php
use App\Helpers\Response;

$pageTitle = 'Laba Rugi';
$activeMenu = 'finance';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Keuangan', 'url' => '/finance'],
    ['label' => 'Laba Rugi'],
];

require __DIR__ . '/../layouts/main_top.php';

$formAction = '/finance/profit-loss';
require __DIR__ . '/_period_filter.php';
?>

<div class="azr-card" style="max-width:760px;margin:0 auto;">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Laporan Laba Rugi</h2>
        <span style="color:var(--azr-gray-600);font-size:0.82rem;"><?= (int) $report['transaction_count'] ?> transaksi</span>
    </div>

    <table class="azr-table" style="font-size:0.92rem;">
        <tbody>
        <tr><td colspan="2" style="font-weight:800;color:var(--azr-blue-900);padding-top:14px;">PENDAPATAN</td></tr>
        <tr><td style="padding-left:20px;">Penjualan (Gross)</td><td style="text-align:right;">Rp <?= number_format($report['gross_sales'], 0, ',', '.') ?></td></tr>
        <tr><td style="padding-left:20px;">Retur Penjualan</td><td style="text-align:right;color:var(--azr-red);">(Rp <?= number_format($report['sales_returns'], 0, ',', '.') ?>)</td></tr>
        <tr><td style="padding-left:20px;">Diskon</td><td style="text-align:right;color:var(--azr-red);">(Rp <?= number_format($report['discount'], 0, ',', '.') ?>)</td></tr>
        <tr style="border-top:2px solid var(--azr-gray-300);">
            <td style="font-weight:700;">Pendapatan Bersih</td>
            <td style="text-align:right;font-weight:700;">Rp <?= number_format($report['net_revenue'], 0, ',', '.') ?></td>
        </tr>

        <tr><td style="padding-top:14px;">HPP (Harga Pokok Penjualan)</td><td style="text-align:right;padding-top:14px;color:var(--azr-red);">(Rp <?= number_format($report['hpp'], 0, ',', '.') ?>)</td></tr>
        <tr style="border-top:2px solid var(--azr-gray-300);background:var(--azr-blue-50);">
            <td style="font-weight:800;color:var(--azr-blue-900);">LABA KOTOR</td>
            <td style="text-align:right;font-weight:800;color:var(--azr-blue-900);">
                Rp <?= number_format($report['gross_profit'], 0, ',', '.') ?>
                <div style="font-size:0.76rem;font-weight:400;color:var(--azr-gray-600);">Margin: <?= $report['gross_margin_pct'] ?>%</div>
            </td>
        </tr>

        <tr><td colspan="2" style="font-weight:800;color:var(--azr-blue-900);padding-top:14px;">BIAYA OPERASIONAL &amp; LAINNYA</td></tr>
        <?php if (empty($report['expense_breakdown'])): ?>
            <tr><td style="padding-left:20px;color:var(--azr-gray-600);">Tidak ada pengeluaran pada periode ini.</td><td></td></tr>
        <?php else: ?>
            <?php foreach ($report['expense_breakdown'] as $eb): ?>
                <tr><td style="padding-left:20px;"><?= Response::e($eb['name']) ?></td><td style="text-align:right;color:var(--azr-red);">(Rp <?= number_format((float) $eb['total'], 0, ',', '.') ?>)</td></tr>
            <?php endforeach; ?>
        <?php endif; ?>
        <tr style="border-top:1px solid var(--azr-gray-300);">
            <td style="font-weight:700;">Total Biaya</td>
            <td style="text-align:right;font-weight:700;color:var(--azr-red);">(Rp <?= number_format($report['expense_total'], 0, ',', '.') ?>)</td>
        </tr>

        <tr style="border-top:3px double var(--azr-blue-900);background:<?= $report['net_profit'] >= 0 ? 'var(--azr-blue-50)' : '#fdeaea' ?>;">
            <td style="font-weight:800;font-size:1.05rem;color:var(--azr-blue-900);padding:12px 8px;">LABA BERSIH</td>
            <td style="text-align:right;font-weight:800;font-size:1.05rem;color:<?= $report['net_profit'] >= 0 ? 'var(--azr-blue-900)' : 'var(--azr-red)' ?>;padding:12px 8px;">
                Rp <?= number_format($report['net_profit'], 0, ',', '.') ?>
                <div style="font-size:0.76rem;font-weight:400;color:var(--azr-gray-600);">Margin: <?= $report['net_margin_pct'] ?>%</div>
            </td>
        </tr>
        </tbody>
    </table>
</div>

<div class="azr-no-print" style="max-width:760px;margin:14px auto 0;">
    <a href="/reports/sales-export.php<?= !empty($_GET) ? '?' . Response::e(http_build_query($_GET)) : '' ?>" class="azr-btn azr-btn-outline azr-btn-sm">Export Detail Penjualan (CSV)</a>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
