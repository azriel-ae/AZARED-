<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\AuditLog;
use App\Models\ExpenseCategory;

final class ExpenseCategoryController
{
    public static function index(): void
    {
        $categories = ExpenseCategory::all(false);
        require dirname(__DIR__, 2) . '/views/expense_categories/index.php';
    }

    private static function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
        return trim($slug, '-');
    }

    public static function store(): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $validator = new Validator(['name' => $name]);
        $validator->required('name', 'Nama Kategori')->maxLength('name', 100, 'Nama Kategori');

        if ($validator->fails()) {
            Response::jsonError('Periksa kembali data yang dimasukkan.', 422, $validator->errors());
        }

        $slug = self::slugify($name);
        if (ExpenseCategory::slugExists($slug)) {
            Response::jsonError('Kategori dengan nama serupa sudah ada.', 422, ['name' => 'Nama kategori sudah digunakan.']);
        }

        $id = ExpenseCategory::create($name, $slug);
        AuditLog::record(AuthService::id(), 'expense_category.create', 'expense_category', $id, null, ['name' => $name]);

        Response::jsonSuccess([], 'Kategori pengeluaran berhasil ditambahkan.');
    }

    public static function update(int $id): void
    {
        $existing = ExpenseCategory::find($id);
        if (!$existing) {
            Response::jsonError('Kategori tidak ditemukan.', 404);
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'active');
        $validator = new Validator(['name' => $name, 'status' => $status]);
        $validator->required('name', 'Nama Kategori')->maxLength('name', 100, 'Nama Kategori')
            ->in('status', ['active', 'inactive'], 'Status');

        if ($validator->fails()) {
            Response::jsonError('Periksa kembali data yang dimasukkan.', 422, $validator->errors());
        }

        $slug = self::slugify($name);
        if (ExpenseCategory::slugExists($slug, $id)) {
            Response::jsonError('Kategori dengan nama serupa sudah ada.', 422, ['name' => 'Nama kategori sudah digunakan.']);
        }

        ExpenseCategory::update($id, $name, $slug, $status);
        AuditLog::record(AuthService::id(), 'expense_category.update', 'expense_category', $id, $existing, ['name' => $name, 'status' => $status]);

        Response::jsonSuccess([], 'Kategori pengeluaran berhasil diperbarui.');
    }

    public static function destroy(int $id): void
    {
        $existing = ExpenseCategory::find($id);
        if (!$existing) {
            Response::jsonError('Kategori tidak ditemukan.', 404);
        }

        if (!ExpenseCategory::delete($id)) {
            Response::jsonError('Kategori masih digunakan oleh data pengeluaran dan tidak dapat dihapus. Nonaktifkan saja kategori ini.', 422);
        }

        AuditLog::record(AuthService::id(), 'expense_category.delete', 'expense_category', $id, $existing, null);
        Response::jsonSuccess([], 'Kategori pengeluaran berhasil dihapus.');
    }
}
