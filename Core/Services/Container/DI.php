<?php

namespace Core\Services\Container;

use RuntimeException;

/**
 * Класс DI — контейнер зависимостей (Dependency Injection Container)
 *
 * Хранит и управляет объектами/данными по ключам. Используется как глобальное хранилище зависимостей.
 * Обеспечивает доступ к сервисам и данным через ключи.
 */
class DI
{
    /**
     * Единственный экземпляр контейнера (реализация паттерна Singleton)
     *
     * @var DI|null
     */
    private static ?self $instance = null;

    /**
     * Хранилище зависимостей
     *
     * @var array
     */
    private array $container = [];

    /**
     * Возвращает экземпляр контейнера или создаёт его, если ещё не создан
     *
     * @return self
     */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Получает значение из контейнера по ключу
     *
     * @param string $key — имя зависимости
     * @return mixed — значение зависимости
     * @throws RuntimeException — если ключ не найден
     */
    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new RuntimeException("DI: dependency '$key' not found.");
        }

        $value = $this->container[$key];

        // 👉 Если это замыкание — вызываем и заменяем в контейнере
        if ($value instanceof \Closure) {
            $value = $value(); // вызываем
            $this->container[$key] = $value; // кэшируем результат
        }

        return $value;
    }


    /**
     * Устанавливает значение в контейнер по ключу или в секцию
     *
     * Примеры:
     *   set('db', $connection);                         // обычная зависимость
     *   set(['config', 'db'], ['host' => 'localhost']); // вложенная зависимость
     *
     * @param string|array $key — ключ или массив [секция, имя]
     * @param mixed $value — значение зависимости
     * @return self — для цепочек вызовов
     */
    public function set(string|array $key, mixed $value): self
    {
        if (is_array($key)) {
            [$parent, $child] = $key;
            $this->container[$parent][$child] = $value;
        } else {
            $this->container[$key] = $value;
        }

        return $this;
    }

    /**
     * Проверяет наличие зависимости по ключу
     *
     * @param string $key — имя зависимости
     * @return bool — true, если существует
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->container);
    }

    /**
     * Возвращает все зависимости контейнера
     *
     * @return array — весь контейнер
     */
    public function all(): array
    {
        return $this->container;
    }

    /**
     * Создаёт экземпляр класса, используя контейнер (если есть зависимости).
     */
    public function make(string $class): mixed
    {
        if (!class_exists($class)) {
            throw new \RuntimeException("DI: класс '$class' не найден.");
        }

        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new \RuntimeException("DI: класс '$class' не может быть создан.");
        }

        $constructor = $reflection->getConstructor();
        if (is_null($constructor)) {
            return new $class;
        }

        $params = array_map(function ($param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                return $this->get($type->getName());
            }

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            throw new \RuntimeException("DI: не могу разрешить параметр {$param->getName()}");
        }, $constructor->getParameters());

        return $reflection->newInstanceArgs($params);
    }
}
