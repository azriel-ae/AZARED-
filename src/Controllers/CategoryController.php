<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\AuditLog;
use App\Models\Category;

final class CategoryController
{
    public static function index(): void
    {
        $categories = Category::all();
        require dirname(__DIR__, 2) . '/views/categories/index.php';
    }

    public static function store(): void
    {
        $data = [
            'name'   => trim((string) ($_POST['name'] ?? '')),
            'status' => (string) ($_POST['status'] ?? 'active'),
        ];

        $validator = new Validator($data);
        $validator->required('name', 'Nama kategori')->maxLength('name', 100, 'Nama kategori')
            ->in('status', ['active', 'inactive'], 'Status');

        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        $slug = self::slugify($data['name']);
        if (Category::slugExists($slug)) {
            Response::jsonError('Kategori dengan nama serupa sudah ada.', 422, ['name' => 'Nama sudah digunakan.']);
        }

        $id = Category::create($data['name'], $slug);
        if ($data['status'] !== 'active') {
            Category::update($id, $data['name'], $slug, $data['status']);
        }

        AuditLog::record(AuthService::id(), 'category.create', 'category', $id, null, $data);
        Response::jsonSuccess(['id' => $id], 'Kategori berhasil ditambahkan.');
    }

    public static function update(int $id): void
    {
        $existing = Category::find($id);
        if (!$existing) {
            Response::jsonError('Kategori tidak ditemukan.', 404);
        }

        $data = [
            'name'   => trim((string) ($_POST['name'] ?? '')),
            'status' => (string) ($_POST['status'] ?? 'active'),
        ];

        $validator = new Validator($data);
        $validator->required('name', 'Nama kategori')->maxLength('name', 100, 'Nama kategori')
            ->in('status', ['active', 'inactive'], 'Status');

        if ($validator->fails()) {
            Response::jsonError('Data tidak valid.', 422, $validator->errors());
        }

        $slug = self::slugify($data['name']);
        if (Category::slugExists($slug, $id)) {
            Response::jsonError('Kategori dengan nama serupa sudah ada.', 422, ['name' => 'Nama sudah digunakan.']);
        }

        Category::update($id, $data['name'], $slug, $data['status']);
        AuditLog::record(AuthService::id(), 'category.update', 'category', $id, $existing, $data);
        Response::jsonSuccess([], 'Kategori berhasil diperbarui.');
    }

    public static function destroy(int $id): void
    {
        $existing = Category::find($id);
        if (!$existing) {
            Response::jsonError('Kategori tidak ditemukan.', 404);
        }

        // Safety: refuse deletion while any product still references this
        // category, so no product is ever left pointing at a ghost row.
        if (!Category::delete($id)) {
            Response::jsonError(
                'Kategori masih digunakan oleh produk dan tidak dapat dihapus. Pindahkan atau nonaktifkan produk terkait terlebih dahulu, atau nonaktifkan saja kategori ini.',
                409
            );
        }

        AuditLog::record(AuthService::id(), 'category.delete', 'category', $id, $existing, null);
        Response::jsonSuccess([], 'Kategori berhasil dihapus.');
    }

    private static function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
        return trim((string) $slug, '-');
    }
}
