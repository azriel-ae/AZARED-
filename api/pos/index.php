<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
use App\Controllers\PosController;
use App\Middleware\PermissionMiddleware;
PermissionMiddleware::require('pos.access');
PosController::index();
