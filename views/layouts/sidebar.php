<?php
/** @var string $activeMenu */
use App\Auth\AuthService;
use App\Helpers\Response;

$activeMenu = $activeMenu ?? '';
?>
<aside class="azr-sidebar" id="azrSidebar">
    <div class="azr-sidebar-header">
        <div class="azr-brand">
            <div class="azr-brand-mark">AZ</div>
            <span>AZARED</span>
        </div>
    </div>
    <nav class="azr-nav">
        <a href="/dashboard" class="azr-nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
            <span class="azr-nav-icon">&#9632;</span> Dashboard
        </a>

        <div class="azr-nav-group-label">Operasional</div>
        <?php if (AuthService::hasPermission('pos.access')): ?>
        <a href="/pos" class="azr-nav-link <?= $activeMenu === 'pos' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128179;</span> Kasir</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('products.view')): ?>
        <a href="/products" class="azr-nav-link <?= $activeMenu === 'products' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128230;</span> Produk</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('inventory.view')): ?>
        <a href="/inventory" class="azr-nav-link <?= $activeMenu === 'inventory' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128200;</span> Stok</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('sales.view')): ?>
        <a href="/sales" class="azr-nav-link <?= $activeMenu === 'sales' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128181;</span> Penjualan</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('purchases.view')): ?>
        <a href="/purchases" class="azr-nav-link <?= $activeMenu === 'purchases' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128722;</span> Pembelian</a>
        <?php endif; ?>

        <div class="azr-nav-group-label">Relasi</div>
        <?php if (AuthService::hasPermission('customers.view')): ?>
        <a href="/customers" class="azr-nav-link <?= $activeMenu === 'customers' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128100;</span> Pelanggan</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('suppliers.view')): ?>
        <a href="/suppliers" class="azr-nav-link <?= $activeMenu === 'suppliers' ? 'active' : '' ?>"><span class="azr-nav-icon">&#127970;</span> Supplier</a>
        <?php endif; ?>

        <div class="azr-nav-group-label">Keuangan</div>
        <?php if (AuthService::hasPermission('finance.view')): ?>
        <a href="/finance" class="azr-nav-link <?= $activeMenu === 'finance' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128176;</span> Dashboard Keuangan</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('expenses.view')): ?>
        <a href="/expenses/index.php" class="azr-nav-link <?= $activeMenu === 'expenses' ? 'active' : '' ?>"><span class="azr-nav-icon">&#129534;</span> Pengeluaran</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('reports.finance')): ?>
        <a href="/finance/profit-loss" class="azr-nav-link <?= $activeMenu === 'finance' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128202;</span> Laba Rugi</a>
        <a href="/finance/cash-flow" class="azr-nav-link <?= $activeMenu === 'finance' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128184;</span> Cash Flow</a>
        <?php endif; ?>

        <div class="azr-nav-group-label">Laporan</div>
        <?php if (AuthService::hasPermission('reports.view')): ?>
        <a href="/reports" class="azr-nav-link <?= $activeMenu === 'reports' && ($_SERVER['REQUEST_URI'] ?? '') === '/reports' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128203;</span> Semua Laporan</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('reports.sales')): ?>
        <a href="/reports/sales" class="azr-nav-link <?= $activeMenu === 'reports' && ($_SERVER['REQUEST_URI'] ?? '') === '/reports/sales' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128181;</span> Laporan Penjualan</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('reports.purchase')): ?>
        <a href="/reports/purchases" class="azr-nav-link <?= $activeMenu === 'reports' && ($_SERVER['REQUEST_URI'] ?? '') === '/reports/purchases' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128722;</span> Laporan Pembelian</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('reports.inventory')): ?>
        <a href="/reports/inventory" class="azr-nav-link <?= $activeMenu === 'reports' && ($_SERVER['REQUEST_URI'] ?? '') === '/reports/inventory' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128230;</span> Laporan Inventory</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('reports.finance')): ?>
        <a href="/reports/hpp" class="azr-nav-link <?= $activeMenu === 'reports' && ($_SERVER['REQUEST_URI'] ?? '') === '/reports/hpp' ? 'active' : '' ?>"><span class="azr-nav-icon">&#129534;</span> Laporan HPP</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('tax.report')): ?>
        <a href="/reports/tax" class="azr-nav-link <?= $activeMenu === 'reports' && ($_SERVER['REQUEST_URI'] ?? '') === '/reports/tax' ? 'active' : '' ?>"><span class="azr-nav-icon">&#127974;</span> Laporan Pajak</a>
        <?php endif; ?>

        <div class="azr-nav-group-label">Perpajakan</div>
        <?php if (AuthService::hasPermission('tax.view')): ?>
        <a href="/tax" class="azr-nav-link <?= $activeMenu === 'tax' ? 'active' : '' ?>"><span class="azr-nav-icon">&#127974;</span> Dashboard Pajak</a>
        <a href="/tax/output" class="azr-nav-link <?= $activeMenu === 'tax' && ($_SERVER['REQUEST_URI'] ?? '') === '/tax/output' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128184;</span> Pajak Keluaran</a>
        <a href="/tax/input" class="azr-nav-link <?= $activeMenu === 'tax' && ($_SERVER['REQUEST_URI'] ?? '') === '/tax/input' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128176;</span> Pajak Masukan</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('tax.settings')): ?>
        <a href="/tax/settings" class="azr-nav-link <?= $activeMenu === 'tax' && ($_SERVER['REQUEST_URI'] ?? '') === '/tax/settings' ? 'active' : '' ?>"><span class="azr-nav-icon">&#9881;&#65039;</span> Pengaturan Pajak</a>
        <?php endif; ?>

        <?php if (AuthService::hasPermission('users.view') || AuthService::hasPermission('roles.manage') || AuthService::hasPermission('stores.manage') || AuthService::hasPermission('settings.view') || AuthService::hasPermission('audit.view')): ?>
        <div class="azr-nav-group-label">Administrasi</div>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('users.view')): ?>
        <a href="/users" class="azr-nav-link <?= $activeMenu === 'users' ? 'active' : '' ?>">
            <span class="azr-nav-icon">&#128101;</span> Pengguna
        </a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('roles.manage')): ?>
        <a href="/roles" class="azr-nav-link <?= $activeMenu === 'roles' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128274;</span> Role & Akses</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('stores.manage')): ?>
        <a href="/settings/stores.php" class="azr-nav-link <?= $activeMenu === 'settings' && ($_SERVER['REQUEST_URI'] ?? '') === '/settings/stores.php' ? 'active' : '' ?>"><span class="azr-nav-icon">&#127970;</span> Toko / Cabang</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('settings.view')): ?>
        <a href="/settings" class="azr-nav-link <?= $activeMenu === 'settings' && ($_SERVER['REQUEST_URI'] ?? '') === '/settings' ? 'active' : '' ?>"><span class="azr-nav-icon">&#9881;</span> Pengaturan</a>
        <?php endif; ?>
        <?php if (AuthService::hasPermission('audit.view')): ?>
        <a href="/audit" class="azr-nav-link <?= $activeMenu === 'audit' ? 'active' : '' ?>"><span class="azr-nav-icon">&#128220;</span> Audit Log</a>
        <?php endif; ?>
    </nav>
</aside>
<div class="azr-sidebar-backdrop" data-azr-sidebar-backdrop></div>
