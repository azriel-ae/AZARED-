<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
use App\Controllers\AuditLogController;
use App\Middleware\PermissionMiddleware;
PermissionMiddleware::require('audit.view');
AuditLogController::index();
