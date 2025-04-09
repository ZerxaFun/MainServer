<?php

namespace Core\Services\Routing;

use Core\Services\Auth\AuthMiddleware;
use Core\Services\Container\DI;
use Core\Services\Http\Header;
use Core\Services\Http\Request;
use Core\Services\Path\Path;
use JsonException;
use ReflectionMethod;
use RuntimeException;

class ModuleRunner
{
    /**
     * Запускает выполнение действия контроллера модуля.
     *
     * @param Module $module
     * @return mixed
     * @throws JsonException
     */
    public static function run(Module $module): mixed
    {
        self::loadManifest($module);

        $controllerClass = "\\Modules\\{$module->module}\\Controller\\{$module->controller}";



        if (!class_exists($controllerClass)) {
            throw new RuntimeException("Контроллер не найден: $controllerClass");
        }

        // Создание экземпляра контроллера
        $instance = new $controllerClass;
        $module->instance = $instance;

        // init(), если есть
        if (method_exists($instance, 'init')) {
            $instance->init();
        }

        // Языковые заголовки (для обычных страниц)
        if ($module->type !== 'API') {
            self::setLanguageHeaders();
        }

        // ✅ Авторизация через AuthMiddleware
        try {
            $reflection = new ReflectionMethod($instance, $module->action);

            $middleware = new AuthMiddleware($reflection);
            $middleware->handle(DI::instance()->get('request'), fn() => null);
        } catch (\Throwable $e) {
            // Ошибки здесь означают либо сбой в авторизации, либо отсутствие метода
            APIControllers::setData([
                'message' => 'Ошибка авторизации: ' . $e->getMessage()
            ], 401, 'error');
        }

        // Вызов контроллера
        return match ($module->type) {
            'api', 'cli' => $instance->{$module->action}($module->parameters),
            default      => call_user_func([$instance, $module->action], $module->parameters),
        };
    }



    /**
     * Загружает манифест и определяет тип модуля.
     *
     * @param Module $module
     * @return void
     * @throws JsonException
     */
    private static function loadManifest(Module $module): void
    {
        if (empty($module->module)) {
            throw new RuntimeException("Ошибка: module->module пуст в ModuleRunner::loadManifest().");
        }

        $path = Path::module($module->module) . 'manifest.json';

        if (!is_file($path)) {
            throw new RuntimeException("Файл manifest.json не найден: $path");
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new RuntimeException("manifest.json повреждён или пуст: $path");
        }

        $module->type = $data['type'] ?? 'module';
    }

    /**
     * Устанавливает заголовки языка, если они есть в контейнере.
     *
     * @return void
     */
    private static function setLanguageHeaders(): void
    {
        $lang = DI::instance()->get('language');

        if (isset($lang['languages'][$lang['select']]['http'])) {
            Header::language($lang['languages'][$lang['select']]['http']);
        }
    }
}
