<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\AuditLog;
use App\Models\Unit;

final class UnitController
{
    public static function index(): void
    {
        $units = Unit::all();
        require dirname(__DIR__, 2) . '/views/units/index.php';
    }

    public static function store(): void
    {
        $data = [
            'name'   => trim((string) ($_POST['name'] ?? '')),
            'symbol' => trim((string) ($_POST['symbol'] ?? '')),
        ];

        $validator = new Validator($data);
        $validator->required('name', 'Nama satuan')->maxLength('name', 50, 'Nama satuan')
            ->required('symbol', 'Simbol')->maxLength('symbol', 10, 'Simbol');

        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        if (Unit::symbolExists($data['symbol'])) {
            Response::jsonError('Simbol satuan sudah digunakan.', 422, ['symbol' => 'Simbol sudah digunakan.']);
        }

        $id = Unit::create($data['name'], $data['symbol']);
        AuditLog::record(AuthService::id(), 'unit.create', 'unit', $id, null, $data);
        Response::jsonSuccess(['id' => $id], 'Satuan berhasil ditambahkan.');
    }

    public static function update(int $id): void
    {
        $existing = Unit::find($id);
        if (!$existing) {
            Response::jsonError('Satuan tidak ditemukan.', 404);
        }

        $data = [
            'name'   => trim((string) ($_POST['name'] ?? '')),
            'symbol' => trim((string) ($_POST['symbol'] ?? '')),
            'status' => (string) ($_POST['status'] ?? 'active'),
        ];

        $validator = new Validator($data);
        $validator->required('name', 'Nama satuan')->maxLength('name', 50, 'Nama satuan')
            ->required('symbol', 'Simbol')->maxLength('symbol', 10, 'Simbol')
            ->in('status', ['active', 'inactive'], 'Status');

        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        if (Unit::symbolExists($data['symbol'], $id)) {
            Response::jsonError('Simbol satuan sudah digunakan.', 422, ['symbol' => 'Simbol sudah digunakan.']);
        }

        Unit::update($id, $data['name'], $data['symbol'], $data['status']);
        AuditLog::record(AuthService::id(), 'unit.update', 'unit', $id, $existing, $data);
        Response::jsonSuccess([], 'Satuan berhasil diperbarui.');
    }

    public static function destroy(int $id): void
    {
        $existing = Unit::find($id);
        if (!$existing) {
            Response::jsonError('Satuan tidak ditemukan.', 404);
        }
        if (!Unit::delete($id)) {
            Response::jsonError('Satuan masih digunakan oleh produk dan tidak dapat dihapus.', 409);
        }
        AuditLog::record(AuthService::id(), 'unit.delete', 'unit', $id, $existing, null);
        Response::jsonSuccess([], 'Satuan berhasil dihapus.');
    }
}
