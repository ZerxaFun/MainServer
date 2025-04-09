<?php

namespace Core\Services\Routing;

use Core\Services\Config\Config;
use Core\Services\Http\Request;
use Core\Services\Http\Uri;
use Core\Services\Modules\Manifest;
use Core\Services\Modules\Language;
use Core\Services\Modules\LanguageConfig;
use Core\Services\Path\Path;
use Core\Services\Template\Layout;
use JetBrains\PhpStorm\NoReturn;
use const http\Client\Curl\Versions\CURL;

/**
 * Главный маршрутизатор системы — загружает маршруты, определяет текущий модуль и запускает его
 */
class Router
{
    /**
     * Активный модуль маршрута
     *
     * @var Module|null
     */
    private static ?Module $module = null;

    /**
     * Возвращает текущий активный модуль
     *
     * @return Module
     */
    public static function module(): Module
    {
        return self::$module;
    }

    /**
     * Инициализация маршрутизации приложения
     */
    public static function initialize(): void
    {
        self::loadRoutes();             // Загрузка маршрутов через атрибуты
        self::rewrite();                // Генерация языковых маршрутов (если включено)
        self::resolveRoute();          // Определение текущего маршрута
        self::initializeLocalization(); // Язык (редиректы и мета)
        self::handleRequest();         // Запуск модуля
    }

    /**
     * Загружает все контроллеры из модулей и применяет атрибуты маршрутов
     */
    private static function loadRoutes(): void
    {
        $modulesPath = Path::module();

        foreach (scandir($modulesPath) as $module) {
            $fullPath = $modulesPath . $module;

            if (!is_dir($fullPath) || in_array($module, ['.', '..'])) {
                continue;
            }

            Manifest::load($module);
            Route::$module = $module;

            $controllerPath = $fullPath . '/Controller';
            $controllers = [];

            if (is_dir($controllerPath)) {
                foreach (glob($controllerPath . '/*.php') as $file) {
                    require_once $file;
                    $class = self::classFromFile($file, $module);
                    if (class_exists($class)) {
                        $controllers[] = $class;
                    }
                }

                AttributeRouteLoader::load($controllers);
            }
        }
    }

    /**
     * Генерирует маршруты с префиксами языков, если включена мультиязычность
     */
    private static function rewrite(): void
    {
        LanguageConfig::load();

        foreach (Repository::stored() as $method => $routes) {
            foreach ($routes as $uri => $options) {
                $module = $options['module'] ?? null;

                if (!$module) {
                    continue;
                }

                $modulePath = Path::module($module);
                if (!is_dir($modulePath . 'Language')) {
                    continue;
                }

                $langConfig = LanguageConfig::$modules[$module] ?? null;
                if (!$langConfig || $langConfig['manifest']->multiLanguage !== true) {
                    continue;
                }

                foreach ($langConfig['languages'] as $iso => $item) {
                    $prefix = ($item['default'] ?? false) ? '' : $item['prefix'] . '/';
                    $langUri = $prefix . $uri;

                    Repository::$storedLanguage[$method][$langUri] = [
                        'url'       => $langUri,
                        'iso'       => $iso,
                        'prefix'    => $prefix,
                        'option'    => $options,
                        'default'   => $iso,
                        'original'  => $uri,
                        'languages' => $langConfig,
                    ];
                }
            }
        }

        // Добавим языковые маршруты в общее хранилище
        foreach (Repository::$storedLanguage as $method => $routes) {
            foreach ($routes as $uri => $routeData) {
                Route::add($method, $uri, $routeData['option']);
            }
        }
    }

    /**
     * Находит маршрут по URI и методу и создаёт объект Module
     */
    private static function resolveRoute(): void
    {
        $method = Request::method();
        $uri = Uri::segmentString();

        $route = Repository::retrieve($method, $uri);

        if (!empty($route)) {
            self::$module = new Module($route);
            return;
        }
        if (Request::isApi()) {
            // Возвращай JSON ошибку
            APIControllers::setData([
                'message' => 'Route not found'
            ], 404, 'error');
        }

        // Маршрут не найден — передаём обработку ErrorController
        if (!Config::has('errorModule')) {
            self::rawErrorResponse(['Route not found', 'Missing errorModule config']);
        }

        $errorModule = Config::item('errorModule');

        if (!is_dir(Path::module($errorModule))) {
            self::rawErrorResponse(['Route not found', "Module not found: $errorModule"]);
        }

        self::$module = new Module([
            'controller' => 'ErrorController',
            'action' => 'page404',
            'module' => $errorModule,
        ]);
    }

    #[NoReturn] private static function rawErrorResponse(array $messages, int $code = 404): void
    {
        if (Request::isJson()) {
            APIControllers::setData(['message' => $messages], $code, 'error');
        } else {
            http_response_code($code);
            echo "<h1>404 - Not Found</h1><ul>";
            foreach ($messages as $msg) {
                echo "<li>" . htmlspecialchars($msg) . "</li>";
            }
            echo "</ul>";
            exit;
        }
    }

    /**
     * Запускает и выполняет модуль (API, CLI, WEB)
     */
    private static function handleRequest(): void
    {
        $response = ModuleRunner::run(self::$module);

        // View-объект → layout должен быть применён
        if ($response instanceof \Core\Services\Template\View) {
            \Layout::view($response);           // сохраняем View
            echo \Layout::get(static::$module->instance()::$layout ?? 'layout'); // рендерим layout
        return;
    }

        // Контроллер возвращает объект с методом respond()
        if (is_object($response) && method_exists($response, 'respond')) {
            $response->respond();
            return;
        }

        // Просто строка — выводим напрямую
        if (is_string($response)) {
            echo $response;
        }
    }


    /**
     * Загружает языковую систему (init + redirect)
     */
    private static function initializeLocalization(): void
    {
        if (!self::$module || empty(self::$module->module)) {
            return;
        }

        Language::init();
        Language::redirect();
    }

    /**
     * Преобразует путь к файлу контроллера в полное имя класса
     *
     * @param string $file
     * @param string $module
     * @return string
     */
    private static function classFromFile(string $file, string $module): string
    {
        return "\\Modules\\$module\\Controller\\" . basename($file, '.php');
    }
}
