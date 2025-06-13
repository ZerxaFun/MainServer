<?php


namespace Core\Services\Session\Facades;

use Core\Services\Session\SessionManager;

class Session
{
    public static function initialize(): void
    {
        SessionManager::start();
    }

    public static function put(string $key, mixed $value): void
    {
        SessionManager::driver()->put($key, $value);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return SessionManager::driver()->get($key, $default);
    }

    public static function has(string $key): bool
    {
        return SessionManager::driver()->has($key);
    }

    public static function forget(string $key): void
    {
        SessionManager::driver()->forget($key);
    }

    public static function flush(): void
    {
        SessionManager::driver()->flush();
    }

    public static function all(): array
    {
        return SessionManager::driver()->all();
    }

    public static function id(): string
    {
        return SessionManager::driver()->id();
    }

    public static function regenerate(): void
    {
        SessionManager::driver()->regenerate();
    }

    public static function destroy(): void
    {
        SessionManager::driver()->destroy();
    }
}