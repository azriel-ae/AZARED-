<?php
use App\Auth\AuthService;
use App\Helpers\Response;
use App\Models\User;

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
$breadcrumb = [['label' => 'Dashboard']];

$storeName = 'AZARED Pusat';
$storeAccess = User::storeAccess((int) AuthService::id());
foreach ($storeAccess as $sa) {
    if (!empty($sa['is_primary'])) { $storeName = $sa['name']; break; }
}
if ($storeName === 'AZARED Pusat' && !empty($storeAccess)) {
    $storeName = $storeAccess[0]['name'];
}

require __DIR__ . '/../layouts/main_top.php';

$payLabel = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'debit' => 'Kartu Debit', 'credit' => 'Kartu Kredit', 'ewallet' => 'E-Wallet', 'qris' => 'QRIS', 'other' => 'Lainnya'];
?>

<div class="azr-alert azr-alert-info" data-azr-autodismiss>
    Selamat datang, <strong><?= Response::e($_SESSION['full_name'] ?? '') ?></strong> &middot;
    Role: <strong><?= Response::e(implode(', ', $_SESSION['roles'] ?? [])) ?></strong> &middot;
    Toko: <strong><?= Response::e($storeName) ?></strong>
</div>

<div class="azr-stats-grid">
    <div class="azr-stat-card">
        <div class="azr-stat-icon">&#128181;</div>
        <div class="azr-stat-label">Total Penjualan Hari Ini</div>
        <div class="azr-stat-value">Rp <?= number_format((float) $stats['sales_today_total'], 0, ',', '.') ?></div>
    </div>
    <div class="azr-stat-card">
        <div class="azr-stat-icon">&#128179;</div>
        <div class="azr-stat-label">Jumlah Transaksi Hari Ini</div>
        <div class="azr-stat-value"><?= (int) $stats['sales_today_count'] ?></div>
    </div>
    <div class="azr-stat-card">
        <div class="azr-stat-icon">&#128200;</div>
        <div class="azr-stat-label">Penjualan Bulan Ini</div>
        <div class="azr-stat-value">Rp <?= number_format((float) $stats['sales_month_total'], 0, ',', '.') ?></div>
    </div>
    <div class="azr-stat-card <?= (int) $stats['low_stock_count'] > 0 ? 'azr-stat-warn' : 'azr-stat-good' ?>">
        <div class="azr-stat-icon">&#9888;</div>
        <div class="azr-stat-label">Produk Stok Menipis</div>
        <div class="azr-stat-value"><?= (int) $stats['low_stock_count'] ?></div>
    </div>
    <div class="azr-stat-card">
        <div class="azr-stat-icon">&#128722;</div>
        <div class="azr-stat-label">Pembelian Hari Ini</div>
        <div class="azr-stat-value">Rp <?= number_format((float) $stats['purchases_today_total'], 0, ',', '.') ?></div>
    </div>
    <div class="azr-stat-card azr-stat-good">
        <div class="azr-stat-icon">&#128176;</div>
        <div class="azr-stat-label">Estimasi Laba Kotor Hari Ini</div>
        <div class="azr-stat-value">Rp <?= number_format((float) $stats['gross_profit_today'], 0, ',', '.') ?></div>
    </div>
    <div class="azr-stat-card">
        <div class="azr-stat-icon">&#128100;</div>
        <div class="azr-stat-label">Total Pelanggan Aktif</div>
        <div class="azr-stat-value"><?= (int) $stats['customers_total'] ?></div>
    </div>
</div>

<div class="azr-card" style="margin-bottom:22px;">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Akses Cepat</h2>
    </div>
    <div class="azr-shortcuts">
        <?php if (AuthService::hasPermission('pos.access')): ?>
        <a href="/pos/index.php" class="azr-shortcut">
            <div class="azr-shortcut-icon">&#128179;</div>
            Buka Kasir
        </a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('products.view')): ?>
        <a href="/products/index.php" class="azr-shortcut">
            <div class="azr-shortcut-icon">&#128230;</div>
            Kelola Produk
        </a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('purchases.create')): ?>
        <a href="/purchases/create.php" class="azr-shortcut">
            <div class="azr-shortcut-icon">&#128722;</div>
            Pembelian Baru
        </a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('inventory.view')): ?>
        <a href="/inventory/index.php" class="azr-shortcut">
            <div class="azr-shortcut-icon">&#128200;</div>
            Lihat Stok
        </a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('users.view')): ?>
        <a href="/users/index.php" class="azr-shortcut">
            <div class="azr-shortcut-icon">&#128101;</div>
            Manajemen Pengguna
        </a>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px;">
    <div class="azr-card">
        <div class="azr-card-header"><h2 class="azr-card-title">Produk Terlaris (Bulan Ini)</h2></div>
        <?php if (empty($bestSellers)): ?>
            <p style="color:var(--azr-gray-600);font-size:0.86rem;">Belum ada data penjualan bulan ini.</p>
        <?php else: ?>
        <table class="azr-table">
            <thead><tr><th>Produk</th><th>Terjual</th><th>Omzet</th></tr></thead>
            <tbody>
            <?php foreach ($bestSellers as $b): ?>
                <tr>
                    <td><?= Response::e($b['name']) ?><br><span style="color:var(--azr-gray-600);font-size:0.78rem;"><?= Response::e($b['sku']) ?></span></td>
                    <td><?= rtrim(rtrim(number_format((float) $b['total_qty'], 3, ',', '.'), '0'), ',') ?></td>
                    <td>Rp <?= number_format((float) $b['total_revenue'], 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div>
        <div class="azr-card" style="margin-bottom:18px;">
            <div class="azr-card-header"><h2 class="azr-card-title">Stok Menipis</h2></div>
            <?php if (!empty($lowStockAlertNote)): ?>
                <p style="color:var(--azr-amber);font-size:0.8rem;margin-bottom:8px;"><?= Response::e($lowStockAlertNote) ?></p>
            <?php endif; ?>
            <?php if (empty($lowStockList)): ?>
                <p style="color:var(--azr-gray-600);font-size:0.86rem;">Semua stok produk dalam batas aman.</p>
            <?php else: ?>
                <?php foreach ($lowStockList as $ls): ?>
                    <div class="azr-lowstock-item">
                        <span><?= Response::e($ls['name']) ?></span>
                        <span style="font-weight:700;color:var(--azr-amber);"><?= rtrim(rtrim(number_format((float) $ls['stock'], 3, ',', '.'), '0'), ',') ?> <?= Response::e($ls['unit_symbol'] ?: '') ?></span>
                    </div>
                <?php endforeach; ?>
                <a href="/inventory/index.php" class="azr-btn-link" style="display:inline-block;margin-top:10px;">Lihat semua &rarr;</a>
            <?php endif; ?>
        </div>

        <div class="azr-card">
            <div class="azr-card-header"><h2 class="azr-card-title">Metode Pembayaran Hari Ini</h2></div>
            <?php if (empty($paymentBreakdown)): ?>
                <p style="color:var(--azr-gray-600);font-size:0.86rem;">Belum ada transaksi hari ini.</p>
            <?php else: ?>
                <?php foreach ($paymentBreakdown as $pb): ?>
                    <div class="azr-summary-row"><span><?= $payLabel[$pb['method']] ?? Response::e($pb['method']) ?></span><span>Rp <?= number_format((float) $pb['total'], 0, ',', '.') ?></span></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($_SESSION['must_change_password'])): ?>
<div class="azr-alert azr-alert-error" style="margin-top:18px;">
    Anda menggunakan password sementara/default. Segera hubungi administrator untuk mengganti password Anda.
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
