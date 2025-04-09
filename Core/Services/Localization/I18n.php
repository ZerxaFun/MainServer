<?php
/**
 *=====================================================
 * Majestic Engine - by Zerxa Fun (Majestic Studio)   =
 *-----------------------------------------------------
 * @url: http://majestic-studio.ru/                   -
 *-----------------------------------------------------
 * @copyright: 2020 Majestic Studio and ZerxaFun      -
 *=====================================================
 *                                                    =
 *                                                    =
 *                                                    =
 *=====================================================
 */


namespace Core\Services\Localization;


use Core\Services\Modules\LanguageConfig;
use Core\Services\Path\Path;
use DI;


/**
 * Класс для работы с локализацией проекта
 *
 * Class I18n
 */
class I18n
{

    private static null|object $instance = null;

    /**
     * @return I18n
     */
    public static function instance(): I18n
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param string $key
     * @param array $data
     * @return string
     */
    final public function get(string $key, string $section, array $data = []): string
    {
        $lang = DI::instance()->get('i18n')[$section];
        $text = $lang[$key] ?? '';

        if (!empty($data)) {
            $text = sprintf($text, ...$data);
        }

        return $text;
    }

    /**
     * @param string $file
     * @param string $module
     * @return I18n
     */
    final public function load(string $file, string $module = ''): static
    {

        $path = static::path($module) . $file . '.ini';

        $content = parse_ini_file($path, true);


        $lang = DI::instance()->get('i18n') ?: [];

        foreach ($content as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {

                    $lang[$file][$key . '.' . $k] = $v;
                }
            } else {
                $lang[$file][$key] = $value;
            }
        }


        DI::instance()->set('i18n', $lang);

        return $this;
    }

    /**
     * @param string $moduleName
     * @return string
     */
    private static function path(string $moduleName = ''): string
    {
        $module = DI::instance()->get('module');

        $moduleModuleName = $module['this']->module;

        if ($moduleName !== '') {
            $moduleModuleName = $moduleName;
        }

        $activeLang = LanguageConfig::$modules[$moduleModuleName]['select'];


        return Path::module() . sprintf('%s/Language/%s/', $moduleModuleName, $activeLang);
    }

    public static function all()
    {
        return DI::instance()->get('i18n') ?: [];
    }


    public static function _get(string $i18n)
    {
        $languages = [];
        foreach (DI::instance()->get('i18n') as $key => $item) {
            foreach ($item as $language => $value) {
                $languages[str_replace('/', '.', $key) . '.' . $language] = $value;
            }

        }



        if (array_key_exists($i18n, $languages)) {
            return $languages[$i18n];
        }

        return $i18n;

    }
}
