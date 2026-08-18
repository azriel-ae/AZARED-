<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Helpers\Response;
use App\Middleware\PermissionMiddleware;
use App\Models\User;

/**
 * AJAX endpoint: GET /api/check-username.php?username=xxx
 * Used by the "create user" form to give live feedback before submit.
 * Still requires users.create permission - never expose user existence
 * data to unauthenticated or unauthorized callers.
 */
PermissionMiddleware::require('users.create', true);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::jsonError('Method not allowed', 405);
}

$username = trim((string) ($_GET['username'] ?? ''));

if ($username === '' || !preg_match('/^[a-zA-Z0-9_.]{4,50}$/', $username)) {
    Response::jsonError('Username tidak valid.', 422);
}

Response::jsonSuccess(['available' => !User::usernameExists($username)]);
