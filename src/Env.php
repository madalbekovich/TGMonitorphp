<?php

namespace App;

class Env
{
    private static array $env = [];

    public static function load(string $path = '.env'): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Файл .env не найден");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                self::$env[trim($key)] = trim($value);
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$env[$key] ?? $default;
    }
} 