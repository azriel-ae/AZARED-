<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\AuditLog;
use App\Models\Store;

final class StoreController
{
    public static function index(): void
    {
        $stores = Store::allIncludingInactive();
        require dirname(__DIR__, 2) . '/views/settings/stores.php';
    }

    private static function validated(?int $exceptId = null): array
    {
        $data = [
            'name'    => trim((string) ($_POST['name'] ?? '')),
            'code'    => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'phone'   => trim((string) ($_POST['phone'] ?? '')),
            'tax_id'  => trim((string) ($_POST['tax_id'] ?? '')),
            'status'  => (string) ($_POST['status'] ?? 'active'),
        ];

        $validator = new Validator($data);
        $validator->required('name', 'Nama toko')->maxLength('name', 150, 'Nama toko')
            ->required('code', 'Kode toko')->maxLength('code', 30, 'Kode toko')
            ->maxLength('address', 255, 'Alamat')
            ->maxLength('phone', 30, 'Telepon')
            ->maxLength('tax_id', 50, 'NPWP')
            ->in('status', ['active', 'inactive'], 'Status');

        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        if (Store::codeExists($data['code'], $exceptId)) {
            Response::jsonError('Kode toko sudah digunakan.', 422, ['code' => 'Kode sudah digunakan.']);
        }

        return $data;
    }

    public static function store(): void
    {
        $data = self::validated();
        $id = Store::create($data);
        AuditLog::record(AuthService::id(), 'store.create', 'store', $id, null, $data);
        Response::jsonSuccess(['id' => $id], 'Toko/cabang berhasil ditambahkan.');
    }

    public static function update(int $id): void
    {
        $existing = Store::find($id);
        if (!$existing) {
            Response::jsonError('Toko tidak ditemukan.', 404);
        }

        $data = self::validated($id);

        // Refuse to deactivate the last remaining active store - POS,
        // user store-access, and every store-scoped report assume at
        // least one active store exists.
        if ($data['status'] !== 'active' && $existing['status'] === 'active' && Store::activeCount() <= 1) {
            Response::jsonError('Tidak dapat menonaktifkan satu-satunya toko aktif yang tersisa.', 409);
        }

        Store::update($id, $data);
        AuditLog::record(AuthService::id(), 'store.update', 'store', $id, $existing, $data);
        Response::jsonSuccess([], 'Toko/cabang berhasil diperbarui.');
    }

    public static function toggleStatus(int $id): void
    {
        $existing = Store::find($id);
        if (!$existing) {
            Response::jsonError('Toko tidak ditemukan.', 404);
        }

        if ($existing['status'] === 'active' && Store::activeCount() <= 1) {
            Response::jsonError('Tidak dapat menonaktifkan satu-satunya toko aktif yang tersisa.', 409);
        }

        Store::toggleStatus($id);
        AuditLog::record(AuthService::id(), 'store.toggle_status', 'store', $id, $existing, null);
        Response::jsonSuccess([], 'Status toko berhasil diperbarui.');
    }
}
