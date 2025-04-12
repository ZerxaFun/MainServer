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

use Core\Define;

/**
 * Класс для работы с заголовком браузера
 *
 * Class Header
 * @package Core\Services\Http
 */
class Header extends AbstractHeader
{
    public static string $header = '';

    /**
     * Устанавливает Content-Type заголовок
     *
     * @param string $type Тип содержимого: html, json, xml, text
     */
    public static function header(string $type = 'html'): void
    {
        $final = self::$type[$type] ?? self::$type['html'];
        $charset = self::$charset;

        header("Content-Type: {$final}; charset={$charset}");
    }

    /**
     * Отправка уже обработанного Content-Type
     * конченному клиенту
     *
     * @param string $header - заголовок страницы, HTML, json и так далее
     */
    private function construct(string $header): void
    {
        header('Content-Type: ' . $header . '; charset=' . self::$charset);
    }

    public static function language(string $languageTag): void
    {
        header("Content-Language: $languageTag");
    }

    /**
     * Устанавливает HTTP статус-код безопасно (без привязки к HTTP/1.1)
     *
     * @param int $code HTTP status code (например, 200, 404, 422)
     * @return void
     */
    public static function code(int $code): void
    {
        http_response_code($code);
    }


    /**
     * Отправка кода 404, страница не найдена
     *
     * @return void
     */
    public static function code404(): void
    {
        header('HTTP/1.1 404 Not Found');
    }
}

