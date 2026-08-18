<?php
/**
 * AZARED - Core Application Configuration
 *
 * Loads environment variables (from real environment on Vercel,
 * or from a local .env file during local development) and exposes
 * a single `config()` helper for reading them safely.
 *
 * IMPORTANT: No credentials are ever hardcoded here. Everything comes
 * from environment variables so the same codebase works locally and
 * on Vercel simply by changing the env configuration.
 */

declare(strict_types=1);

// ---- Load .env for local development only (Vercel injects real env vars) ----
if (!function_exists('azared_load_dotenv')) {
    function azared_load_dotenv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Strip surrounding quotes if present
            if (strlen($value) > 1 && $value[0] === '"' && $value[-1] === '"') {
                $value = substr($value, 1, -1);
            }
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}

azared_load_dotenv(dirname(__DIR__) . '/.env');

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        $lower = strtolower($value);
        return match ($lower) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $value,
        };
    }
}

$GLOBALS['__azared_config'] = [
    'app' => [
        'name'      => env('APP_NAME', 'AZARED'),
        'env'       => env('APP_ENV', 'production'),
        'debug'     => (bool) env('APP_DEBUG', false),
        'url'       => env('APP_URL', ''),
        'timezone'  => env('APP_TIMEZONE', 'Asia/Jakarta'),
        'key'       => env('APP_KEY', ''),
    ],
    'db' => [
        'host'     => env('DB_HOST', '127.0.0.1'),
        'port'     => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'azared'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'ssl'      => (bool) env('DB_SSL', false),
    ],
    'session' => [
        'lifetime_minutes'    => (int) env('SESSION_LIFETIME_MINUTES', 120),
        'remember_me_days'    => (int) env('REMEMBER_ME_DAYS', 14),
        'csrf_token_name'     => env('CSRF_TOKEN_NAME', 'azared_csrf_token'),
    ],
    'login' => [
        'max_attempts'     => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes'  => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
    ],
];

if (!function_exists('config')) {
    /**
     * Dot-notation config getter, e.g. config('db.host')
     */
    function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $GLOBALS['__azared_config'];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}

date_default_timezone_set((string) config('app.timezone', 'Asia/Jakarta'));

if (config('app.debug') === true) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
