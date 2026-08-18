<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AuthService;
use App\Helpers\Response;

/**
 * AZARED - ensures the request comes from an authenticated user.
 * Call AuthMiddleware::handle() at the top of any protected page/endpoint.
 */
final class AuthMiddleware
{
    public static function handle(bool $isApi = false): void
    {
        if (!AuthService::check()) {
            if ($isApi) {
                Response::jsonError('Sesi tidak valid atau telah berakhir. Silakan login kembali.', 401);
            }
            Response::redirect('/login.php');
        }

        // Re-verify the account is still active and pull fresh
        // roles/permissions from the database on every request - see
        // AuthService::refreshIfNeeded() for why this matters.
        if (!AuthService::refreshIfNeeded()) {
            if ($isApi) {
                Response::jsonError('Akun Anda tidak lagi aktif atau sesi tidak valid. Silakan login kembali.', 401);
            }
            Response::redirect('/login.php');
        }
    }
}
