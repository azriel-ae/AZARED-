<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Store;

final class ExpenseController
{
    private const PER_PAGE = 20;

    public static function index(): void
    {
        $filters = [
            'search'         => trim((string) ($_GET['search'] ?? '')),
            'category_id'    => (int) ($_GET['category_id'] ?? 0),
            'payment_method' => (string) ($_GET['payment_method'] ?? ''),
            'date_from'      => (string) ($_GET['date_from'] ?? ''),
            'date_to'        => (string) ($_GET['date_to'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = Expense::paginate($filters, $page, self::PER_PAGE);
        $expenses = $result['rows'];
        $total = $result['total'];
        $totalPages = (int) max(1, ceil($total / self::PER_PAGE));
        $categories = ExpenseCategory::all(true);
        $periodTotal = array_sum(array_column($expenses, 'amount'));

        require dirname(__DIR__, 2) . '/views/expenses/index.php';
    }

    public static function show(int $id): void
    {
        $expense = Expense::find($id);
        if (!$expense) {
            Response::redirect('/expenses/index.php?error=notfound');
        }
        require dirname(__DIR__, 2) . '/views/expenses/show.php';
    }

    public static function createForm(): void
    {
        $categories = ExpenseCategory::all(true);
        $stores = Store::all();
        $errors = [];
        $old = [];
        require dirname(__DIR__, 2) . '/views/expenses/form.php';
    }

    public static function editForm(int $id): void
    {
        $expense = Expense::find($id);
        if (!$expense) {
            Response::redirect('/expenses/index.php?error=notfound');
        }
        $categories = ExpenseCategory::all(true);
        $stores = Store::all();
        $errors = [];
        $old = $expense;
        require dirname(__DIR__, 2) . '/views/expenses/form.php';
    }

    private static function collectInput(): array
    {
        return [
            'store_id'       => (int) ($_POST['store_id'] ?? 0),
            'category_id'    => (int) ($_POST['category_id'] ?? 0),
            'description'    => trim((string) ($_POST['description'] ?? '')),
            'amount'         => (float) ($_POST['amount'] ?? 0),
            'payment_method' => (string) ($_POST['payment_method'] ?? 'cash'),
            'expense_date'   => (string) ($_POST['expense_date'] ?? date('Y-m-d')),
            'notes'          => trim((string) ($_POST['notes'] ?? '')),
        ];
    }

    private static function validate(array $data): array
    {
        $validator = new Validator($data);
        $validator->required('description', 'Deskripsi')->maxLength('description', 255, 'Deskripsi')
            ->in('payment_method', ['cash', 'transfer', 'debit', 'credit', 'ewallet', 'qris', 'other'], 'Metode Pembayaran');

        $errors = $validator->errors();
        if ($data['category_id'] <= 0 || !ExpenseCategory::find($data['category_id'])) {
            $errors['category_id'] = 'Kategori tidak valid.';
        }
        if ($data['amount'] <= 0) {
            $errors['amount'] = 'Jumlah harus lebih dari 0.';
        }
        if ($data['expense_date'] === '' || !strtotime($data['expense_date'])) {
            $errors['expense_date'] = 'Tanggal tidak valid.';
        }

        return $errors;
    }

    public static function store(): void
    {
        $data = self::collectInput();
        $errors = self::validate($data);

        if (!empty($errors)) {
            $categories = ExpenseCategory::all(true);
            $stores = Store::all();
            $old = $data;
            http_response_code(422);
            require dirname(__DIR__, 2) . '/views/expenses/form.php';
            return;
        }

        $id = Expense::create($data, (int) AuthService::id());
        AuditLog::record(AuthService::id(), 'expense.create', 'expense', $id, null, $data);

        Response::redirect('/expenses/index.php?created=1');
    }

    public static function update(int $id): void
    {
        $existing = Expense::find($id);
        if (!$existing) {
            Response::redirect('/expenses/index.php?error=notfound');
        }

        $data = self::collectInput();
        $errors = self::validate($data);

        if (!empty($errors)) {
            $categories = ExpenseCategory::all(true);
            $stores = Store::all();
            $old = array_merge($existing, $data);
            $old['id'] = $id;
            http_response_code(422);
            require dirname(__DIR__, 2) . '/views/expenses/form.php';
            return;
        }

        Expense::update($id, $data);
        AuditLog::record(AuthService::id(), 'expense.update', 'expense', $id, $existing, $data);

        Response::redirect('/expenses/index.php?updated=1');
    }

    public static function destroy(int $id): void
    {
        $existing = Expense::find($id);
        if (!$existing) {
            Response::jsonError('Pengeluaran tidak ditemukan.', 404);
        }
        Expense::softDelete($id);
        AuditLog::record(AuthService::id(), 'expense.delete', 'expense', $id, $existing, null);
        Response::jsonSuccess([], 'Pengeluaran berhasil dihapus.');
    }

    /**
     * Export the currently-filtered expense list to CSV.
     */
    public static function exportCsv(): void
    {
        $filters = [
            'search'         => trim((string) ($_GET['search'] ?? '')),
            'category_id'    => (int) ($_GET['category_id'] ?? 0),
            'payment_method' => (string) ($_GET['payment_method'] ?? ''),
            'date_from'      => (string) ($_GET['date_from'] ?? ''),
            'date_to'        => (string) ($_GET['date_to'] ?? ''),
        ];
        $rows = Expense::reportAll($filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="azared-pengeluaran-' . date('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['No. Pengeluaran', 'Tanggal', 'Kategori', 'Deskripsi', 'Jumlah', 'Metode Pembayaran', 'Dicatat Oleh', 'Catatan']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['expense_no'], $r['expense_date'], $r['category_name'], $r['description'],
                $r['amount'], $r['payment_method'], $r['user_name'], $r['notes'],
            ]);
        }
        fclose($out);
        exit;
    }
}
