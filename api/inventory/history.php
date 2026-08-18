<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
use App\Controllers\InventoryController;
use App\Middleware\PermissionMiddleware;
PermissionMiddleware::require('inventory.view', true);
$id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); exit('ID produk tidak valid.'); }
InventoryController::history($id);
