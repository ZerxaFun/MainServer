<?php

namespace Core\Services\Localization;

class Lang
{
    /**
     * @param string $key
     * @param array $data
     */
    public static function getString(string $key, array $data = []): void
    {
        echo I18n::instance()->get($key, $data);
    }

    /**
     * @param string $key
     * @param array $data
     * @return string
     */
    public static function get(string $key, array $data = []): string
    {
        return I18n::instance()->get($key, $data);
    }
}