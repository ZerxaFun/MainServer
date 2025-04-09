<?php

namespace Core\Services\Routing;

class Repository
{
    private static array $stored = [
        'get' => [], 'post' => [], 'put' => [], 'patch' => [], 'delete' => [], 'cli' => []
    ];

    public static array $storedLanguage = [
        'get' => [], 'post' => [], 'put' => [], 'patch' => [], 'delete' => [], 'cli' => []
    ];

    public static function store(string $method, string $uri, array $options): void
    {
        self::$stored[$method][$uri] = $options;
    }

    public static function retrieve(string $method, string $uri): array
    {
        return self::$stored[$method][$uri] ?? [];
    }

    public static function remove(string $method, string $uri): bool
    {
        if (isset(self::$stored[$method][$uri])) {
            unset(self::$stored[$method][$uri]);
            return true;
        }
        return false;
    }

    public static function stored(): array
    {
        return self::$stored;
    }
}