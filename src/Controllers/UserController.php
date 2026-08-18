<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;

/**
 * AZARED - user management (CRUD). Every method here assumes
 * PermissionMiddleware::require(...) has already been called by the
 * entry point script that invokes it.
 */
final class UserController
{
    public static function index(): void
    {
        $users = User::all();
        $roles = Role::all();
        $stores = Store::all();
        require dirname(__DIR__, 2) . '/views/users/index.php';
    }

    public static function createForm(): void
    {
        $roles = Role::all();
        $stores = Store::all();
        $errors = [];
        $old = [];
        require dirname(__DIR__, 2) . '/views/users/form.php';
    }

    public static function store(): void
    {
        \App\Middleware\CsrfMiddleware::verify();

        $data = [
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'username'  => trim((string) ($_POST['username'] ?? '')),
            'email'     => trim((string) ($_POST['email'] ?? '')),
            'phone'     => trim((string) ($_POST['phone'] ?? '')),
            'password'  => (string) ($_POST['password'] ?? ''),
            'password_confirmation' => (string) ($_POST['password_confirmation'] ?? ''),
            'role_id'   => (string) ($_POST['role_id'] ?? ''),
            'status'    => (string) ($_POST['status'] ?? 'active'),
            'store_ids' => $_POST['store_ids'] ?? [],
        ];

        $validator = new Validator($data);
        $validator->required('full_name', 'Nama lengkap')
            ->maxLength('full_name', 150, 'Nama lengkap')
            ->required('username', 'Username')
            ->minLength('username', 4, 'Username')
            ->maxLength('username', 50, 'Username')
            ->alphaNumericUnderscore('username', 'Username')
            ->required('role_id', 'Role')
            ->in('status', ['active', 'inactive', 'suspended'], 'Status')
            ->required('password', 'Password')
            ->strongPassword('password', 'Password')
            ->matches('password_confirmation', 'password', 'Konfirmasi password');

        if ($data['email'] !== '') {
            $validator->email('email', 'Email');
        }

        // IMPORTANT: only ADMIN may create another ADMIN account (a "normal"
        // user with users.create permission, e.g. a Manager, must not be
        // able to grant admin-level access to anyone).
        $selectedRole = null;
        foreach (Role::all() as $r) {
            if ((string) $r['id'] === $data['role_id']) {
                $selectedRole = $r;
                break;
            }
        }

        if ($selectedRole === null) {
            $validator = $validator; // no-op, keeps flow explicit
        } elseif ($selectedRole['slug'] === 'admin' && !AuthService::hasRole('admin')) {
            http_response_code(403);
            $errors = ['role_id' => 'Hanya Admin yang dapat membuat akun dengan role Admin.'];
            $roles = Role::all();
            $stores = Store::all();
            $old = $data;
            require dirname(__DIR__, 2) . '/views/users/form.php';
            return;
        }

        if (!$validator->fails() && User::usernameExists($data['username'])) {
            $validator = new Validator($data);
            $validator->required('username', 'Username');
        }

        $usernameTaken = User::usernameExists($data['username']);
        $emailTaken = $data['email'] !== '' && User::emailExists($data['email']);

        if ($validator->fails() || $usernameTaken || $emailTaken) {
            $errors = $validator->errors();
            if ($usernameTaken) {
                $errors['username'] = 'Username sudah digunakan.';
            }
            if ($emailTaken) {
                $errors['email'] = 'Email sudah digunakan.';
            }
            $roles = Role::all();
            $stores = Store::all();
            $old = $data;
            require dirname(__DIR__, 2) . '/views/users/form.php';
            return;
        }

        $newUserId = User::create($data, (int) AuthService::id());

        AuditLog::record(
            AuthService::id(),
            'user.create',
            'user',
            $newUserId,
            null,
            ['username' => $data['username'], 'role_id' => $data['role_id'], 'status' => $data['status']]
        );

        Response::redirect('/users/index.php?created=1');
    }

    /**
     * IDOR / privilege-escalation guard: a non-admin actor (e.g. a Manager
     * who holds users.edit) must never be able to modify an Admin account.
     */
    private static function guardAgainstEscalation(array $targetUser): void
    {
        $targetRoles = array_column(User::roles((int) $targetUser['id']), 'slug');
        if (in_array('admin', $targetRoles, true) && !AuthService::hasRole('admin')) {
            Response::redirect('/403.php');
        }
    }

