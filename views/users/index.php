<?php
use App\Auth\AuthService;
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Manajemen Pengguna';
$activeMenu = 'users';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Pengguna']];

require __DIR__ . '/../layouts/main_top.php';

$badgeClass = [
    'active'    => 'azr-badge-active',
    'inactive'  => 'azr-badge-inactive',
    'suspended' => 'azr-badge-suspended',
];
$badgeLabel = [
    'active'    => 'Aktif',
    'inactive'  => 'Nonaktif',
    'suspended' => 'Ditangguhkan',
];
?>

<?php if (isset($_GET['created'])): ?>
<div class="azr-alert azr-alert-success" data-azr-autodismiss>Pengguna baru berhasil dibuat.</div>
<?php elseif (isset($_GET['updated'])): ?>
<div class="azr-alert azr-alert-success" data-azr-autodismiss>Status pengguna berhasil diperbarui.</div>
<?php elseif (isset($_GET['reset'])): ?>
<div class="azr-alert azr-alert-success" data-azr-autodismiss>Password pengguna berhasil direset.</div>
<?php endif; ?>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Daftar Pengguna</h2>
        <?php if (AuthService::hasPermission('users.create')): ?>
        <a href="/users/create.php" class="azr-btn azr-btn-primary">+ Tambah Pengguna</a>
        <?php endif; ?>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead>
            <tr>
                <th>Nama Lengkap</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Login Terakhir</th>
                <th style="text-align:right;">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= Response::e($u['full_name']) ?></td>
                    <td><?= Response::e($u['username']) ?></td>
                    <td><?= Response::e($u['email'] ?: '-') ?></td>
                    <td><?= Response::e($u['role_names'] ?: '-') ?></td>
                    <td>
                        <span class="azr-badge <?= $badgeClass[$u['status']] ?? '' ?>">
                            <?= $badgeLabel[$u['status']] ?? Response::e($u['status']) ?>
                        </span>
                    </td>
                    <td><?= $u['last_login_at'] ? Response::e($u['last_login_at']) : '-' ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <?php if (AuthService::hasPermission('users.edit')): ?>
                        <a href="/users/edit.php?id=<?= (int) $u['id'] ?>" class="azr-btn azr-btn-outline azr-btn-sm">Edit</a>
                        <form action="/users/toggle-status.php?id=<?= (int) $u['id'] ?>" method="post"
                              style="display:inline;" data-azr-confirm="Ubah status pengguna ini?">
                            <?= Csrf::field() ?>
                            <button type="submit" class="azr-btn azr-btn-outline azr-btn-sm">
                                <?= $u['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                        </form>
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm"
                                data-azr-modal-open="resetModal<?= (int) $u['id'] ?>">Reset Password</button>
                        <?php endif; ?>
                    </td>
                </tr>

                <?php if (AuthService::hasPermission('users.edit')): ?>
                <div class="azr-modal-backdrop" id="resetModal<?= (int) $u['id'] ?>">
                    <div class="azr-modal">
                        <h3 class="azr-modal-title">Reset Password: <?= Response::e($u['full_name']) ?></h3>
                        <form action="/users/reset-password.php?id=<?= (int) $u['id'] ?>" method="post">
                            <?= Csrf::field() ?>
                            <div class="azr-form-group">
                                <label class="azr-label">Password Baru</label>
                                <input class="azr-input" type="password" name="new_password" required minlength="8">
                                <p class="azr-help-text">Minimal 8 karakter, mengandung huruf besar dan angka.</p>
                            </div>
                            <div class="azr-modal-actions">
                                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                                <button type="submit" class="azr-btn azr-btn-primary">Reset Password</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if (empty($users)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--azr-gray-600);">Belum ada data pengguna.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
