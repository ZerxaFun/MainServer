<?php

namespace Core\Routing;

class Route extends RouteAbstract
{
    public static function get(string $uri, array $options = []): bool
    {
        return static::add('get', $uri, $options);
    }

    public static function post(string $uri, array $options = []): bool
    {
        return static::add('post', $uri, $options);
    }

    public static function put(string $uri, array $options = []): bool
    {
        return static::add('put', $uri, $options);
    }

    public static function patch(string $uri, array $options = []): bool
    {
        return static::add('patch', $uri, $options);
    }

    public static function delete(string $uri, array $options = []): bool
    {
        return static::add('delete', $uri, $options);
    }

    public static function cli(string $uri, array $options = []): bool
    {
        return static::add('cli', $uri, $options);
    }
}