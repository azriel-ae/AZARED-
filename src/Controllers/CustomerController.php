<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\AuditLog;
use App\Models\Customer;

final class CustomerController
{
    private const PER_PAGE = 20;

    public static function index(): void
    {
        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'status' => (string) ($_GET['status'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = Customer::paginate($filters, $page, self::PER_PAGE);
        $customers = $result['rows'];
        $total = $result['total'];
        $totalPages = (int) max(1, ceil($total / self::PER_PAGE));

        require dirname(__DIR__, 2) . '/views/customers/index.php';
    }

    public static function show(int $id): void
    {
        $customer = Customer::find($id);
        if (!$customer) {
            Response::redirect('/customers/index.php?error=notfound');
        }
        $history = Customer::history($id);
        require dirname(__DIR__, 2) . '/views/customers/show.php';
    }

    private static function collectInput(): array
    {
        return [
            'name'        => trim((string) ($_POST['name'] ?? '')),
            'legal_name'  => trim((string) ($_POST['legal_name'] ?? '')),
            'phone'       => trim((string) ($_POST['phone'] ?? '')),
            'email'       => trim((string) ($_POST['email'] ?? '')),
            'address'     => trim((string) ($_POST['address'] ?? '')),
            'npwp'        => trim((string) ($_POST['npwp'] ?? '')),
            'nik'         => trim((string) ($_POST['nik'] ?? '')),
            'tax_status'  => (string) ($_POST['tax_status'] ?? ''),
            'tax_address' => trim((string) ($_POST['tax_address'] ?? '')),
            'type'        => (string) ($_POST['type'] ?? 'retail'),
            'status'      => (string) ($_POST['status'] ?? 'active'),
        ];
    }

    public static function store(): void
    {
        $data = self::collectInput();
        $validator = new Validator($data);
        $validator->required('name', 'Nama pelanggan')->maxLength('name', 150, 'Nama pelanggan')
            ->in('type', ['retail', 'member', 'wholesale', 'corporate'], 'Tipe pelanggan')
            ->in('status', ['active', 'inactive'], 'Status')
            ->in('tax_status', ['', 'pkp', 'non_pkp'], 'Status Perpajakan');
        if ($data['email'] !== '') {
            $validator->email('email', 'Email');
        }
        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        $data['code'] = Customer::generateCode();
        $id = Customer::create($data);
        AuditLog::record(AuthService::id(), 'customer.create', 'customer', $id, null, $data);
        Response::jsonSuccess(['id' => $id, 'code' => $data['code']], 'Pelanggan berhasil ditambahkan.');
    }

    public static function update(int $id): void
    {
        $existing = Customer::find($id);
        if (!$existing) {
            Response::jsonError('Pelanggan tidak ditemukan.', 404);
        }
        $data = self::collectInput();
        $validator = new Validator($data);
        $validator->required('name', 'Nama pelanggan')->maxLength('name', 150, 'Nama pelanggan')
            ->in('type', ['retail', 'member', 'wholesale', 'corporate'], 'Tipe pelanggan')
            ->in('status', ['active', 'inactive'], 'Status')
            ->in('tax_status', ['', 'pkp', 'non_pkp'], 'Status Perpajakan');
        if ($data['email'] !== '') {
            $validator->email('email', 'Email');
        }
        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        Customer::update($id, $data);
        AuditLog::record(AuthService::id(), 'customer.update', 'customer', $id, $existing, $data);
        Response::jsonSuccess([], 'Data pelanggan berhasil diperbarui.');
    }

    public static function toggleStatus(int $id): void
    {
        $existing = Customer::find($id);
        if (!$existing) {
            Response::jsonError('Pelanggan tidak ditemukan.', 404);
        }
        $newStatus = $existing['status'] === 'active' ? 'inactive' : 'active';
        Customer::setStatus($id, $newStatus);
        AuditLog::record(AuthService::id(), 'customer.status_change', 'customer', $id, ['status' => $existing['status']], ['status' => $newStatus]);
        Response::jsonSuccess(['status' => $newStatus], 'Status pelanggan berhasil diperbarui.');
    }
}
