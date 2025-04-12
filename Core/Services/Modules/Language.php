<?php

namespace Core\Services\Modules;

use Core\Routing\Router;
use Core\Services\Config\Config;
use Core\Services\Http\Redirect;
use Core\Services\Http\Uri;
use Core\Services\Session\Facades\Session;

class Language
{
    public static function init(): void
    {
        // пусто — всё по URI или сессии
    }

    public static function current(): string
    {
        $module = Router::module()->module;
        $langs = LanguageConfig::$modules[$module]['languages'] ?? [];

        $prefix = Uri::segmentOriginal(1);

        // 1. Префикс есть в URL → значит пользователь явно выбрал язык
        if ($prefix) {
            foreach ($langs as $iso => $lang) {
                if ($lang['prefix'] === $prefix) {
                    return $iso;
                }
            }
        }

        // 2. Есть язык в сессии
        if (Session::has('lang') && isset($langs[Session::get('lang')])) {
            return Session::get('lang');
        }

        // 3. Возвращаем язык по умолчанию
        return LanguageConfig::getDefaultLanguageModule($module);
    }

    public static function currentPrefix(): string
    {
        $module = Router::module()->module;
        $langs = LanguageConfig::$modules[$module]['languages'] ?? [];

        $current = self::current();

        return $langs[$current]['prefix'] ?? '';
    }

    public static function redirect(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') return;

        $module = Router::module()->module;
        $langs = LanguageConfig::$modules[$module]['languages'] ?? [];

        $prefix = Uri::segmentOriginal(1);

        // 1. Если префикс есть → пользователь явно выбрал язык
        if ($prefix) {
            foreach ($langs as $iso => $lang) {
                if ($lang['prefix'] === $prefix) {
                    Session::put('lang', $iso);
                    return;
                }
            }
        }

        // 2. Если нет префикса, но есть сохранённый язык в сессии
        if (!$prefix && Session::has('lang')) {
            $iso = Session::get('lang');

            if (isset($langs[$iso]) && !$langs[$iso]['default']) {
                Redirect::go('/' . $langs[$iso]['prefix'] . '/');
            }

            return;
        }

        // 3. Нет префикса и нет сессии — проверим включено ли автоопределение
        if (!$prefix && Config::item('useBrowserLang', 'main') === true) {
            $preferred = self::detectFromHeader();
            $default = LanguageConfig::getDefaultLanguageModule($module);

            if ($preferred && $preferred !== $default && isset($langs[$preferred])) {
                Redirect::go('/' . $langs[$preferred]['prefix'] . '/');
            }

            return;
        }

        // 4. Всё отключено — редиректим на default язык, если он не без префикса
        $default = LanguageConfig::getDefaultLanguageModule($module);

        if (!$prefix && isset($langs[$default]) && !$langs[$default]['default']) {
            Redirect::go('/' . $langs[$default]['prefix'] . '/');
        }
    }


    public static function detectFromHeader(): ?string
    {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return null;
        }

        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $parts = explode(',', $header);

        $module = Router::module()->module;
        $supported = array_keys(LanguageConfig::$modules[$module]['languages'] ?? []);

        foreach ($parts as $part) {
            $code = substr(trim($part), 0, 2);
            if (in_array($code, $supported, true)) {
                return $code;
            }
        }

        return null;
    }
}
