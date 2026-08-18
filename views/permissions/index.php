<?php
use App\Helpers\Response;

$pageTitle = 'Katalog Izin';
$activeMenu = 'roles';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Role & Akses', 'url' => '/roles'],
    ['label' => 'Katalog Izin'],
];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Katalog Izin (Permission)</h2>
        <a href="/roles" class="azr-btn azr-btn-outline">&larr; Kembali ke Role</a>
    </div>
    <p class="azr-help-text" style="margin-bottom:14px;">
        Halaman ini hanya menampilkan katalog izin yang tersedia di sistem dan role mana saja yang
        memilikinya - bersifat baca saja. Setiap izin di sini terhubung langsung ke pemeriksaan otorisasi
        server-side (<code>PermissionMiddleware::require()</code>) di kode aplikasi, sehingga izin baru
        tidak dapat dibuat bebas lewat UI. Untuk mengubah izin yang dimiliki sebuah role, gunakan tombol
        "Atur Izin" pada halaman Role.
    </p>

    <?php foreach ($groupedPermissions as $group => $perms): ?>
        <div style="margin-bottom:22px;">
            <h3 style="font-size:0.95rem;font-weight:800;color:var(--azr-blue-900);text-transform:capitalize;margin-bottom:8px;">
                <?= Response::e($group) ?>
            </h3>
            <div class="azr-table-wrap">
                <table class="azr-table">
                    <thead>
                    <tr>
                        <th>Izin</th>
                        <th>Deskripsi</th>
                        <?php foreach ($roles as $r): ?>
                            <th style="text-align:center;"><?= Response::e($r['name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($perms as $p): ?>
                        <tr>
                            <td><code><?= Response::e($p['slug']) ?></code></td>
                            <td><?= Response::e($p['description'] ?: '-') ?></td>
                            <?php foreach ($roles as $r): ?>
                                <td style="text-align:center;">
                                    <?php if (!empty($matrix[$r['slug']][$p['slug']])): ?>
                                        <span style="color:var(--azr-green);font-weight:700;">&#10003;</span>
                                    <?php else: ?>
                                        <span style="color:var(--azr-gray-300);">&#8212;</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
