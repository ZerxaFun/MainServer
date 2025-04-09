<?php

namespace Core\Services\Template\Theme;

class ComponentResolver
{
    public static function resolve(string $template, string $baseDir): string
    {
        return preg_replace_callback(
            '#{component (.+?)}#i',
            fn($matches) => TemplateLoader::loadComponent($matches[1], $baseDir),
            $template
        );
    }
}
