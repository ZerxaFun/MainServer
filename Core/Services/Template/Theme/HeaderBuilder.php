<?php

namespace Core\Services\Template\Theme;

use Core\Services\Container\DI;
use Core\Services\Http\Uri;
use Core\Services\Modules\LanguageConfig;

class HeaderBuilder
{
    public static function build(): string
    {
        $module = DI::instance()->get('module')['this']->module;
        $languages = LanguageConfig::$modules[$module]['languages'];
        $headers = '';

        foreach ($languages as $lang) {
            $prefix = $lang['default'] ? '' : $lang['prefix'] . '/';
            $url = Uri::base() . '/' . $prefix . Uri::segmentString();
            $rel = $lang['default'] ? 'x-default' : $lang['iso'];
            $headers .= "<link rel='alternate' hreflang='$rel' href='$url'>\n";
        }

        return $headers;
    }
}
