<?php
/**
 *=====================================================
 * Majestic Engine - by Zerxa Fun (Majestic Studio)   =
 *-----------------------------------------------------
 * @url: http://majestic-studio.ru/                   -
 *-----------------------------------------------------
 * @copyright: 2020 Majestic Studio and ZerxaFun      -
 *=====================================================
 *                                                    =
 *                                                    =
 *                                                    =
 *=====================================================
 */


namespace Core\Services\Http;


/**
 * Class Request
 * @package Core\Services\Http
 */
class Request
{
    protected array $data = [];
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }


    /**
     * Проверка, является ли запрос определенным методом.
     *
     * @param  string  $method - Метод запроса для проверки.
     * @return bool
     */
    public static function is(string $method): bool
	{
        return match (strtolower($method)) {
            'https' => self::https(),
            'ajax' => self::isAjax(),
            'cli' => self::cli(),
            default => $method === self::method(),
        };
    }

    /**
     * Получение текущего запроса.
     *
     * @return string
     */
    public static function method(): string
	{
        return strtolower($_SERVER['REQUEST_METHOD'] ?? 'get');
    }

    /**
     * Проверьте, если запрос через соединение https.
     *
     * @return bool
     */
    public static function https(): bool
	{
        return ($_SERVER['HTTPS'] ?? '') === 'on';
    }


    /**
     * Проверка, является ли запрос запросом CLI.
     *
     * @return bool
     */
    public static function cli(): bool
	{
        return (PHP_SAPI === 'cli' || defined('STDIN'));
    }

    /**
     * Проверяет, был ли запрос выполнен через AJAX.
     *
     * @return bool
     */
    public static function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /**
     * Проверяет, является ли запрос HTTPS.
     *
     * @return bool
     */
    public static function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    /**
     * Возвращает протокол (http или https).
     *
     * @return string
     */
    public static function protocol(): string
    {
        return self::isSecure() ? 'https' : 'http';
    }

    /**
     * Возвращает хост (доменное имя).
     *
     * @return string
     */
    public static function host(): string
    {
        return $_SERVER['HTTP_HOST'] ?? 'localhost';
    }

    /**
     * Возвращает базовый URL (протокол + хост).
     *
     * @return string
     */
    public static function baseUrl(): string
    {
        return self::protocol() . '://' . self::host();
    }

    public function bearerToken(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if (!$authHeader) {
            return null;
        }

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Проверяет, содержит ли запрос JSON.
     *
     * @return bool
     */
    public static function isJson(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        return str_contains(strtolower($contentType), 'application/json');
    }

    public static function isApi(): bool
    {
        return self::isJson() || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }
}

