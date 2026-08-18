<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * AZARED - CSRF protection helper.
 * Token is stored in the (DB-backed) PHP session and must be sent back
 * by the client on every state-changing request (POST/PUT/PATCH/DELETE).
 */
final class Csrf
{
    public static function token(): string
    {
        $name = (string) config('session.csrf_token_name', 'azared_csrf_token');
        if (empty($_SESSION[$name])) {
            $_SESSION[$name] = bin2hex(random_bytes(32));
        }
        return $_SESSION[$name];
    }

    public static function field(): string
    {
        $name = (string) config('session.csrf_token_name', 'azared_csrf_token');
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . $name . '" value="' . $token . '">';
    }

    public static function verify(?string $submittedToken): bool
    {
        $name = (string) config('session.csrf_token_name', 'azared_csrf_token');
        $sessionToken = $_SESSION[$name] ?? null;

        if (!$sessionToken || !$submittedToken) {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    public static function verifyRequestOrFail(): void
    {
        $tokenName = (string) config('session.csrf_token_name', 'azared_csrf_token');
        $submitted = $_POST[$tokenName] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        if (!self::verify($submitted)) {
            http_response_code(419);

            $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (strcasecmp($xrw, 'XMLHttpRequest') === 0 || str_contains($accept, 'application/json')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'Sesi keamanan formulir sudah kedaluwarsa. Silakan muat ulang halaman dan coba lagi.',
                    'errors'  => [],
                ]);
                exit;
            }

            $viewPath = dirname(__DIR__, 2) . '/views/errors/419.php';
            if (is_file($viewPath)) {
                require $viewPath;
            } else {
                echo 'CSRF token tidak valid atau kedaluwarsa. Silakan muat ulang halaman.';
            }
            exit;
        }
    }
}
