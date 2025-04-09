<?php

namespace Core\Services\Modules;

use Core\Services\Container\DI;
use Core\Services\Path\Path;

class LanguageModules
{
    public static array $moduleLanguages = [];


    public static function get(): array
    {
        $module = Path::module();

        foreach (scandir($module) as $item) {
            if (in_array($item, ['.', '..'], true)) {
                continue;
            }

            if (!is_dir($module . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . 'Language')) {
                continue;
            }
            foreach (scandir($module . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . 'Language') as $lang) {
                if (in_array($lang, ['.', '..'], true)) {
                    continue;
                }

                LanguageConfig::load($item, $lang);

                self::$moduleLanguages[$item][$lang] = LanguageConfig::getDates();
            }
        }

        DI::instance()->set(['module', 'languages'], self::$moduleLanguages);

        return self::$moduleLanguages;
    }
}