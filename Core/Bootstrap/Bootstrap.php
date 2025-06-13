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

namespace Core\Bootstrap;


use Core\Foundation\ServiceProvider;
use Core\Services\Auth\AuthManager;
use Core\Services\Auth\JWTGuard;
use Core\Services\Config\Config;
use Core\Services\Container\DI;
use Core\Services\Database\Database;
use Core\Services\Environment\Dotenv;
use Core\Services\ErrorHandler\ErrorHandler;
use Core\Services\Http\Request;
use Core\Services\Http\Uri;
use Core\Services\Modules\LanguageConfig;
use Core\Routing\Controller;
use Core\Routing\Route;
use Core\Routing\Router;
use Core\Services\Session\Facades\Session;
use Core\Template\Layout;
use Core\Template\View;
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
        $start = microtime(true);

        DI::instance()->set('baseDir', $pathApplication);
        define('use_memory', memory_get_usage());

        class_alias(DI::class, 'DI');
        class_alias(Controller::class, 'Controller');
        class_alias(Layout::class, 'Layout');
        class_alias(Route::class, 'Route');
        class_alias(View::class, 'View');

        Dotenv::initialize();
        if ($_ENV['developer'] === true) {
            ini_set('error_reporting', E_ALL);
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
        }

        Session::initialize();
        Uri::initialize();
        LanguageConfig::load();
        ErrorHandler::initialize();
        Database::initialize();

        \Illuminate\Database\Capsule\Manager::connection()->enableQueryLog();
        self::registerProviders();
        AuthManager::registerGuard('jwt', new JWTGuard());
        DI::instance()->set('request', new Request());

        Router::initialize();

        Controller::init();
    }



    private static function registerProviders(): void
    {
        $providers = Config::group('providers');

        foreach ($providers as $providerClass) {
            $provider = new $providerClass();

            if ($provider instanceof ServiceProvider) {
                $provider->register();
                $provider->boot();
            }
        }
    }
}
