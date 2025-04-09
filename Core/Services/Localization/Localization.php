<?php
/*
 *  *=====================================================
 *  * Majestic Next Engine - by Zerxa Fun                =
 *  *-----------------------------------------------------
 *  * @url: http://majestic-studio.com/                  =
 *  *-----------------------------------------------------
 *  * @copyright: 2021-2022 Majestic Studio and Zerxa    =
 *  *=====================================================
 *  * @license GPL version 3                             =
 *  *=====================================================
 *  * Localization.php -                                       =
 *  *=====================================================
 */

namespace Core\Services\Localization;

use Core\Services\Container\DI;

class Localization
{
    final public static function get(string $section, string $key)
    {
        $language = DI::instance()->get('i18n');

        if (!array_key_exists($section, $language) || !array_key_exists($key, $language[$section])) {
            return '{' . $section . '|' . $key . '}';
        }


        return $language[$section][$key];
    }
}