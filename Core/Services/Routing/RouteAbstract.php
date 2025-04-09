<?php

namespace Core\Services\Routing;

use Core\Services\Auth\Auth;

abstract class RouteAbstract
{
    public static string $module = '';
    private static string $prefix = '';

    public static function add(string $method, string $uri, array $options): bool
    {
        if (isset($options['protected']) && !static::checkProtection($options['protected'])) {
            return false;
        }

        if (static::validateOptions($options)) {
            $options['module'] = $options['module'] ?? static::$module;
            Repository::store($method, static::prefixed($uri), $options);
            return true;
        }

        return false;
    }

    private static function checkProtection(array $protection): bool
    {
        $user = Auth::user();

        if (in_array('all', $protection)) {
            return true;
        }

        if ($user === false) {
            return in_array('guest', $protection);
        }

        if ($user->role === 'admin') {
            return true;
        }

        return match ($user->Status ?? null) {
            'pending' => in_array('pending', $protection),
            default   => in_array('user', $protection),
        };
    }

    private static function prefixed(string $uri): string
    {
        $uri = trim($uri, '/');
        return self::$prefix ? trim(self::$prefix, '/') . '/' . $uri : $uri;
    }

    private static function validateOptions(array $options): bool
    {
        return isset($options['controller'], $options['action']);
    }
}