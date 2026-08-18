<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AuthService;
use App\Helpers\Response;

/**
 * AZARED - server-side permission enforcement.
 * This is the ONLY source of truth for authorization. The frontend may
 * hide buttons/menus for convenience, but every privileged action MUST
 * be re-checked here so a manipulated client can never bypass it.
 */
final class PermissionMiddleware
{
    public static function require(string $permissionSlug, bool $isApi = false): void
    {
        AuthMiddleware::handle($isApi);

        if (!AuthService::hasPermission($permissionSlug)) {
            if ($isApi) {
                Response::jsonError('Anda tidak memiliki izin untuk melakukan aksi ini.', 403);
            }
            Response::redirect('/403.php');
        }
    }

    public static function requireAny(array $permissionSlugs, bool $isApi = false): void
    {
        AuthMiddleware::handle($isApi);

        foreach ($permissionSlugs as $slug) {
            if (AuthService::hasPermission($slug)) {
                return;
            }
        }

        if ($isApi) {
            Response::jsonError('Anda tidak memiliki izin untuk melakukan aksi ini.', 403);
        }
        Response::redirect('/403.php');
    }
}
