<?php

declare(strict_types=1);

namespace Core\Services\Http;

/**
 * Класс Input предоставляет удобный доступ к данным запроса:
 * GET, POST, JSON и FILES.
 */
class Input
{
    /**
     * Получить данные из $_GET.
     *
     * @param string|null $key Ключ, если нужен конкретный параметр
     * @return mixed
     */
    public static function get(?string $key = null): mixed
    {
        return $key ? ($_GET[$key] ?? null) : $_GET;
    }

    /**
     * Получить данные из $_POST.
     *
     * @param string|null $key Ключ, если нужен конкретный параметр
     * @return mixed
     */
    public static function post(?string $key = null): mixed
    {
        return $key ? ($_POST[$key] ?? null) : $_POST;
    }

    /**
     * Получить данные из JSON-тела запроса.
     *
     * @param string|null $key Ключ, если нужен конкретный параметр
     * @return mixed
     */
    public static function json(?string $key = null): mixed
    {
        static $json = null;

        if ($json === null) {
            $json = json_decode(file_get_contents('php://input'), true) ?? [];
        }

        return $key ? ($json[$key] ?? null) : $json;
    }

    /**
     * Получить данные из $_FILES.
     *
     * @param string|null $key Ключ, если нужен конкретный файл
     * @return mixed
     */
    public static function files(?string $key = null): mixed
    {
        return $key ? ($_FILES[$key] ?? null) : $_FILES;
    }

    /**
     * Проверка наличия значения по ключу в любом из источников
     *
     * @param string $key Ключ
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset($_POST[$key]) || isset($_GET[$key]) || isset(static::json()[$key]) || isset($_FILES[$key]);
    }

    /**
     * Получить значение из всех источников: POST > GET > JSON > FILES.
     *
     * @param string $key
     * @return mixed|null
     */
    public static function all(string $key): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? static::json()[$key] ?? $_FILES[$key] ?? null;
    }
}
