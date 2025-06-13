<?php

namespace Core\Template\Theme;

use Core\Services\Container\DI;

class I18nProcessor
{
    public static function process(string $template): string
    {
        $template = preg_replace_callback('/\((lang=([^)]+))\)/', fn($m) => self::langText($m[2]), $template);

        return preg_replace_callback('/\((i18nLink=([^|]+)\s*\|\s*([^)]+))\)/', function ($m) {
            $text = self::langText(str_replace(['[', ']'], '', $m[3]));
            $prefix = self::getLangPrefix();
            return "<a href=\"/$prefix{$m[2]}\">$text</a>";
        }, $template);
    }

    private static function langText(string $code): string
    {
        $parts = explode('|', $code);
        $i18n = DI::instance()->get('i18n') ?? [];

        return $i18n[$parts[0]][$parts[1]] ?? "[!$code!]";
    }

    private static function getLangPrefix(): string
    {
        $lang = DI::instance()->get('language');
        return $lang['languages'][$lang['select']]['prefix'] ?? '';
    }
}
