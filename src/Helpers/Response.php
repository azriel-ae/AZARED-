<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * AZARED - simple response helpers (JSON output, redirects, escaping).
 */
final class Response
{
    public static function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function jsonError(string $message, int $statusCode = 400, array $errors = []): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $statusCode);
    }

    public static function jsonSuccess(mixed $data = [], string $message = 'OK'): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], 200);
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . $path, true, 302);
        exit;
    }

    /**
     * Escape output for safe HTML rendering (XSS protection).
     */
    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
