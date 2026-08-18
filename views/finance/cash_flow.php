<?php
use App\Helpers\Response;

$pageTitle = 'Cash Flow';
$activeMenu = 'finance';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Keuangan', 'url' => '/finance'],
    ['label' => 'Cash Flow'],
];

require __DIR__ . '/../layouts/main_top.php';

$formAction = '/finance/cash-flow';
require __DIR__ . '/_period_filter.php';
?>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px;">
    <div class="azr-card">
        <div class="azr-card-header"><h2 class="azr-card-title">Laporan Arus Kas</h2></div>

        <div class="azr-summary-row" style="font-size:1rem;padding:10px 0;border-bottom:2px solid var(--azr-gray-300);">
            <span style="font-weight:700;">Saldo Awal (Opening Balance)</span>
            <span style="font-weight:700;">Rp <?= number_format($report['opening_balance'], 0, ',', '.') ?></span>
        </div>

        <div style="margin-top:14px;">
            <div style="font-weight:800;color:var(--azr-green);margin-bottom:6px;">CASH IN</div>
            <div class="azr-summary-row"><span>Penjualan</span><span>Rp <?= number_format($report['cash_in']['sales'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row"><span>Retur Pembelian (Uang Kembali dari Supplier)</span><span>Rp <?= number_format($report['cash_in']['purchase_returns'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row"><span>Pemasukan Lainnya</span><span>Rp <?= number_format($report['cash_in']['other'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row" style="border-top:1px solid var(--azr-gray-300);font-weight:700;color:var(--azr-green);">
                <span>Total Cash In</span><span>Rp <?= number_format($report['cash_in']['total'], 0, ',', '.') ?></span>
            </div>
        </div>

        <div style="margin-top:18px;">
            <div style="font-weight:800;color:var(--azr-red);margin-bottom:6px;">CASH OUT</div>
            <div class="azr-summary-row"><span>Pembelian</span><span>Rp <?= number_format($report['cash_out']['purchases'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row"><span>Pengeluaran (Expense)</span><span>Rp <?= number_format($report['cash_out']['expenses'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row"><span>Retur Penjualan (Refund ke Customer)</span><span>Rp <?= number_format($report['cash_out']['sales_returns'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row"><span>Pengeluaran Lainnya</span><span>Rp <?= number_format($report['cash_out']['other'], 0, ',', '.') ?></span></div>
            <div class="azr-summary-row" style="border-top:1px solid var(--azr-gray-300);font-weight:700;color:var(--azr-red);">
                <span>Total Cash Out</span><span>Rp <?= number_format($report['cash_out']['total'], 0, ',', '.') ?></span>
            </div>
        </div>

        <div class="azr-summary-row total" style="margin-top:18px;font-size:1.1rem;">
            <span>Saldo Akhir (Closing Balance)</span>
            <span>Rp <?= number_format($report['closing_balance'], 0, ',', '.') ?></span>
        </div>
    </div>

    <div class="azr-card">
        <div class="azr-card-header"><h2 class="azr-card-title">Saldo Akun Saat Ini</h2></div>
        <?php foreach ($accounts as $acc): ?>
            <div class="azr-lowstock-item">
                <div>
                    <?= Response::e($acc['name']) ?><br>
                    <span style="font-size:0.76rem;color:var(--azr-gray-600);"><?= $acc['type'] === 'cash' ? 'Kas' : 'Bank' ?></span>
                </div>
                <span style="font-weight:700;">Rp <?= number_format((float) $acc['balance'], 0, ',', '.') ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
