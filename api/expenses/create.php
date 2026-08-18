<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
use App\Controllers\ExpenseController;
use App\Middleware\PermissionMiddleware;
PermissionMiddleware::require('expenses.create');
ExpenseController::createForm();
