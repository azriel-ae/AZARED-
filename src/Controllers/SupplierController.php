<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\AuditLog;
use App\Models\Supplier;

final class SupplierController
{
    private const PER_PAGE = 20;

    public static function index(): void
    {
        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'status' => (string) ($_GET['status'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = Supplier::paginate($filters, $page, self::PER_PAGE);
        $suppliers = $result['rows'];
        $total = $result['total'];
        $totalPages = (int) max(1, ceil($total / self::PER_PAGE));

        require dirname(__DIR__, 2) . '/views/suppliers/index.php';
    }

    public static function show(int $id): void
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            Response::redirect('/suppliers/index.php?error=notfound');
        }
        $history = Supplier::history($id);
        require dirname(__DIR__, 2) . '/views/suppliers/show.php';
    }

    private static function collectInput(): array
    {
        return [
            'name'            => trim((string) ($_POST['name'] ?? '')),
            'legal_name'      => trim((string) ($_POST['legal_name'] ?? '')),
            'contact_person'  => trim((string) ($_POST['contact_person'] ?? '')),
            'phone'           => trim((string) ($_POST['phone'] ?? '')),
            'email'           => trim((string) ($_POST['email'] ?? '')),
            'address'         => trim((string) ($_POST['address'] ?? '')),
            'npwp'            => trim((string) ($_POST['npwp'] ?? '')),
            'nik'             => trim((string) ($_POST['nik'] ?? '')),
            'tax_status'      => (string) ($_POST['tax_status'] ?? ''),
            'tax_address'     => trim((string) ($_POST['tax_address'] ?? '')),
            'status'          => (string) ($_POST['status'] ?? 'active'),
        ];
    }

    public static function store(): void
    {
        $data = self::collectInput();
        $validator = new Validator($data);
        $validator->required('name', 'Nama supplier')->maxLength('name', 150, 'Nama supplier')
            ->in('status', ['active', 'inactive'], 'Status')
            ->in('tax_status', ['', 'pkp', 'non_pkp'], 'Status Perpajakan');
        if ($data['email'] !== '') {
            $validator->email('email', 'Email');
        }
        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        $data['code'] = Supplier::generateCode();
        $id = Supplier::create($data);
        AuditLog::record(AuthService::id(), 'supplier.create', 'supplier', $id, null, $data);
        Response::jsonSuccess(['id' => $id, 'code' => $data['code']], 'Supplier berhasil ditambahkan.');
    }

    public static function update(int $id): void
    {
        $existing = Supplier::find($id);
        if (!$existing) {
            Response::jsonError('Supplier tidak ditemukan.', 404);
        }
        $data = self::collectInput();
        $validator = new Validator($data);
        $validator->required('name', 'Nama supplier')->maxLength('name', 150, 'Nama supplier')
            ->in('status', ['active', 'inactive'], 'Status')
            ->in('tax_status', ['', 'pkp', 'non_pkp'], 'Status Perpajakan');
        if ($data['email'] !== '') {
            $validator->email('email', 'Email');
        }
        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        Supplier::update($id, $data);
        AuditLog::record(AuthService::id(), 'supplier.update', 'supplier', $id, $existing, $data);
        Response::jsonSuccess([], 'Data supplier berhasil diperbarui.');
    }

    public static function toggleStatus(int $id): void
    {
        $existing = Supplier::find($id);
        if (!$existing) {
            Response::jsonError('Supplier tidak ditemukan.', 404);
        }
        $newStatus = $existing['status'] === 'active' ? 'inactive' : 'active';
        Supplier::setStatus($id, $newStatus);
        AuditLog::record(AuthService::id(), 'supplier.status_change', 'supplier', $id, ['status' => $existing['status']], ['status' => $newStatus]);
        Response::jsonSuccess(['status' => $newStatus], 'Status supplier berhasil diperbarui.');
    }
}
