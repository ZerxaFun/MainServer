<?php

namespace Core\Template\Theme;

use Core\Services\Container\DI;
use Core\Services\Http\Uri;
use Core\Services\Modules\LanguageConfig;

class HeaderBuilder
{
    public static function build(): string
    {
        $module = DI::instance()->get('module')['this']->module;
        $languages = LanguageConfig::$modules[$module]['languages'] ?? [];
        $defaultIso = LanguageConfig::getDefaultLanguageModule($module);

        $uri = Uri::segmentString();
        $base = rtrim(Uri::base(), '/');
        $headers = '';

        foreach ($languages as $lang) {
            $isDefault = $lang['iso'] === $defaultIso;

            // Префикс только если язык не по умолчанию
            $prefix = $isDefault ? '' : $lang['prefix'] . '/';
            $url = $base . '/' . ltrim($prefix . $uri, '/');

            // Убираем двойные слеши и лишний мусор
            $url = preg_replace('#/+#', '/', $url);
            $url = preg_replace('#:/#', '://', $url); // восстановим http://

            $headers .= sprintf(
                "<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\" />\n",
                $lang['iso'],
                $url
            );
        }

        // x-default → указывает на язык по умолчанию
        if (isset($languages[$defaultIso])) {
            $lang = $languages[$defaultIso];
            $prefix = $lang['default'] ? '' : $lang['prefix'] . '/';
            $defaultUrl = $base . '/' . ltrim($prefix . $uri, '/');

            $defaultUrl = preg_replace('#/+#', '/', $defaultUrl);
            $defaultUrl = preg_replace('#:/#', '://', $defaultUrl);

            $headers .= "<link rel=\"alternate\" hreflang=\"x-default\" href=\"{$defaultUrl}\" />\n";
        }

        return $headers;
    }
}
