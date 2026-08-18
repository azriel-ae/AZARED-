<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database;
use App\Helpers\RateLimiter;
use App\Models\LoginLog;
use App\Models\User;

/**
 * AZARED - Authentication service.
 * Handles login, logout, and session identity checks.
 */
final class AuthService
{
    /** Per-request memo so we only hit the DB once per request even if
     *  multiple permission checks happen (e.g. requireAny with several
     *  slugs), while still never trusting week-old session data. */
    private static bool $refreshed = false;

    public static function attemptLogin(string $username, string $password): array
    {
        $db = Database::connection();
        $limiter = new RateLimiter($db);

        if ($limiter->isLocked($username)) {
            LoginLog::record(null, $username, 'failed', 'account_locked');
            return ['success' => false, 'locked' => true, 'message' => 'Akun terkunci sementara karena terlalu banyak percobaan gagal. Coba lagi nanti.'];
        }

        $user = User::findByUsername($username);

        if (!$user) {
            // Still run password_verify against a dummy hash to avoid timing
            // attacks that could reveal whether a username exists.
            password_verify($password, '$2y$12$abcdefghijklmnopqrstuuVXWyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
            LoginLog::record(null, $username, 'failed', 'user_not_found');
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }

        if ($user['status'] !== 'active') {
            LoginLog::record((int) $user['id'], $username, 'failed', 'account_inactive');
            return ['success' => false, 'message' => 'Akun tidak aktif. Hubungi administrator.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $limiter->registerFailedAttempt($username);
            LoginLog::record((int) $user['id'], $username, 'failed', 'invalid_password');
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }

        // Success: reset attempts, regenerate session ID (prevents session fixation)
        $limiter->resetAttempts($username);
        User::recordSuccessfulLogin((int) $user['id'], $_SERVER['REMOTE_ADDR'] ?? '');
        LoginLog::record((int) $user['id'], $username, 'success', null);

        session_regenerate_id(true);

        $_SESSION['user_id']    = (int) $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['full_name']  = $user['full_name'];
        $_SESSION['roles']      = array_column(User::roles((int) $user['id']), 'slug');
        $_SESSION['permissions'] = User::permissions((int) $user['id']);
        $_SESSION['must_change_password'] = (bool) $user['must_change_password'];
        $_SESSION['last_activity'] = time();

        return ['success' => true, 'user' => $user];
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Re-fetches the current user's status, roles, and permissions from
     * the database (once per request) and overwrites the session cache
     * with the fresh values.
     *
     * This closes two real gaps that existed when permissions/roles were
     * only ever cached at login time:
     *   1. A user whose role/permissions were changed by an admin kept
     *      their OLD permissions for the rest of their session (up to the
     *      full session lifetime) instead of the change taking effect
     *      immediately.
     *   2. A user who was deactivated/suspended mid-session could keep
     *      using the app until their session naturally expired, because
     *      nothing ever re-checked `status` after login.
     *
     * Returns false (and destroys the session) if the account no longer
     * exists or is no longer active, so the caller can force a re-login.
     */
    public static function refreshIfNeeded(): bool
    {
        if (!self::check()) {
            return false;
        }
        if (self::$refreshed) {
            return true;
        }
        self::$refreshed = true;

        $user = User::findById((int) $_SESSION['user_id']);
        if (!$user || $user['status'] !== 'active') {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            return false;
        }

        $_SESSION['full_name']  = $user['full_name'];
        $_SESSION['roles']      = array_column(User::roles((int) $user['id']), 'slug');
        $_SESSION['permissions'] = User::permissions((int) $user['id']);
        $_SESSION['must_change_password'] = (bool) $user['must_change_password'];

        return true;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function hasPermission(string $permissionSlug): bool
    {
        $permissions = $_SESSION['permissions'] ?? [];
        return in_array($permissionSlug, $permissions, true);
    }

    public static function hasRole(string $roleSlug): bool
    {
        $roles = $_SESSION['roles'] ?? [];
        return in_array($roleSlug, $roles, true);
    }
}
