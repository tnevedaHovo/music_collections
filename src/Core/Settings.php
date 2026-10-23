<?php
declare(strict_types=1);

namespace App\Core;

final class Settings
{
    private static ?array $cache = null;

    public static function get(string $key, string $default = ''): string
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function json(string $key): array
    {
        $v = json_decode(self::get($key), true);
        return is_array($v) ? $v : [];
    }

    public static function set(string $key, string|array|null $value): void
    {
        self::load();
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        Db::run(
            'INSERT INTO settings (key, value) VALUES (:k, :v) ON CONFLICT(key) DO UPDATE SET value = excluded.value',
            ['k' => $key, 'v' => $value]
        );
        self::$cache[$key] = (string) $value;
    }

    private static function load(): void
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Db::all('SELECT key, value FROM settings') as $row) {
                self::$cache[$row['key']] = (string) $row['value'];
            }
        }
    }
}