    public static function editForm(int $userId): void
    {
        $user = User::findById($userId);
        if (!$user) {
            Response::redirect('/users/index.php?error=notfound');
        }
        self::guardAgainstEscalation($user);

        $roles = Role::all();
        $stores = Store::all();
        $userRoles = User::roles($userId);
        $userStoreAccess = User::storeAccess($userId);
        $errors = [];
        $old = [
            'id'        => $user['id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'phone'     => $user['phone'],
            'status'    => $user['status'],
            'role_id'   => (string) ($userRoles[0]['id'] ?? ''),
            'store_ids' => array_map(static fn ($s) => (string) $s['id'], $userStoreAccess),
        ];
        $isSelf = $userId === (int) AuthService::id();

        require dirname(__DIR__, 2) . '/views/users/edit_form.php';
    }

    public static function update(int $userId): void
    {
        \App\Middleware\CsrfMiddleware::verify();

        $target = User::findById($userId);
        if (!$target) {
            Response::redirect('/users/index.php?error=notfound');
        }
        self::guardAgainstEscalation($target);

        $isSelf = $userId === (int) AuthService::id();

        $data = [
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'email'     => trim((string) ($_POST['email'] ?? '')),
            'phone'     => trim((string) ($_POST['phone'] ?? '')),
            'status'    => (string) ($_POST['status'] ?? $target['status']),
            'role_id'   => (string) ($_POST['role_id'] ?? ''),
            'store_ids' => $_POST['store_ids'] ?? [],
        ];

        // SECURITY: a user editing their OWN account can never change
        // their own role, store access, or active status - that would let
        // any user with users.edit self-escalate to Admin, grant
        // themselves extra store access, or block someone else from
        // suspending them. These three fields are silently pinned back to
        // their current server-side values when the target is the actor.
        if ($isSelf) {
            $currentRole = User::roles($userId)[0]['id'] ?? null;
            $data['role_id'] = (string) $currentRole;
            $data['store_ids'] = array_map(static fn ($s) => (string) $s['id'], User::storeAccess($userId));
            $data['status'] = $target['status'];
        }

        $validator = new Validator($data);
        $validator->required('full_name', 'Nama lengkap')->maxLength('full_name', 150, 'Nama lengkap')
            ->required('role_id', 'Role')
            ->in('status', ['active', 'inactive', 'suspended'], 'Status');
        if ($data['email'] !== '') {
            $validator->email('email', 'Email');
        }

        $selectedRole = null;
        foreach (Role::all() as $r) {
            if ((string) $r['id'] === $data['role_id']) {
                $selectedRole = $r;
                break;
            }
        }

        // Only an Admin may promote another account to Admin.
        if ($selectedRole !== null && $selectedRole['slug'] === 'admin' && !AuthService::hasRole('admin')) {
            http_response_code(403);
            $errors = ['role_id' => 'Hanya Admin yang dapat memberikan role Admin.'];
            $roles = Role::all();
            $stores = Store::all();
            $old = array_merge($data, ['id' => $userId]);
            require dirname(__DIR__, 2) . '/views/users/edit_form.php';
            return;
        }

        $emailTaken = $data['email'] !== '' && User::emailExists($data['email'], $userId);

        if ($validator->fails() || $selectedRole === null || $emailTaken) {
            $errors = $validator->errors();
            if ($selectedRole === null) {
                $errors['role_id'] = 'Role tidak valid.';
            }
            if ($emailTaken) {
                $errors['email'] = 'Email sudah digunakan.';
            }
            $roles = Role::all();
            $stores = Store::all();
            $old = array_merge($data, ['id' => $userId]);
            require dirname(__DIR__, 2) . '/views/users/edit_form.php';
            return;
        }

        $before = [
            'full_name' => $target['full_name'],
            'email'     => $target['email'],
            'status'    => $target['status'],
        ];

        User::updateProfile($userId, $data);
        if (!$isSelf) {
            User::updateRole($userId, (int) $data['role_id']);
            User::updateStoreAccess($userId, array_map('intval', (array) $data['store_ids']));
        }

        AuditLog::record(AuthService::id(), 'user.update', 'user', $userId, $before, $data);

        Response::redirect('/users/index.php?updated=1');
    }

    public static function toggleStatus(int $userId): void
    {
        \App\Middleware\CsrfMiddleware::verify();

        $user = User::findById($userId);
        if (!$user) {
            Response::redirect('/users/index.php?error=notfound');
        }

        self::guardAgainstEscalation($user);

        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        User::setStatus($userId, $newStatus);

        AuditLog::record(
            AuthService::id(),
            'user.status_change',
            'user',
            $userId,
            ['status' => $user['status']],
            ['status' => $newStatus]
        );

        Response::redirect('/users/index.php?updated=1');
    }

    public static function resetPassword(int $userId): void
    {
        \App\Middleware\CsrfMiddleware::verify();

        $target = User::findById($userId);
        if (!$target) {
            Response::redirect('/users/index.php?error=notfound');
        }
        self::guardAgainstEscalation($target);

        $newPassword = (string) ($_POST['new_password'] ?? '');
        $validator = new Validator(['new_password' => $newPassword]);
        $validator->required('new_password', 'Password baru')->strongPassword('new_password', 'Password baru');

        if ($validator->fails()) {
            Response::redirect('/users/index.php?error=weakpassword');
        }

        User::resetPassword($userId, $newPassword);

        AuditLog::record(AuthService::id(), 'user.reset_password', 'user', $userId, null, null);

        Response::redirect('/users/index.php?reset=1');
    }
}
