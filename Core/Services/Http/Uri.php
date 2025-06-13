<?php

namespace Core\Services\Http;

use Core\Routing\Router;
use Core\Services\Modules\LanguageConfig;
use Core\Services\Session\Facades\Session;
use Route;

class Uri
{
    protected static string $base = '';
    protected static string $assets = '';
    protected static string $uri = '';
    protected static string $host = '';

    public static array $segments = [];
    public static array $segmentsOriginal = [];

    public static function initialize(): void
    {
        header('X-Powered-By: Majestic Next Engine');

        if (isset($_SERVER['REQUEST_URI'])) {
            $request = $_SERVER['REQUEST_URI'];
            $host = $_SERVER['HTTP_HOST'];
            $protocol = 'http' . (Request::https() ? 's' : '');
            $base = $protocol . '://' . $host;
            $assets = '//' . $host;
            $uri = $base . $request;

            $length = strlen($base);
            $str = substr($uri, $length);
            $arr = explode('/', trim($str, '/'));
            $segments = [];

            foreach ($arr as $segment) {
                if ($segment !== '') {
                    $segments[] = $segment;
                }
            }

            static::$base = $base;
            static::$host = $host;
            static::$assets = $assets;
            static::$uri = $uri;
            static::$segments = $segments;
            static::$segmentsOriginal = $segments;

        } elseif (isset($_SERVER['argv'])) {
            $segments = [];
            foreach ($_SERVER['argv'] as $arg) {
                if ($arg !== $_SERVER['SCRIPT_NAME']) {
                    $segments[] = $arg;
                }
            }

            static::$segments = $segments;
            static::$segmentsOriginal = $segments;
        }
    }

    public static function backSegment() { return $_SERVER['HTTP_REFERER'] ?? ''; }
    public static function segmentLast() { return array_pop(static::$segments); }

    public static function main(): bool
    {
        return (self::segmentStringOriginal() === '') || (self::prefixLanguage() === self::segmentOriginal(1));
    }

    public static function base(): string { return static::$base; }
    public static function host(): string { return static::$host; }
    public static function assets(): string { return static::$assets; }
    public static function uri(): string { return static::$uri; }
    public static function segments(): array { return static::$segments; }

    public static function url(string $uri = ''): string { return static::base() . ltrim($uri, '/'); }

    public static function segment(int $num): string
    {
        if (array_key_exists(0, static::$segments)) {
            $langList = LanguageConfig::$modules[Route::$module]['languages'] ?? [];
            $prefixes = array_column($langList, 'prefix');

            if (in_array(static::$segments[0], $prefixes, true)) {
                array_shift(static::$segments);
            }
        }

        --$num;
        return static::$segments[$num] ?? '';
    }

    public static function segmentOriginal(int $num): ?string
    {
        --$num;
        return static::$segmentsOriginal[$num] ?? null;
    }

    public static function segmentString(): string
    {
        return implode('/', static::$segments);
    }

    public static function segmentStringOriginal(): string
    {
        return implode('/', static::$segmentsOriginal);
    }

    public static function segmentLanguage(): string
    {
        $prefix = self::segmentOriginal(1);
        $module = Route::$module ?? Router::module()->module;
        $languages = LanguageConfig::$modules[$module]['languages'] ?? [];

        foreach ($languages as $lang) {
            if ($lang['prefix'] === $prefix) {
                return $lang['iso'];
            }
        }

        return LanguageConfig::getDefaultLanguageModule($module);
    }

    public static function prefixLanguage(): string
    {
        $langList = LanguageConfig::$modules[Route::$module]['languages'] ?? [];
        $map = [];
        foreach ($langList as $lang) {
            $map[$lang['prefix']] = $lang['prefix'];
        }

        return $map[self::segmentOriginal(1)] ?? array_values($map)[0] ?? '';
    }

    public static function getSegment(): string
    {
        return str_replace(self::prefixLanguage() . '/', '', self::segmentString());
    }

    public static function get(string $name = '')
    {
        $result = [];
        foreach ($_GET as $key => $item) {
            $result[] = [
                'key' => $key,
                'get' => $item
            ];
        }

        if ($name !== '') {
            return $result[$name] ?? null;
        }

        return $result;
    }
}
