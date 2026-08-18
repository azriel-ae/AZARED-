<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Helpers\Csrf;
use App\Helpers\Response;

final class AuthController
{
    public static function showLoginForm(): void
    {
        if (AuthService::check()) {
            Response::redirect('/dashboard');
        }
        require dirname(__DIR__, 2) . '/views/auth/login.php';
    }

    public static function login(): void
    {
        \App\Middleware\CsrfMiddleware::verify();

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
            require dirname(__DIR__, 2) . '/views/auth/login.php';
            return;
        }

        $result = AuthService::attemptLogin($username, $password);

        if (!$result['success']) {
            http_response_code(!empty($result['locked']) ? 429 : 401);
            $error = $result['message'];
            require dirname(__DIR__, 2) . '/views/auth/login.php';
            return;
        }

        Response::redirect('/dashboard');
    }

    public static function logout(): void
    {
        AuthService::logout();
        Response::redirect('/login.php');
    }
}
