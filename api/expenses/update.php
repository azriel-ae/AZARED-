<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
use App\Controllers\ExpenseController;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PermissionMiddleware;
PermissionMiddleware::require('expenses.edit');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); exit('ID pengeluaran tidak valid.'); }
CsrfMiddleware::verify();
ExpenseController::update($id);
