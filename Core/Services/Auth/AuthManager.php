<?php

namespace Core\Services\Auth;

use Core\Services\Http\Request;

class AuthManager
{
    protected static array $guards = [];

    public static function registerGuard(string $name, GuardInterface $guard): void
    {
        self::$guards[$name] = $guard;
    }

    public static function getGuard(string $name): GuardInterface
    {
        if (!isset(self::$guards[$name])) {
            throw new \RuntimeException('Guard [{$name}] not registered.');
        }

        return self::$guards[$name];
    }


    public static function extend(string $name, GuardInterface $guard): void
    {
        static::$guards[$name] = $guard;
    }

    public static function guard(string $name): GuardInterface
    {
        return static::$guards[$name] ?? throw new \Exception("Guard [$name] not registered.");
    }

    public static function user(Request $request, string $guard = 'jwt'): object|null
    {
        return static::guard($guard)->user($request);
    }

    public static function check(Request $request, string $guard = 'jwt'): bool
    {
        return static::guard($guard)->check($request);
    }
}
