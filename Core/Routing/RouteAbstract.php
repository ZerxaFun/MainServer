<?php

namespace Core\Routing;


abstract class RouteAbstract
{
    public static string $module = '';
    private static string $prefix = '';

    public static function add(string $method, string $uri, array $options): bool
    {
        if (static::validateOptions($options)) {
            $options['module'] = $options['module'] ?? static::$module;
            Repository::store($method, static::prefixed($uri), $options);
            return true;
        }

        return false;
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