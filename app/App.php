<?php

declare(strict_types=1);

namespace App;

use App\Data\DataService;
use App\Data\RedisLocalAdapter;

/**
 * Penyimpan konfigurasi dan layanan bersama (service locator sederhana).
 * Sengaja dibuat kecil agar mudah dipahami.
 */
final class App
{
    private static string $root = '';
    /** @var array<string,mixed> */
    private static array $config = [];
    private static ?DataService $data = null;

    /** @param array<string,mixed> $config */
    public static function init(string $root, array $config): void
    {
        self::$root = $root;
        self::$config = $config;
        self::$data = null;
    }

    public static function root(): string
    {
        return self::$root;
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /** @return array<string,mixed> */
    public static function allConfig(): array
    {
        return self::$config;
    }

    /** Layanan data (Redis lokal), dibuat saat pertama dipakai. */
    public static function data(): DataService
    {
        if (self::$data === null) {
            self::$data = RedisLocalAdapter::fromConfig(self::$config);
        }
        return self::$data;
    }

    /** Dipakai oleh tes untuk menyuntikkan adapter tertentu. */
    public static function setData(DataService $data): void
    {
        self::$data = $data;
    }
}
