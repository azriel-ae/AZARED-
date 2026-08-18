<?php
/**
 * AZARED - Lightweight PSR-4-style autoloader
 *
 * We deliberately avoid a hard Composer dependency so the app can run
 * on Vercel's PHP runtime without a mandatory `composer install` build
 * step. If you later want Composer-managed packages (e.g. PHPMailer),
 * you can still `require vendor/autoload.php` alongside this file.
 *
 * Namespace map:
 *   App\Auth\...        -> src/Auth/...
 *   App\Middleware\...  -> src/Middleware/...
 *   App\Models\...      -> src/Models/...
 *   App\Controllers\... -> src/Controllers/...
 *   App\Helpers\...     -> src/Helpers/...
 */

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relativePath;

    if (is_file($file)) {
        require_once $file;
    }
});
