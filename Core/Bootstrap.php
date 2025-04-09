<?php
declare(strict_types=1);
/**
 *=====================================================
 * Majestic Engine                                    =
 *=====================================================
 * @package Core\Bootstrap                            =
 *-----------------------------------------------------
 * @url http://majestic-studio.com/                   =
 *-----------------------------------------------------
 * @copyright 2021 Majestic Studio                    =
 *=====================================================
 * @author ZerxaFun aKa Zerxa                         =
 *=====================================================
 * @license GPL version 3                             =
 *=====================================================
 *                                                    =
 *                                                    =
 *=====================================================
 */

namespace Core;


use Core\Services\Auth\Auth;
use Core\Services\Auth\AuthManager;
use Core\Services\Auth\JWTGuard;
use Core\Services\Container\DI;
use Core\Services\Database\Database;
use Core\Services\Environment\Dotenv;
use Core\Services\ErrorHandler\ErrorHandler;
use Core\Services\Http\Request;
use Core\Services\Http\Uri;
use Core\Services\Modules\LanguageConfig;
use Core\Services\Routing\Controller;
use Core\Services\Routing\Route;
use Core\Services\Routing\Router;
use Core\Services\Session\Facades\Session;
use Core\Services\Template\Layout;
use Core\Services\Template\View;
use Exception;

/**
 * Инициализация проекта
 */
class Bootstrap
{
    /**
     * @throws Exception
     */
    public static function run(string $pathApplication): void
    {

        DI::instance()->set('baseDir', $pathApplication);

        DEFINE('use_memory', memory_get_usage());

        /**
         * Загрузка классов необходимых для работы
         */
        class_alias(DI::class, 'DI');
        class_alias(Controller::class, 'Controller');
        class_alias(Layout::class, 'Layout');
        class_alias(Route::class, 'Route');
        class_alias(View::class, 'View');

        /**
         * Парсинг core.conf файлов окружения
         */
        Dotenv::initialize();


        if ($_ENV['developer'] === true) {
            ini_set('error_reporting', E_ALL);
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
        }

        /**
         * Инициализация сессий.
         */
        Session::initialize();

        LanguageConfig::load();
        /**
         * Инициализация URI.
         */
        Uri::initialize();

        /**
         * Правильный вывод ошибок
         */
        ErrorHandler::initialize();

        /**
         * Подключение к базе данных.
         */
        Database::initialize();


        /**
         * Подключение аутентификации
         */
        AuthManager::registerGuard('jwt', new JWTGuard());

        /**
         * Регистрация request в контейнер
         */
        DI::instance()->set('request', new Request());
        /**
         * Подключение MVC паттерна
         */
        Router::initialize();
    }

}
