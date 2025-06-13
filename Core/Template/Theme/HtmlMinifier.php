<?php

namespace Core\Template\Theme;

/**
 * Класс для минификации HTML-контента
 */
class HtmlMinifier
{
    /**
     * Минификация HTML-контента
     *
     * @param string $html
     * @return string
     */
    public static function minify(string $html): string
    {
        $search = [
            // Удаляем пробелы после тега
            '/>[^\S ]+/s',

            // Удаляем пробелы перед тегом
            '/[^\S ]+</s',

            // Удаляем множественные пробелы
            '/(\s)+/s',

            // Удаляем комментарии
            '/<!--(.|\s)*?-->/',

            '/>(.|\s) </'
        ];
        $replace = ['>', '<', '\\1', '><'];

        return preg_replace($search, $replace, $html);
    }
}
