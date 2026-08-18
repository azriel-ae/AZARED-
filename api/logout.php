<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Controllers\AuthController;
use App\Middleware\CsrfMiddleware;

CsrfMiddleware::verify();
AuthController::logout();
