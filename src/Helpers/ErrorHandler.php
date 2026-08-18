<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * AZARED - centralized error handling.
 *
 * Registers a PHP error handler (converts warnings/notices to
 * exceptions so they funnel through one place), an uncaught-exception
 * handler, and a shutdown handler (catches fatal errors that bypass
 * both of the above). All three do the same two things:
 *   1. Log the FULL detail (message, file, line, trace) server-side
 *      via error_log() - this is what the ops/dev team sees.
 *   2. Show the CLIENT only a generic message via the 500 error page
 *      (or a generic JSON envelope for AJAX/API calls) - never a raw
 *      stack trace, file path, SQL query, or credential.
 *
 * This must never be relied on as a substitute for proper try/catch
 * around expected failure modes (e.g. Sale::checkout() already catches
 * its own RuntimeException/Throwable) - it is a last-resort safety net.
 */
final class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        // Respect the @-operator / error_reporting() masks, same as PHP's default behavior.
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(\Throwable $e): void
    {
        self::logDetailed($e);
        self::respond();
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatalTypes, true)) {
            return;
        }

        error_log(sprintf(
            '[AZARED][FATAL] %s in %s:%d',
            $error['message'],
            $error['file'],
            $error['line']
        ));
        self::respond();
    }

    private static function logDetailed(\Throwable $e): void
    {
        error_log(sprintf(
            '[AZARED][EXCEPTION] %s: %s in %s:%d' . "\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }

    private static function respond(): void
    {
        if (headers_sent()) {
            return;
        }
        http_response_code(500);

        $wantsJson = self::requestWantsJson();
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem. Silakan coba lagi.',
                'errors'  => [],
            ]);
            return;
        }

        $viewPath = dirname(__DIR__, 2) . '/views/errors/500.php';
        if (is_file($viewPath)) {
            require $viewPath;
        } else {
            echo 'Terjadi kesalahan pada sistem. Silakan coba lagi.';
        }
    }

    private static function requestWantsJson(): bool
    {
        // NOTE: this app physically serves both full HTML pages and JSON
        // AJAX endpoints from under /api/*.php (see vercel.json), so the
        // script path alone can't tell them apart - only rely on explicit
        // AJAX signals the frontend actually sends (ajax-forms.js / pos.js
        // always set X-Requested-With, and fetch() defaults to accepting
        // JSON responses).
        $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return strcasecmp($xrw, 'XMLHttpRequest') === 0
            || str_contains($accept, 'application/json');
    }
}
