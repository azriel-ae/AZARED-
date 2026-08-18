<?php
/**
 * AZARED - Bootstrap
 *
 * This file is required at the top of every public entry point / API
 * endpoint. It wires up config, autoloading, DB-backed sessions
 * (required because Vercel functions are stateless/serverless, so we
 * cannot rely on PHP's default filesystem session storage), and a
 * baseline of security headers.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/database.php';

use App\Auth\PdoSessionHandler;

// ---- Baseline security headers ----
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:;");
if ((bool) config('db.ssl') || (($_SERVER['HTTPS'] ?? '') === 'on')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// ---- Secure session configuration ----
$lifetimeSeconds = ((int) config('session.lifetime_minutes', 120)) * 60;

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', (string) $lifetimeSeconds);

session_set_cookie_params([
    'lifetime' => $lifetimeSeconds,
    'path'     => '/',
    'domain'   => '',
    'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Use a MySQL-backed session handler instead of the local filesystem.
// This is required for correctness on serverless platforms like Vercel,
// where each invocation may run in a different ephemeral container and
// the local disk is not shared/persistent across requests.
$sessionHandler = new PdoSessionHandler(\App\Database::connection(), $lifetimeSeconds);
session_set_save_handler($sessionHandler, true);

session_name('azared_session');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ---- Idle timeout enforcement ----
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $lifetimeSeconds) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

require_once __DIR__ . '/../src/Helpers/Csrf.php';
require_once __DIR__ . '/../src/Helpers/Response.php';
require_once __DIR__ . '/../src/Helpers/Validator.php';
require_once __DIR__ . '/../src/Helpers/ErrorHandler.php';

// Global safety net: any uncaught exception, PHP warning promoted to an
// exception, or fatal error from this point on is logged in full detail
// server-side and shown to the client only as a generic 500 response -
// never a stack trace, file path, or query. See ErrorHandler for details.
\App\Helpers\ErrorHandler::register();
