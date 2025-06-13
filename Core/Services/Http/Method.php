<?php

declare(strict_types=1);

namespace Core\Services\Http;

/**
 * Класс Method предоставляет информацию о текущем HTTP-методе запроса.
 */
class Method
{
    /**
     * Возвращает текущий HTTP-метод (GET, POST, PUT, DELETE и т.д.)
     *
     * @return string
     */
    public static function current(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Проверка, является ли текущий метод POST
     *
     * @return bool
     */
    public static function isPost(): bool
    {
        return self::current() === 'POST';
    }

    /**
     * Проверка, является ли текущий метод GET
     *
     * @return bool
     */
    public static function isGet(): bool
    {
        return self::current() === 'GET';
    }

    /**
     * Проверка, является ли текущий метод PUT
     *
     * @return bool
     */
    public static function isPut(): bool
    {
        return self::current() === 'PUT';
    }

    /**
     * Проверка, является ли текущий метод DELETE
     *
     * @return bool
     */
    public static function isDelete(): bool
    {
        return self::current() === 'DELETE';
    }

    /**
     * Проверка, является ли текущий метод PATCH
     *
     * @return bool
     */
    public static function isPatch(): bool
    {
        return self::current() === 'PATCH';
    }

    /**
     * Проверка, является ли текущий метод OPTIONS
     *
     * @return bool
     */
    public static function isOptions(): bool
    {
        return self::current() === 'OPTIONS';
    }

    /**
     * Проверка, является ли текущий метод HEAD
     *
     * @return bool
     */
    public static function isHead(): bool
    {
        return self::current() === 'HEAD';
    }
}
