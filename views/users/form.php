<?php
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Tambah Pengguna';
$activeMenu = 'users';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Pengguna', 'url' => '/users/index.php'],
    ['label' => 'Tambah Pengguna'],
];
$errors = $errors ?? [];
$old = $old ?? [];

function azrOld(array $old, string $key, string $default = ''): string
{
    return Response::e((string) ($old[$key] ?? $default));
}

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-card" style="max-width:720px;">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Tambah Pengguna Baru</h2>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="azr-alert azr-alert-error">
        Periksa kembali form di bawah ini — terdapat kesalahan input.
    </div>
    <?php endif; ?>

    <form action="/users/store.php" method="post" novalidate>
        <?= Csrf::field() ?>

        <div class="azr-form-group">
            <label class="azr-label" for="full_name">Nama Lengkap</label>
            <input class="azr-input" type="text" id="full_name" name="full_name" value="<?= azrOld($old, 'full_name') ?>" required>
            <?php if (!empty($errors['full_name'])): ?><p class="azr-error-text"><?= Response::e($errors['full_name']) ?></p><?php endif; ?>
        </div>

        <div style="display:flex;gap:16px;">
            <div class="azr-form-group" style="flex:1;">
                <label class="azr-label" for="username">Username</label>
                <input class="azr-input" type="text" id="username" name="username" value="<?= azrOld($old, 'username') ?>" required>
                <?php if (!empty($errors['username'])): ?><p class="azr-error-text"><?= Response::e($errors['username']) ?></p><?php endif; ?>
            </div>
            <div class="azr-form-group" style="flex:1;">
                <label class="azr-label" for="email">Email (opsional)</label>
                <input class="azr-input" type="email" id="email" name="email" value="<?= azrOld($old, 'email') ?>">
                <?php if (!empty($errors['email'])): ?><p class="azr-error-text"><?= Response::e($errors['email']) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="azr-form-group">
            <label class="azr-label" for="phone">Nomor Telepon (opsional)</label>
            <input class="azr-input" type="text" id="phone" name="phone" value="<?= azrOld($old, 'phone') ?>">
        </div>

        <div style="display:flex;gap:16px;">
            <div class="azr-form-group" style="flex:1;">
                <label class="azr-label" for="password">Password</label>
                <input class="azr-input" type="password" id="password" name="password" required minlength="8">
                <?php if (!empty($errors['password'])): ?><p class="azr-error-text"><?= Response::e($errors['password']) ?></p><?php endif; ?>
                <p class="azr-help-text">Minimal 8 karakter, mengandung huruf besar dan angka.</p>
            </div>
            <div class="azr-form-group" style="flex:1;">
                <label class="azr-label" for="password_confirmation">Konfirmasi Password</label>
                <input class="azr-input" type="password" id="password_confirmation" name="password_confirmation" required minlength="8">
                <?php if (!empty($errors['password_confirmation'])): ?><p class="azr-error-text"><?= Response::e($errors['password_confirmation']) ?></p><?php endif; ?>
            </div>
        </div>

        <div style="display:flex;gap:16px;">
            <div class="azr-form-group" style="flex:1;">
                <label class="azr-label" for="role_id">Role</label>
                <select class="azr-select" id="role_id" name="role_id" required>
                    <option value="">-- Pilih Role --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>" <?= (string) ($old['role_id'] ?? '') === (string) $role['id'] ? 'selected' : '' ?>>
                            <?= Response::e($role['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['role_id'])): ?><p class="azr-error-text"><?= Response::e($errors['role_id']) ?></p><?php endif; ?>
            </div>
            <div class="azr-form-group" style="flex:1;">
                <label class="azr-label" for="status">Status</label>
                <select class="azr-select" id="status" name="status">
                    <?php $statusOld = $old['status'] ?? 'active'; ?>
                    <option value="active" <?= $statusOld === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= $statusOld === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    <option value="suspended" <?= $statusOld === 'suspended' ? 'selected' : '' ?>>Ditangguhkan</option>
                </select>
            </div>
        </div>

        <div class="azr-form-group">
            <label class="azr-label">Akses Toko / Cabang</label>
            <?php $oldStoreIds = $old['store_ids'] ?? []; ?>
            <?php foreach ($stores as $store): ?>
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;font-weight:400;">
                    <input type="checkbox" name="store_ids[]" value="<?= (int) $store['id'] ?>"
                        <?= in_array((string) $store['id'], (array) $oldStoreIds, true) ? 'checked' : '' ?>>
                    <?= Response::e($store['name']) ?> (<?= Response::e($store['code']) ?>)
                </label>
            <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="submit" class="azr-btn azr-btn-primary">Simpan Pengguna</button>
            <a href="/users/index.php" class="azr-btn azr-btn-outline">Batal</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
