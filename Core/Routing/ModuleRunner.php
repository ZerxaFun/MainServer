<?php

namespace Core\Routing;

use Core\Services\Auth\AuthMiddleware;
use Core\Services\Container\DI;
use Core\Services\Http\Header;
use Core\Services\Http\Input;
use Core\Services\Http\Request;
use Core\Services\Http\ValidatedRequest;
use Core\Services\Modules\Language;
use Core\Services\Modules\LanguageConfig;
use Core\Services\Validation\Exceptions\ValidationException;
use Core\Services\Validation\Validator;
use JsonException;
use ReflectionMethod;
use RuntimeException;

class ModuleRunner
{
    /**
     * @param Module $module
     * @return mixed|void
     * @throws JsonException
     */
    public static function run(Module $module)
    {
        $controllerClass = "\\Modules\\{$module->module}\\Controller\\{$module->controller}";

        if (!class_exists($controllerClass)) {
            throw new RuntimeException("Контроллер не найден: $controllerClass");
        }

        // Создание экземпляра контроллера через DI
        $controllerInstance = DI::instance()->make($controllerClass);
        $module->instance = $controllerInstance;

        if (method_exists($controllerInstance, 'init')) {
            $controllerInstance->init();
        }

        self::setLanguageHeaders();

        try {
            $reflection = new ReflectionMethod($controllerInstance, $module->action);

            (new AuthMiddleware($reflection))
                ->handle(DI::instance()->get('request'), fn () => null);

            $parameters = self::resolveMethodParameters($reflection);

            return $reflection->invokeArgs($controllerInstance, $parameters);
        } catch (\Throwable $e) {
            if ($_ENV['developer'] === 'true') {
                APIControllers::setData([
                    'message' => 'Ошибка выполнения',
                    'error' => [
                        'type'    => get_class($e),
                        'message' => $e->getMessage(),
                        'file'    => $e->getFile(),
                        'line'    => $e->getLine(),
                        'code'    => $e->getCode(),
                    ]
                ], 500);
            } else {
                /**
                 * TODO::: LOG
                 */
                APIControllers::setData([
                    'message' => 'Упс.. Кажется у нас ошибка.',
                ], 500);
            }

        }
    }

    /**
     * Устанавливает заголовки языка, если они есть в контейнере.
     */
    private static function setLanguageHeaders(): void
    {
        $module = Router::module()->module;
        $currentIso = Language::current();
        $langConfig = LanguageConfig::$modules[$module]['languages'] ?? [];

        if (isset($langConfig[$currentIso]['http'])) {
            Header::language($langConfig[$currentIso]['http']);
        }
    }

    /**
     * Подготавливает аргументы для вызова метода контроллера.
     *
     * @param ReflectionMethod $reflection
     * @return array
     */
    private static function resolveMethodParameters(ReflectionMethod $reflection): array
    {
        $parameters = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

            switch ($typeName) {
                case ValidatedRequest::class:
                    $rules = [];
                    foreach ($reflection->getAttributes(\Core\Services\Attributes\Validate::class) as $attr) {
                        $validateAttr = $attr->newInstance();
                        $rules = $validateAttr->rules ?? [];
                    }

                    $input = Input::json() + Input::post() + Input::get();
                    $validator = new Validator($rules);

                    try {
                        $validator->validate($input);
                        $parameters[] = new ValidatedRequest($validator->validated());
                    } catch (ValidationException $e) {
                        $parameters[] = new ValidatedRequest($input, $e->errors());
                    }
                    break;

                case Request::class:
                    $parameters[] = DI::instance()->get('request');
                    break;

                default:
                    $parameters[] = null;
            }
        }

        return $parameters;
    }
}
