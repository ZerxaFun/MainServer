<?php

namespace Core\Routing;

use Core\Services\Config\Config;
use Core\Services\Http\Header;
use Core\Services\Http\Request;
use Core\Services\Http\Uri;
use Core\Services\Modules\Language;
use Core\Services\Modules\LanguageConfig;
use Core\Services\Path\Path;
use Core\Template\Layout;
use Core\Template\View;
use JetBrains\PhpStorm\NoReturn;
use ReflectionMethod;
use ReflectionNamedType;

class Router
{
    private static ?Module $module = null;
    private static ?MatchedRoute $matchedRoute = null;

    public static function module(): Module
    {
        return self::$module;
    }

    public static function initialize(): void
    {
        self::loadRoutes();
        self::annotateReturnTypes(); // ⬅ типы маршрутов
        self::rewrite();             // ⬅ язык
        self::resolveRoute();
        self::initializeLocalization();
        self::handleRequest();
    }

    private static function loadRoutes(): void
    {
        $modulesPath = Path::module();

        foreach (scandir($modulesPath) as $module) {
            if (in_array($module, ['.', '..'])) continue;

            $controllerPath = $modulesPath . $module . '/Controller';
            if (!is_dir($controllerPath)) continue;

            Route::$module = $module;

            foreach (glob($controllerPath . '/*.php') as $file) {
                require_once $file;
                $class = self::classFromFile($file, $module);
                if (class_exists($class)) {
                    AttributeRouteLoader::load([$class]);
                }
            }
        }
    }

    private static function annotateReturnTypes(): void
    {
        foreach (Repository::stored() as $method => $routes) {
            foreach ($routes as $uri => $options) {
                $type = self::detectReturnType($options);
                $options['type'] = $type;
                Repository::store($method, $uri, $options);
            }
        }
    }

    private static function detectReturnType(array $options): string
    {
        try {
            $class = "\\Modules\\{$options['module']}\\Controller\\{$options['controller']}";
            if (!class_exists($class)) return 'unknown';

            $method = new ReflectionMethod($class, $options['action']);
            $returnType = $method->getReturnType();
            if (!$returnType instanceof ReflectionNamedType) return 'unknown';

            $type = $returnType->getName();
            if (is_a($type, View::class, true)) return 'view';
            if (strtolower($type) === 'void') return 'api';
            if (strtolower($type) === 'string') return 'string';

            return $type;
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private static function rewrite(): void
    {
        LanguageConfig::load();

        foreach (Repository::stored() as $method => $routes) {
            if ($method !== 'get') continue;

            foreach ($routes as $uri => $options) {
                if (($options['type'] ?? '') !== 'view') continue;

                $module = $options['module'] ?? null;
                if (!$module) continue;

                $languages = LanguageConfig::$modules[$module]['languages'] ?? [];
                $default = LanguageConfig::getDefaultLanguageModule($module);

                foreach ($languages as $iso => $lang) {
                    $prefix = $lang['prefix'];
                    $prefixedUri = self::cleanUri($prefix . '/' . $uri);

                    self::addLangRoute($method, $prefixedUri, $iso, $prefix, $uri, $options, $languages);

                    if ($iso === $default) {
                        $cleanUri = self::cleanUri($uri);
                        self::addLangRoute($method, $cleanUri, $iso, '', $uri, $options, $languages, true);
                    }
                }
            }
        }
    }

    private static function addLangRoute(
        string $method,
        string $uri,
        string $iso,
        string $prefix,
        string $original,
        array $options,
        array $languages,
        bool $isDefault = false
    ): void {
        Repository::$storedLanguage[$method][$uri] = [
            'url'       => $uri,
            'iso'       => $iso,
            'prefix'    => $prefix,
            'option'    => $options,
            'default'   => $isDefault,
            'original'  => $original,
            'languages' => $languages,
        ];

        Route::add($method, $uri, $options);
    }

    private static function resolveRoute(): void
    {
        $method = Request::method();
        $uri = Uri::segmentString();

        $routes = Repository::stored()[$method] ?? [];

        foreach ($routes as $pattern => $routeOptions) {
            $parsed = RoutePatternParser::parse($pattern);
            if (preg_match($parsed['regex'], $uri, $matches)) {
                $params = array_intersect_key($matches, array_flip($parsed['params']));
                self::$matchedRoute = new MatchedRoute($params);
                self::$module = new Module($routeOptions);
                return;
            }
        }

        if (Request::isApi()) {
            APIControllers::setData(['message' => 'Route not found'], 404);
        }

        self::fallbackToErrorModule();
    }

    private static function fallbackToErrorModule(): void
    {
        $errorModule = Config::has('errorModule') ? Config::item('errorModule') : null;

        if (!is_string($errorModule) || trim($errorModule) === '') {
            self::rawErrorResponse(['Route not found', 'Missing or invalid errorModule config']);
        }

        if (!is_dir(Path::module($errorModule))) {
            self::rawErrorResponse(['Route not found', "Module not found: $errorModule"]);
        }

        if (Config::has('JSApp') && !Config::item('JSApp')) {
            Header::code404();
        }

        self::$module = new Module([
            'controller' => 'ErrorController',
            'action'     => 'page404',
            'module'     => $errorModule,
        ]);
    }

    private static function initializeLocalization(): void
    {
        if (!self::$module || empty(self::$module->module)) return;

        LanguageConfig::reloadConfig();
        Language::redirect();
    }

    private static function handleRequest(): void
    {
        $response = ModuleRunner::run(self::$module);

        if ($response instanceof View) {
            Layout::view($response);
            echo Layout::get(static::$module->instance()::$layout ?? 'layout');
            return;
        }

        if (is_object($response) && method_exists($response, 'respond')) {
            $response->respond();
            return;
        }

        if (is_string($response)) {
            echo $response;
        }
    }

    #[NoReturn]
    private static function rawErrorResponse(array $messages, int $code = 404): void
    {
        if (Request::isJson()) {
            APIControllers::setData(['message' => $messages], $code);
        } else {
            http_response_code($code);
            include sprintf("%s404.php", Path::base('Core/Routing/Theme/'));
        }

        exit();
    }

    public static function getParam(string $key): ?string
    {
        return self::$matchedRoute?->get($key);
    }

    private static function classFromFile(string $file, string $module): string
    {
        return "\\Modules\\$module\\Controller\\" . basename($file, '.php');
    }

    private static function cleanUri(string $uri): string
    {
        return trim(preg_replace('#/+#', '/', $uri), '/');
    }
}
