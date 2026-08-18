<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Auth\AuthService;
use App\Helpers\Response;

Response::redirect(AuthService::check() ? '/dashboard.php' : '/login.php');
