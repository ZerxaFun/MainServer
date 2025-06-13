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
 * Абстрактный класс для работы с заголовком браузера
 *
 *
 * Class AbstractHeader
 * @package Core\Services\Http
 */
abstract class AbstractHeader
{
    /**
     * Список MIME методов для генерации ответа Header.
     * Для добавления нового типа заголовка страницы
     * необходимо поместить его в массив
     * в формате 'ключ' => 'значение', к примеру
     * 'json' => 'application/json'
     *
     * @var array - тип HTTP запроса к роутеру
     */
    protected static array $type = [
        'html' => 'text/html',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'text' => 'text/plain'
    ];

    /**
     * @var string - кодировка страниц
     */
    protected static string $charset = 'utf-8';


}