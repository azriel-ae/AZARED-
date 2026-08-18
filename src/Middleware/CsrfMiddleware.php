<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Csrf;

/**
 * AZARED - verifies CSRF token on every state-changing request.
 */
final class CsrfMiddleware
{
    public static function verify(): void
    {
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            Csrf::verifyRequestOrFail();
        }
    }
}
