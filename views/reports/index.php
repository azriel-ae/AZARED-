<?php
use App\Auth\AuthService;
use App\Helpers\Response;

$pageTitle = 'Laporan';
$activeMenu = 'reports';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Laporan'],
];

$reportLinks = [
    ['perm' => 'reports.sales',     'url' => '/reports/sales',          'icon' => '&#128181;', 'title' => 'Laporan Penjualan',   'desc' => 'Rincian transaksi penjualan, retur, dan diskon per periode.'],
    ['perm' => 'reports.purchase',  'url' => '/reports/purchases',      'icon' => '&#128722;', 'title' => 'Laporan Pembelian',   'desc' => 'Rincian transaksi pembelian dan retur ke supplier.'],
    ['perm' => 'reports.inventory', 'url' => '/reports/inventory',      'icon' => '&#128230;', 'title' => 'Laporan Inventory',   'desc' => 'Nilai stok, stok masuk/keluar, dan produk stok menipis.'],
    ['perm' => 'reports.inventory', 'url' => '/reports/stock-movements.php', 'icon' => '&#128203;', 'title' => 'Riwayat Stock Movement', 'desc' => 'Log setiap pergerakan stok per produk.'],
    ['perm' => 'reports.finance',   'url' => '/reports/hpp',            'icon' => '&#129534;', 'title' => 'Laporan HPP',          'desc' => 'Harga Pokok Penjualan dirinci per produk.'],
    ['perm' => 'reports.finance',   'url' => '/finance/profit-loss',    'icon' => '&#128202;', 'title' => 'Laba Rugi',            'desc' => 'Ringkasan pendapatan, HPP, biaya, dan laba bersih.'],
    ['perm' => 'reports.finance',   'url' => '/finance/cash-flow',      'icon' => '&#128184;', 'title' => 'Cash Flow',            'desc' => 'Arus kas masuk dan keluar per periode.'],
    ['perm' => 'tax.report',        'url' => '/reports/tax',            'icon' => '&#127974;', 'title' => 'Laporan Pajak',        'desc' => 'Pajak keluaran dan masukan per periode.'],
];

require __DIR__ . '/../layouts/main_top.php';
?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
    <?php foreach ($reportLinks as $link): ?>
        <?php if (!AuthService::hasPermission($link['perm'])): continue; endif; ?>
        <a href="<?= Response::e($link['url']) ?>" class="azr-card" style="display:block;text-decoration:none;color:inherit;transition:box-shadow .15s;">
            <div style="font-size:1.6rem;margin-bottom:8px;"><?= $link['icon'] ?></div>
            <div style="font-weight:800;color:var(--azr-blue-900);margin-bottom:4px;"><?= Response::e($link['title']) ?></div>
            <div style="color:var(--azr-gray-600);font-size:0.85rem;"><?= Response::e($link['desc']) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
