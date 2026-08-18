<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * AZARED - simple key/value app settings (Pengaturan), backed by the
 * `app_settings` table (migration_006_settings.sql). Deliberately kept
 * to a small, curated set of keys that are actually read somewhere in
 * the app (see SettingsController::ALLOWED_KEYS) - this is not a
 * free-form config bag, so every setting exposed in the UI has a real
 * effect.
 */
final class AppSetting
{
    public static function get(string $key, string $default = ''): string
    {
        $stmt = Database::connection()->prepare('SELECT value FROM app_settings WHERE `key` = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    /** @return array<string,string> */
    public static function allAsMap(): array
    {
        $stmt = Database::connection()->query('SELECT `key`, value FROM app_settings');
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['key']] = $row['value'];
        }
        return $map;
    }

    public static function set(string $key, string $value): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO app_settings (`key`, value) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        $stmt->execute(['key' => $key, 'value' => $value]);
    }

    /** @param array<string,string> $values */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::set($key, $value);
        }
    }
}
