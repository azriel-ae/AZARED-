<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Controllers\UserController;
use App\Middleware\PermissionMiddleware;

PermissionMiddleware::require('users.create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

UserController::store();
