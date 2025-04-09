<?php
/**
 *=====================================================
 * Majestic Next Engine - by Zerxa Fun                =
 *-----------------------------------------------------
 * @url: https://majestic-studio.com/                 =
 *-----------------------------------------------------
 * @copyright: 2021 Majestic Studio and ZerxaFun      =
 *=====================================================
 * @license GPL version 3                             =
 *=====================================================
 * LanguageConfig - получение конфигурации языков     =
 * модулей системы.                                   =
 *                                                    =
 *=====================================================
 */

namespace Core\Services\Modules;

use Core\Services\Path\Path;
use Core\Services\Routing\Route;
use Core\Services\Routing\Router;
use Core\Services\Session\Facades\Session;
use JsonException;
use RuntimeException;


class LanguageConfig
{
    /**
     * Массив данных всех модулей
     *
     * @var array
     */
    public static array $modules = [];

    /**
     * Статичное название файла конфигруации определенного языка конкретного модуля
     *
     * @var string
     */
    private static string $langManifest = 'lang.json';

    /**
     * Статичное название файла конфигруации модуля
     *
     * @var string
     */
    private static string $manifest = 'manifest.json';

    /**
     * Название директории пакета языков модулей
     *
     * @var string
     */
    private static string $languageDir = 'Language';

    /**
     * Подключение и загрузка данных конфигурации файла manifest.json указанного модуля
     *
     * @return void
     * @throws RuntimeException
     * @throws JsonException
     */
    public static function load(): void
    {
        /**
         * Проверяем, существует ли папка модулей на сервере
         */
        if (is_dir(Path::module())) {

            /**
             * Получаем все доступные модули
             */
            foreach (scandir(Path::module()) as $module) {
                if (in_array($module, ['.', '..'], true)) {
                    continue;
                }

                /**
                 * Получаем manifest.json модуля, узнаем, поддерживает ли он мультиязычность.
                 */
                if (is_file(Path::module($module) . self::$manifest)) {
                    $manifest = json_decode(
                        file_get_contents(Path::module($module) . self::$manifest),
                        false, 512, JSON_THROW_ON_ERROR
                    );

                } else {
                    /**
                     * Если файла manifest.json не существует в модуле
                     */
                    throw new RuntimeException(sprintf('Не удалось найти файл %s модуля %s', self::$manifest, $module));
                }

                /**
                 * Проверяем наличие конфигурации языка и поддержки мультиязычности модуля
                 * Если данных нет, то пропускаем модуль как модуль для одного языка.
                 */
                if (array_key_exists('language', (array) $manifest)) {
                    self::$modules[$module]['manifest'] = $manifest->language;
                    self::$modules[$module]['module'] = $module;
                    self::$modules[$module]['select'] = Session::get('language')[$module]['iso'] ?? $manifest->language->default;
                } else {
                    continue;
                }


                /**
                 * Получение директории Languages нашего модуля
                 *
                 * Если у модуля нет директории Language, то пропускаем его
                 */
                if (is_dir(Path::module($module) . self::$languageDir)) {
                    foreach (scandir(Path::module($module) . self::$languageDir) as $languageDir) {
                        if (in_array($languageDir, ['.', '..'], true)) {
                            continue;
                        }

                        /**
                         * Проверяем наличие информационного json файла языка
                         */
                        if (!is_file(Path::module($module) . self::$languageDir . DIRECTORY_SEPARATOR . $languageDir . DIRECTORY_SEPARATOR . self::$langManifest)) {
                            throw new RuntimeException(
                                sprintf(
                                    'Ошибка модуля %s. В директории %s языка %s нет файла конфигурациии %s',
                                    $module, self::$languageDir, $languageDir, self::$langManifest
                                )
                            );
                        }

                        /**
                         * JSON парсинг массив данных языка
                         */
                        $manifest = json_decode(
                            file_get_contents(
                                Path::module($module) . self::$languageDir . DIRECTORY_SEPARATOR . $languageDir . DIRECTORY_SEPARATOR . self::$langManifest
                            ),
                            false, 512, JSON_THROW_ON_ERROR
                        );

                        /**
                         * Обработка массива данных языка.
                         */
                        if ((
                            !array_key_exists('Prefix', (array) $manifest) ||
                            !array_key_exists('iso', (array) $manifest) ||
                            !array_key_exists('name', (array) $manifest) ||
                            !array_key_exists('http', (array) $manifest))
                        ) {
                            throw new RuntimeException(
                                sprintf(
                                    'Ошибка модуля %s. В директории %s языка %s в файле конфигурациии %s отсутствует одно из обязательных значений [Prefix, iso, http]',
                                    $module, self::$languageDir, $languageDir, self::$langManifest
                                )
                            );
                        }


                        /**
                         * Проверяем, если у модуля отключена мультиязычность
                         * То присваиваем ему его стандартный язык по-умолчанию
                         */
                        if ((self::$modules[$module]['manifest']->multiLanguage === false) && (self::$modules[$module]['manifest']->default !== $languageDir)) {
                            continue;
                        }

                        /**
                         * Создание массива параметров языков модулей
                         */
                        self::$modules[$module]['languages'][$languageDir] = [
                            'name'       => $manifest->name,
                            'dir'       => $languageDir,
                            'prefix'    => $manifest->Prefix,
                            'default'   => $manifest->iso === self::$modules[$module]['manifest']->default ?? true,
                            'iso'       => $manifest->iso,
                            'http'      => $manifest->http
                        ];

                        unset($manifest);
                    }
                }
            }
        } else {
            throw new RuntimeException('Директории Modules не существует в корне проекта');
        }

    }

    /**
     * @throws JsonException
     */
    public static function reloadConfig(): void
    {
        self::load();
    }

    /**
     * @param string $isoLanguageCode
     * @return array
     */
    public static function language(string $isoLanguageCode): array
    {
        if (array_key_exists($isoLanguageCode, self::$modules[Router::module()->module]['languages'])) {
            return self::$modules[Router::module()->module]['languages'][$isoLanguageCode];
        }

        throw new RuntimeException(sprintf('Не удалось найти данные языка %s в модуле %s', $isoLanguageCode, Router::module()->module));
    }


    public static function getDefaultLanguageModule(string $module): string
    {
        return self::$modules[$module]['manifest']->default;
    }
}