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


use Core\Facades\LoggerFacade;
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
        class_alias(LoggerFacade::class, 'Logger');

        $t1 = microtime(true);

        Dotenv::initialize();
        if ($_ENV['developer'] === true) {
            ini_set('error_reporting', E_ALL);
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
        }

        $t2 = microtime(true);

        Session::initialize();
        $t3 = microtime(true);

        Uri::initialize();
        $t4 = microtime(true);

        LanguageConfig::load();
        $t5 = microtime(true);

        ErrorHandler::initialize();
        $t6 = microtime(true);

        Database::initialize();
        $t7 = microtime(true);

        self::registerProviders();
        $t8 = microtime(true);

        AuthManager::registerGuard('jwt', new JWTGuard());
        $t9 = microtime(true);

        DI::instance()->set('request', new Request());
        $t10 = microtime(true);

        Router::initialize();
        $t11 = microtime(true);

        Controller::init();
        $t12 = microtime(true);

        // 🔍 Подробный отчёт
        $timing = [
            'Startup / Aliases'       => $t1 - $start,
            'Dotenv'                  => $t2 - $t1,
            'Session Init'            => $t3 - $t2,
            'URI Init'                => $t4 - $t3,
            'LanguageConfig::load()'  => $t5 - $t4,
            'ErrorHandler'            => $t6 - $t5,
            'Database Init'           => $t7 - $t6,
            'Service Providers'       => $t8 - $t7,
            'Auth Guard'              => $t9 - $t8,
            'Request DI Bind'         => $t10 - $t9,
            'Router Init'             => $t11 - $t10,
            'Controller Init'         => $t12 - $t11,
            'TOTAL'                   => $t12 - $start,
        ];

        echo "<pre>";
        foreach ($timing as $step => $time) {
            printf("%-25s : %.4f sec\n", $step, $time);
        }
        echo "</pre>";
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
