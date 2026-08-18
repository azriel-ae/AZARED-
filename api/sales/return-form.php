<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
use App\Controllers\SaleController;
use App\Middleware\PermissionMiddleware;
PermissionMiddleware::require('sales.return');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); exit('ID transaksi tidak valid.'); }
SaleController::returnForm($id);
