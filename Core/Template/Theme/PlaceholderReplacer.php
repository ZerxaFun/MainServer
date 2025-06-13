<?php

namespace Core\Template\Theme;

class PlaceholderReplacer
{
    public static function replace(array $data, string $template): string
    {
        return str_ireplace(array_keys($data), array_values($data), $template);
    }

    public static function replaceBlocks(array $blockData, string $template): string
    {
        if (empty($blockData)) {
            return $template;
        }

        foreach ($blockData as $key => $value) {
            $template = preg_replace($key, $value, $template);
        }

        return $template;
    }
}
