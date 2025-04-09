<?php

namespace Core\Services\Routing;

use Core\Services\Auth\Attributes\Authorize;
use Core\Services\Routing\Attributes\HttpMethod;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionAttribute;

class AttributeRouteLoader
{
    public static function load(array $controllers): void
    {
        foreach ($controllers as $controllerClass) {
            $reflection = new \ReflectionClass($controllerClass);
            $module = self::extractModuleName($controllerClass);
            $controller = $reflection->getShortName();

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $httpAttributes = $method->getAttributes(HttpMethod::class);
                if (empty($httpAttributes)) {
                    continue;
                }

                // Подгружаем Authorize (если есть)
                $authAttributes = $method->getAttributes(Authorize::class);
                $authorizeData = null;

                if (!empty($authAttributes)) {
                    $authorize = $authAttributes[0]->newInstance();
                    $authorizeData = [
                        'guard' => $authorize->guard,
                        'permission' => $authorize->permission,
                    ];
                }

                foreach ($httpAttributes as $attr) {
                    $http = $attr->newInstance();
                    $methods = (array) $http->method;

                    foreach ($methods as $httpMethod) {
                        $options = [
                            'controller' => $controller,
                            'action'     => $method->getName(),
                            'module'     => $module,
                        ];

                        if ($authorizeData) {
                            $options['attributes']['Authorize'] = $authorizeData;
                        }

                        Route::add(strtolower($httpMethod), $http->uri, $options);
                    }
                }
            }
        }
    }


    /**
     * Получает имя модуля из полного имени класса
     *
     * @param string $class — полный путь класса
     * @return string
     */
    private static function extractModuleName(string $class): string
    {
        if (preg_match('/\\\\Modules\\\\([^\\\\]+)\\\\Controller\\\\/', $class, $matches)) {
            return $matches[1];
        }

        throw new \RuntimeException("Невозможно определить имя модуля из класса: $class");
    }
}
