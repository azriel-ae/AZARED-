<?php
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Atur Izin - ' . $role['name'];
$activeMenu = 'roles';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Role & Akses', 'url' => '/roles'],
    ['label' => $role['name']],
];
$isAdminRole = $role['slug'] === 'admin';

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Izin untuk Role: <?= Response::e($role['name']) ?></h2>
        <a href="/roles" class="azr-btn azr-btn-outline">&larr; Kembali</a>
    </div>

    <?php if ($isAdminRole): ?>
        <div class="azr-alert azr-alert-info">
            Role Admin selalu memiliki seluruh izin sistem dan tidak dapat dikurangi lewat halaman ini,
            untuk mencegah semua akun admin terkunci dari sistem tanpa jalan kembali.
        </div>
    <?php endif; ?>

    <form action="/roles/update-permissions.php?id=<?= (int) $role['id'] ?>" method="post" data-azr-ajax>
        <?= Csrf::field() ?>

        <?php foreach ($groupedPermissions as $group => $perms): ?>
            <div style="margin-bottom:18px;">
                <h3 style="font-size:0.95rem;font-weight:800;color:var(--azr-blue-900);text-transform:capitalize;margin-bottom:8px;">
                    <?= Response::e($group) ?>
                </h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:8px;">
                    <?php foreach ($perms as $p): ?>
                        <label class="azr-checkbox-row">
                            <input type="checkbox" name="permission_ids[]" value="<?= (int) $p['id'] ?>"
                                <?= in_array($p['slug'], $grantedSlugs, true) ? 'checked' : '' ?>
                                <?= $isAdminRole ? 'disabled' : '' ?>>
                            <span>
                                <?= Response::e($p['slug']) ?>
                                <div class="azr-help-text"><?= Response::e($p['description'] ?: '') ?></div>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$isAdminRole): ?>
        <div style="margin-top:10px;">
            <button type="submit" class="azr-btn azr-btn-primary">Simpan Izin</button>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
