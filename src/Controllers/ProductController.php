<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Unit;

final class ProductController
{
    private const PER_PAGE = 20;

    public static function index(): void
    {
        $filters = [
            'search'       => trim((string) ($_GET['search'] ?? '')),
            'category_id'  => (int) ($_GET['category_id'] ?? 0),
            'status'       => (string) ($_GET['status'] ?? ''),
            'stock_filter' => (string) ($_GET['stock_filter'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Product::paginate($filters, $page, self::PER_PAGE);
        $products = $result['rows'];
        $total = $result['total'];
        $totalPages = (int) max(1, ceil($total / self::PER_PAGE));
        $categories = Category::all(true);

        require dirname(__DIR__, 2) . '/views/products/index.php';
    }

    public static function createForm(): void
    {
        $categories = Category::all(true);
        $units = Unit::all(true);
        $taxes = Tax::all(true);
        $errors = [];
        $old = [];
        require dirname(__DIR__, 2) . '/views/products/form.php';
    }

    public static function editForm(int $id): void
    {
        $product = Product::find($id);
        if (!$product) {
            Response::redirect('/products/index.php?error=notfound');
        }
        $categories = Category::all(true);
        $units = Unit::all(true);
        $taxes = Tax::all(true);
        $errors = [];
        $old = $product;
        require dirname(__DIR__, 2) . '/views/products/form.php';
    }

    private static function collectInput(): array
    {
        return [
            'sku'                  => trim((string) ($_POST['sku'] ?? '')),
            'barcode'              => trim((string) ($_POST['barcode'] ?? '')),
            'name'                 => trim((string) ($_POST['name'] ?? '')),
            'category_id'          => (int) ($_POST['category_id'] ?? 0),
            'unit_id'              => (int) ($_POST['unit_id'] ?? 0),
            'cost_price'           => (float) ($_POST['cost_price'] ?? 0),
            'sell_price'           => (float) ($_POST['sell_price'] ?? 0),
            'wholesale_price'      => trim((string) ($_POST['wholesale_price'] ?? '')),
            'wholesale_min_qty'    => trim((string) ($_POST['wholesale_min_qty'] ?? '')),
            'stock'                => (float) ($_POST['stock'] ?? 0),
            'min_stock'            => (float) ($_POST['min_stock'] ?? 0),
            'tax_percent'          => (float) ($_POST['tax_percent'] ?? 0),
            'tax_id'               => (int) ($_POST['tax_id'] ?? 0) ?: null,
            'tax_inclusive'        => isset($_POST['tax_inclusive']) ? 1 : 0,
            'status'               => (string) ($_POST['status'] ?? 'active'),
            'description'          => trim((string) ($_POST['description'] ?? '')),
            'allow_negative_stock' => isset($_POST['allow_negative_stock']) ? 1 : 0,
        ];
    }

    private static function validate(array $data, ?int $exceptId = null): array
    {
        $validator = new Validator($data);
        $validator->required('name', 'Nama produk')->maxLength('name', 180, 'Nama produk')
            ->in('status', ['active', 'inactive'], 'Status');

        if ($data['sku'] !== '') {
            $validator->maxLength('sku', 50, 'SKU');
        }

        $errors = $validator->errors();
        if ($data['cost_price'] < 0) {
            $errors['cost_price'] = 'Harga beli tidak boleh negatif.';
        }
        if ($data['sell_price'] < 0) {
            $errors['sell_price'] = 'Harga jual tidak boleh negatif.';
        }
        if ($data['tax_percent'] < 0 || $data['tax_percent'] > 100) {
            $errors['tax_percent'] = 'Pajak harus antara 0-100%.';
        }

        $sku = $data['sku'] !== '' ? $data['sku'] : Product::generateSku();
        if (Product::skuExists($sku, $exceptId)) {
            $errors['sku'] = 'SKU sudah digunakan.';
        }
        if ($data['barcode'] !== '' && Product::barcodeExists($data['barcode'], $exceptId)) {
            $errors['barcode'] = 'Barcode sudah digunakan produk lain.';
        }

        $data['sku'] = $sku;
        return [$data, $errors];
    }

    public static function store(): void
    {
        $data = self::collectInput();
        [$data, $errors] = self::validate($data);

        if (!empty($errors)) {
            $categories = Category::all(true);
            $units = Unit::all(true);
            $taxes = Tax::all(true);
            $old = $data;
            http_response_code(422);
            require dirname(__DIR__, 2) . '/views/products/form.php';
            return;
        }

        // Image upload (optional): stored under public/assets/img/products
        $imagePath = self::handleImageUpload();
        if ($imagePath) {
            $data['image_path'] = $imagePath;
        }

        $id = Product::create($data, (int) AuthService::id());
        AuditLog::record(AuthService::id(), 'product.create', 'product', $id, null, ['sku' => $data['sku'], 'name' => $data['name']]);

        Response::redirect('/products/index.php?created=1');
    }

    public static function update(int $id): void
    {
        $existing = Product::find($id);
        if (!$existing) {
            Response::redirect('/products/index.php?error=notfound');
        }

        $data = self::collectInput();
        [$data, $errors] = self::validate($data, $id);

        if (!empty($errors)) {
            $categories = Category::all(true);
            $units = Unit::all(true);
            $taxes = Tax::all(true);
            $old = array_merge($existing, $data);
            $old['id'] = $id;
            http_response_code(422);
            require dirname(__DIR__, 2) . '/views/products/form.php';
            return;
        }

        $imagePath = self::handleImageUpload();
        $data['image_path'] = $imagePath ?: $existing['image_path'];

        Product::update($id, $data);
        AuditLog::record(AuthService::id(), 'product.update', 'product', $id, $existing, $data);

        Response::redirect('/products/index.php?updated=1');
    }

    public static function toggleStatus(int $id): void
    {
        $existing = Product::find($id);
        if (!$existing) {
            Response::jsonError('Produk tidak ditemukan.', 404);
        }
        $newStatus = $existing['status'] === 'active' ? 'inactive' : 'active';
        Product::setStatus($id, $newStatus);
        AuditLog::record(AuthService::id(), 'product.status_change', 'product', $id, ['status' => $existing['status']], ['status' => $newStatus]);
        Response::jsonSuccess(['status' => $newStatus], 'Status produk berhasil diperbarui.');
    }

    public static function destroy(int $id): void
    {
        $existing = Product::find($id);
        if (!$existing) {
            Response::jsonError('Produk tidak ditemukan.', 404);
        }
        // Soft-delete only: a product referenced by historical sales/purchases
        // must never be hard-deleted, or those records would lose integrity.
        Product::softDelete($id);
        AuditLog::record(AuthService::id(), 'product.delete', 'product', $id, $existing, null);
        Response::jsonSuccess([], 'Produk berhasil dinonaktifkan dan dihapus dari daftar.');
    }

    public static function lookupBarcode(): void
    {
        $barcode = trim((string) ($_GET['barcode'] ?? ''));
        if ($barcode === '') {
            Response::jsonError('Barcode kosong.', 422);
        }
        $product = Product::findByBarcode($barcode);
        if (!$product) {
            Response::jsonError('Produk dengan barcode tersebut tidak ditemukan.', 404);
        }
        Response::jsonSuccess($product);
    }

    private static function handleImageUpload(): ?string
    {
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $tmpPath = $_FILES['image']['tmp_name'];
        $mime = mime_content_type($tmpPath) ?: '';

        if (!isset($allowed[$mime]) || $_FILES['image']['size'] > 2 * 1024 * 1024) {
            return null; // Silently ignore invalid uploads; product still saves without image.
        }

        $dir = dirname(__DIR__, 2) . '/public/assets/img/products';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $destination = $dir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $destination)) {
            return null;
        }

        return '/assets/img/products/' . $filename;
    }

    /**
     * Import produk from a CSV file. Expected header:
     * sku,barcode,name,category,unit,cost_price,sell_price,stock,min_stock,tax_percent,status
     */
    public static function importCsv(): void
    {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::jsonError('File CSV tidak ditemukan.', 422);
        }

        $handle = fopen($_FILES['file']['tmp_name'], 'r');
        if (!$handle) {
            Response::jsonError('File tidak dapat dibaca.', 422);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            Response::jsonError('File CSV kosong.', 422);
        }
        $header = array_map(static fn($h) => strtolower(trim((string) $h)), $header);

        $categoriesBySlug = [];
        foreach (Category::all() as $c) {
            $categoriesBySlug[strtolower($c['name'])] = (int) $c['id'];
        }
        $unitsBySymbol = [];
        foreach (Unit::all() as $u) {
            $unitsBySymbol[strtolower($u['symbol'])] = (int) $u['id'];
        }

        $imported = 0;
        $skipped = 0;
        $rowErrors = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $assoc = array_combine($header, array_pad($row, count($header), null));
            if (!$assoc) {
                $skipped++;
                continue;
            }

            $name = trim((string) ($assoc['name'] ?? ''));
            if ($name === '') {
                $rowErrors[] = "Baris {$rowNum}: nama produk kosong, dilewati.";
                $skipped++;
                continue;
            }

            $sku = trim((string) ($assoc['sku'] ?? ''));
            if ($sku === '') {
                $sku = Product::generateSku();
            } elseif (Product::skuExists($sku)) {
                $rowErrors[] = "Baris {$rowNum}: SKU '{$sku}' sudah ada, dilewati.";
                $skipped++;
                continue;
            }

            $barcode = trim((string) ($assoc['barcode'] ?? ''));
            if ($barcode !== '' && Product::barcodeExists($barcode)) {
                $barcode = '';
            }

            $data = [
                'sku'                  => $sku,
                'barcode'              => $barcode,
                'name'                 => $name,
                'category_id'          => $categoriesBySlug[strtolower(trim((string) ($assoc['category'] ?? '')))] ?? null,
                'unit_id'              => $unitsBySymbol[strtolower(trim((string) ($assoc['unit'] ?? '')))] ?? null,
                'cost_price'           => (float) ($assoc['cost_price'] ?? 0),
                'sell_price'           => (float) ($assoc['sell_price'] ?? 0),
                'wholesale_price'      => '',
                'wholesale_min_qty'    => '',
                'stock'                => (float) ($assoc['stock'] ?? 0),
                'min_stock'            => (float) ($assoc['min_stock'] ?? 0),
                'tax_percent'          => (float) ($assoc['tax_percent'] ?? 0),
                'tax_inclusive'        => 0,
                'status'               => in_array($assoc['status'] ?? 'active', ['active', 'inactive'], true) ? $assoc['status'] : 'active',
                'description'          => '',
                'allow_negative_stock' => 0,
            ];

            Product::create($data, (int) AuthService::id());
            $imported++;
        }
        fclose($handle);

        AuditLog::record(AuthService::id(), 'product.import', 'product', null, null, ['imported' => $imported, 'skipped' => $skipped]);

        Response::jsonSuccess([
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $rowErrors,
        ], "Import selesai: {$imported} produk ditambahkan, {$skipped} dilewati.");
    }

    /**
     * Export all products (not soft-deleted) to a downloadable CSV.
     */
    public static function exportCsv(): void
    {
        $products = Product::allActive();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="azared-produk-' . date('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['sku', 'name', 'cost_price', 'sell_price', 'stock']);
        foreach ($products as $p) {
            fputcsv($out, [$p['sku'], $p['name'], $p['cost_price'], $p['sell_price'], $p['stock']]);
        }
        fclose($out);
        exit;
    }
}
