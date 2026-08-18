<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;

/**
 * AZARED - Role management.
 *
 * Permissions themselves are fixed (they must correspond to a real
 * PermissionMiddleware::require() call in code - see Permission model
 * docblock), but WHICH permissions a role grants, and which custom
 * roles exist beyond the six seeded system roles, is fully editable
 * here. System roles (is_system = 1: admin, owner, manager, cashier,
 * accountant, tax_officer) can have their permission grants edited but
 * can never be renamed at the slug level or deleted, so every
 * historical permission check in the codebase keeps resolving.
 */
final class RoleController
{
    public static function index(): void
    {
        $roles = Role::all();
        require dirname(__DIR__, 2) . '/views/roles/index.php';
    }

    public static function store(): void
    {
        $data = [
            'name'        => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
        ];

        $validator = new Validator($data);
        $validator->required('name', 'Nama role')->maxLength('name', 50, 'Nama role')
            ->maxLength('description', 255, 'Deskripsi');

        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        $slug = self::slugify($data['name']);
        if ($slug === '' || Role::slugExists($slug)) {
            Response::jsonError('Role dengan nama serupa sudah ada.', 422, ['name' => 'Nama sudah digunakan.']);
        }

        $id = Role::create($data['name'], $slug, $data['description'] ?: null);
        AuditLog::record(AuthService::id(), 'role.create', 'role', $id, null, $data);
        Response::jsonSuccess(['id' => $id], 'Role berhasil ditambahkan.');
    }

    public static function update(int $id): void
    {
        $existing = Role::find($id);
        if (!$existing) {
            Response::jsonError('Role tidak ditemukan.', 404);
        }

        $data = [
            'name'        => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
        ];

        $validator = new Validator($data);
        $validator->required('name', 'Nama role')->maxLength('name', 50, 'Nama role')
            ->maxLength('description', 255, 'Deskripsi');

        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        Role::update($id, $data['name'], $data['description'] ?: null);
        AuditLog::record(AuthService::id(), 'role.update', 'role', $id, $existing, $data);
        Response::jsonSuccess([], 'Role berhasil diperbarui.');
    }

    public static function destroy(int $id): void
    {
        $existing = Role::find($id);
        if (!$existing) {
            Response::jsonError('Role tidak ditemukan.', 404);
        }

        if (!Role::delete($id)) {
            Response::jsonError(
                'Role sistem tidak dapat dihapus, atau role ini masih dipakai oleh salah satu pengguna. Pindahkan pengguna ke role lain terlebih dahulu.',
                409
            );
        }

        AuditLog::record(AuthService::id(), 'role.delete', 'role', $id, $existing, null);
        Response::jsonSuccess([], 'Role berhasil dihapus.');
    }

    /** Permission-matrix editor for a single role. */
    public static function permissionsForm(int $id): void
    {
        $role = Role::find($id);
        if (!$role) {
            Response::redirect('/roles');
        }

        $groupedPermissions = Permission::grouped();
        $grantedSlugs = Role::permissionSlugs($id);

        require dirname(__DIR__, 2) . '/views/roles/permissions.php';
    }

    public static function updatePermissions(int $id): void
    {
        $role = Role::find($id);
        if (!$role) {
            Response::jsonError('Role tidak ditemukan.', 404);
        }

        // Admin's own role must always retain every permission, otherwise
        // an operator could accidentally lock every admin out of the
        // system (including themselves) with no way back in short of a
        // direct database edit.
        if ($role['slug'] === 'admin') {
            Response::jsonError(
                'Izin role Admin tidak dapat dikurangi melalui UI untuk mencegah semua admin terkunci dari sistem.',
                403
            );
        }

        $submittedIds = array_map('intval', (array) ($_POST['permission_ids'] ?? []));
        $before = Role::permissionSlugs($id);

        Role::syncPermissions($id, $submittedIds);

        $after = Role::permissionSlugs($id);
        AuditLog::record(
            AuthService::id(),
            'role.permissions.update',
            'role',
            $id,
            ['permissions' => $before],
            ['permissions' => $after]
        );

        Response::jsonSuccess([], 'Izin role berhasil diperbarui.');
    }

    private static function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? $slug;
        return trim((string) $slug, '_');
    }
}
