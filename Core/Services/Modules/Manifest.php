<?php

namespace Core\Services\Modules;

use Core\Services\Path\Path;

class Manifest
{
    /**
     * Название модуля
     *
     * @var string
     */
    private static string $moduleActive;

    /**
     * Статичное название файла конфигруации
     *
     * @var string
     */
    public static string $manifest = 'manifest.json';

    /**
     * Массив данных конфигурации модуля
     *
     * @var object
     */
    public static object $data;


    /**
     * Подключение и загрузка данных конфигурации файла manifest.json указанного модуля
     *
     * @param string $module
     * @return void
     */
    public static function load(string $module): void
    {

        if(is_dir(Path::module($module))) {
            self::$moduleActive = $module;
            if (is_file(Path::module($module) . self::$manifest)) {
                self::parseJson(file_get_contents(Path::module($module) . self::$manifest));
            } else {
                throw new \RangeException(
                    sprintf(
                        'Конфигурационный файл модуля %s отсутствует', $module
                    )
                );
            }
        } else {
            throw new \RangeException(
                sprintf(
                    'Указаный модуль %s не существует', $module
                )
            );
        }
    }


    private static function parseJson(string $jsonString): void
    {
        $json = json_decode($jsonString);

        self::$data = $json;
    }

    public static function getDates(): object
    {
        return self::$data;
    }

    public static function getData(string $key): bool|string
    {
        if (array_key_exists($key, (array) self::$data)) {
            return self::$data->$key;
        } else {
            return self::$data->$key = false;
        }
    }

}