<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
use App\Controllers\ProductController;
use App\Middleware\PermissionMiddleware;
PermissionMiddleware::require('products.edit');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); exit('ID produk tidak valid.'); }
ProductController::editForm($id);
