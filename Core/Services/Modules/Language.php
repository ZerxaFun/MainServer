<?php

namespace Core\Services\Modules;

use Core\Services\Container\DI;
use Core\Services\Http\Redirect;
use Core\Services\Http\Uri;
use Core\Services\Routing\Module;
use Core\Services\Routing\Route;
use Core\Services\Routing\Router;
use Core\Services\Session\Facades\Session;
use RuntimeException;

class Language
{
    public static ?string $clientLanguage = null;

    /**
     * Инициализация параметров языка модуля
     * @return void
     * @throws RuntimeException
     * @throws \JsonException
     */
    public static function init(): void
    {
        if (!Router::module()->module) {
            throw new RuntimeException('Ошибка. Модуль отсутствует.');
        }

        if (Session::get('language') === false) {
            Session::put('language', []);
        }

        if (array_key_exists(Router::module()->module, LanguageConfig::$modules)) {
            $moduleLanguage = LanguageConfig::$modules[Router::module()->module];
            $uriLanguage = Uri::segmentLanguage();

            if ($moduleLanguage['manifest']->multiLanguage === true) {
                /**
                 * Если в сессии пользователя уже существует конфигурация языка
                 */
                if (array_key_exists(Router::module()->module, Session::get('language'))) {
                    if (array_key_exists($uriLanguage, $moduleLanguage['languages'])) {
                        /**
                         * Меняем префикс и ISO языка местами, чтобы проверить валиден ли наш текущий префикс языка
                         * В случаи отсутствия языка в модуле не меняем его язык, оставляем тот, что есть в сессии.
                         */
                        $prefix = [];
                        foreach (LanguageConfig::$modules[Router::module()->module]['languages'] as $lang) {
                            $prefix[$lang['prefix']] = $lang['iso'];
                        }

                        if (array_key_exists(Uri::segmentOriginal(1), $prefix)) {
                            $_SESSION['Majestic']['language'][Router::module()->module] = $moduleLanguage['languages'][$uriLanguage];
                        }
                    }
                } else {
                    /**
                     * Если у пользователя в сессии язык не установлен получаем язык по умолчанию, либо язык его региона
                     */
                    /**
                     * Выбранный язык
                     */
                    $defaultModuleLanguage = LanguageConfig::getDefaultLanguageModule(Router::module()->module);

                    /**
                     * Если сессия языка ещё не установлена
                     */
                    if (array_key_exists(Router::module()->module, Session::get('language')) === false) {
                        /**
                         * Если нет URL профикса языка и пользователь на главной странице
                         * устанавливаем ему язык модуля по умолчанию.
                         */
                        if ($uriLanguage === $defaultModuleLanguage) {
                            /**
                             * Записываем данные языка в сессию
                             */
                            $_SESSION['Majestic']['language'][Router::module()->module] = $moduleLanguage['languages'][$defaultModuleLanguage];
                        } else {
                            $_SESSION['Majestic']['language'][Router::module()->module] = $moduleLanguage['languages'][$uriLanguage];
                        }
                    } else if (Uri::segmentOriginal(1) !== null) {
                        /**
                         * Если сессия уже установлена, то проверям, есть ли первый сегмент URI.
                         */
                        $_SESSION['Majestic']['language'][Router::module()->module] = $moduleLanguage['languages'][$uriLanguage];
                    }
                }
            }

            LanguageConfig::reloadConfig();
            DI::instance()->set('language', LanguageConfig::$modules[Router::module()->module]);
        }
    }

    public static function redirect()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
            array_key_exists(Router::module()->module, $_SESSION['Majestic']['language']) &&
            Uri::segmentLanguage() !== $_SESSION['Majestic']['language'][Router::module()->module]['iso'] &&
            $_SESSION['Majestic']['language'][Router::module()->module]['default'] !== true) {

            Redirect::go('/' . $_SESSION['Majestic']['language'][Router::module()->module]['prefix'] . '/' . Uri::segmentString());
        }
    }

    public static function getUrl()
    {
        return '/' . $_SESSION['Majestic']['language'][Router::module()->module]['prefix'] . '/' . Uri::segmentString();
    }
}
