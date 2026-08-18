<?php
/**
 * AZARED - Database Connection (PDO Singleton)
 *
 * All database access in the app MUST go through Database::connection()
 * so we always get a single, correctly-configured PDO instance using
 * prepared statements (no raw string interpolation anywhere).
 */

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instance = null;

    private function __construct()
    {
        // Prevent instantiation
    }

    public static function connection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host     = (string) config('db.host');
        $port     = (string) config('db.port');
        $database = (string) config('db.database');
        $username = (string) config('db.username');
        $password = (string) config('db.password');
        $useSsl   = (bool) config('db.ssl');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
            PDO::ATTR_PERSISTENT         => false, // serverless: no persistent conn pooling
        ];

        if ($useSsl && defined('PDO::MYSQL_ATTR_SSL_CA')) {
            // If your managed MySQL provider requires TLS, point this to a CA bundle
            // shipped with the app (read-only file), e.g. config/certs/ca-cert.pem
            $options[PDO::MYSQL_ATTR_SSL_CA] = dirname(__DIR__) . '/config/certs/ca-cert.pem';
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

        try {
            self::$instance = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // Never leak DB credentials or raw exception details to the client
            error_log('[AZARED][DB] Connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Database connection error. Please try again later.');
        }

        return self::$instance;
    }
}
