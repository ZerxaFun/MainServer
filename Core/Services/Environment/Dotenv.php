<?php

namespace Core\Services\Environment;

use Core\Services\Path\Path;

class Dotenv
{
    /**
     * Установка маршрута к файлу окружения
     *
     * @param string $path
     */
    public function __construct(string $path)
    {
        self::load($path);
    }

    public static function initialize(string $path = ''): Dotenv
    {
        if ($path === '') {
            $path = Path::base() . '.env';
        }

        return new Dotenv($path);
    }

    /**
     * Загрузка файла окружения
     *
     * @param string $path              - путь к файлу
     * @param string ...$extraPaths     - путь к дополнительным файлам
     */
    public static function load(string $path, string ...$extraPaths): void
    {
        self::doLoad(func_get_args());
    }

    public static function populate(array $values): void
    {
        foreach ($values as $name => $value) {
            $_ENV[$name] = $value;
        }
    }

    public static function parse(string $data): array
    {
        $lines = explode("\n", $data);
        $values = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Пропускаем пустые строки и комментарии
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // Разделение строки по первому знаку '='
            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue; // Пропускаем строки без знака '='
            }

            $name = trim($parts[0]);
            $value = trim($parts[1]);

            // Удаление кавычек, если значение в них заключено
            if ($value && ($value[0] === '"' && $value[-1] === '"' || $value[0] === "'" && $value[-1] === "'")) {
                $value = substr($value, 1, -1);
            }

            $values[$name] = $value;
        }

        return $values;
    }

    private static function doLoad(array $paths): void
    {
        foreach ($paths as $path) {
            $data = file_get_contents($path);
            $parsed = self::parse($data);
            self::populate($parsed);
        }
    }
}
