<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
use App\Controllers\StoreController;
use App\Middleware\PermissionMiddleware;
PermissionMiddleware::require('stores.manage');
StoreController::index();
