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
use Core\Services\Config\Config;
use Core\Services\Modules\LanguageConfig;
use Core\Services\Modules\LanguageModules;
use Core\Services\Session\Facades\Session;
use Route;

/**
 * Класс для работы с URL
 *
 * Class Uri
 * @package Core\Services\Http
 */
class Uri
{
    /**
     * Базовый URL пользователя
     *
     * @var string
     */
    protected static string $base = '';
    protected static string $assets = '';

    /**
     * Активный URL пользователя
     *
     * @var string
     */
    protected static string $uri = '';

    /**
     * Получение сегментов URL в виде массива
     * @var array
     */
    public static array $segments = [];
    public static array $segmentsOriginal = [];
    private static string $host = '';

    /**
     * Инициализируйте класс URI.
     *
     * @return void
     */
    public static function initialize(): void
    {

        # Нам нужно получить различные разделы из URI для обработки
        # правильных маршрут.
        header('X-Powered-By: ' . Define::NAME_HEAD);
        # Стандартный запрос в браузере?
        if (isset($_SERVER['REQUEST_URI'])) {
            # Получить активный URI.
            $request = $_SERVER['REQUEST_URI'];
            $host = $_SERVER['HTTP_HOST'];
            $protocol = 'http' . (Request::https() ? 's' : '');
            $base = $protocol . '://' . $host;
            $assets = '//' . $host;
            $uri = $base . $request;

            # Создаем сегменты URI.
            $length = strlen($base);
            $str = substr($uri, $length);
            $arr = explode('/', trim($str, '/'));
            $segments = [];

            foreach ($arr as $segment) {
                if ($segment !== '') {
                    $segments[] = $segment;
                }
            }

            # Назначаем свойства.
            static::$base = $base;
            static::$host = $host;
            static::$assets = $assets;
            static::$uri = $uri;
            static::$segments = $segments;
            static::$segmentsOriginal = $segments;

        } else if (isset($_SERVER['argv'])) {
            $segments = [];
            foreach ($_SERVER['argv'] as $arg) {
                if ($arg !== $_SERVER['SCRIPT_NAME']) {
                    $segments[] = $arg;
                }
            }

            static::$segments = $segments;
            static::$segmentsOriginal = $segments;
        }

    }

    public static function backSegment()
    {
        return $_SERVER['HTTP_REFERER'];
    }

    public static function segmentLast()
    {
        return array_pop(static::$segments);
    }

    /**
     * Проверка, является ли данная страница главной.
     * Возвращает bool значение.
     *
     * @return bool
     */
    final public static function main(): bool
    {
        return (self::segmentStringOriginal() === '') || (self::prefixLanguage() === self::segmentOriginal(1));
    }


    /**
     * Получить базовый URI.
     *
     * @return string
     */
    public static function base(): string
    {
        return static::$base;
    }

    /**
     * Получить базовый URI без HTTP's.
     *
     * @return string
     */
    public static function host(): string
    {
        return static::$host;
    }

    /**
     * Получить базовый assets URI.
     *
     * @return string
     */
    public static function assets(): string
    {
        return static::$assets;
    }

    /**
     * Получение текущего URL
     *
     * @return string
     */
    public static function uri(): string
    {
        return static::$uri;
    }

    /**
     * Получите сегменты URI.
     *
     * @return array
     */
    public static function segments(): array
    {
        return static::$segments;
    }

    /**
     * Возвращает URL встроенного сайта.
     *
     * @param string $uri - URI для добавления на базу.
     * @return string
     */
    public function url(string $uri = ''): string
    {
        return static::base() . ltrim($uri, '/');
    }

    /**
     * Получает сегмент из URI.
     *
     * @param int $num - Номер сегмента.
     * @return string
     */
    public static function segment(int $num): string
    {

        if (array_key_exists(0, static::$segments)) {
            $allowLang = [];

            foreach (LanguageConfig::$modules[Route::$module]['languages'] as $lang) {
                $allowLang[$lang['prefix']] = $lang;
            }

            if (array_key_exists(static::$segments[0], $allowLang)) {
                array_shift(static::$segments);
            }
        }



        /**
         * Нормализация номера сегмента
         */
        --$num;

        /**
         * Попытка найти запрошенный сегмент
         */
        return static::$segments[$num] ?? '';
    }

    /**
     * Получает сегмент из URI.
     *
     * @param int $num - Номер сегмента.
     * @return string
     */
    public static function segmentOriginal(int $num): ?string
    {
        /**
         * Нормализация номера сегмента
         */
        --$num;

        return static::$segmentsOriginal[$num] ?? null;
    }

    /**
     * Получить сегменты URI в виде строки.
     *
     * @return string
     */
    public static function segmentString(): string
    {
        return implode('/', static::$segments);
    }

    /**
     * Получить сегменты URI в виде строки.
     *
     * @return string
     */
    public static function segmentStringOriginal(): string
    {
        return implode('/', static::$segmentsOriginal);
    }

    public static function segmentLanguage(): string
    {
        $allowLang = [];

        foreach (LanguageConfig::$modules[Route::$module]['languages'] as $lang) {
            $allowLang[$lang['prefix']] = $lang['iso'];
        }


        return $allowLang[self::segmentOriginal(1)] ?? LanguageConfig::$modules[Route::$module]['manifest']->default;
    }

    public static function prefixLanguage(): string
    {
        $allowLang = [];
        foreach (LanguageConfig::$modules[Route::$module]['languages'] as $lang) {
            $allowLang[$lang['prefix']] = $lang['prefix'];
        }


        return $allowLang[self::segmentOriginal(1)] ?? LanguageConfig::$modules[Route::$module]['manifest']->default;
    }

    public static function getSegment()
    {
        return str_replace(self::prefixLanguage() . '/', '', self::segmentString());
    }

    public static function get(string $name = '')
    {

        $result = [];
        foreach ($_GET as $key => $item) {
            $result[] = [
                'key' => $key,
                'get' => $item
            ];
        }

        if ($name !== '') {
            return $result[$name];
        }

        return $result;
    }
}
