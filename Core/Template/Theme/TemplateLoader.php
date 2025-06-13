<?php

namespace Core\Template\Theme;

use RuntimeException;

class TemplateLoader
{
    public static function load(string $templatePath, string $baseDir): string
    {
        $templatePath = str_replace(chr(0), '', $templatePath);

        if (str_contains($templatePath, '.php')) {
            throw new RuntimeException("Недопустимое имя шаблона: $templatePath");
        }

        $fullPath = $baseDir . DIRECTORY_SEPARATOR . $templatePath;

        if (!file_exists($fullPath)) {
            throw new RuntimeException("Шаблон не найден: $fullPath");
        }

        $content = file_get_contents($fullPath);
        return preg_replace("'{\\*(.*?)\\*}'si", '', $content);
    }

    public static function loadComponent(string $componentPath, string $baseDir): string
    {
        $sanitizedPath = str_replace([chr(0), '..', '/', '\\'], '', $componentPath);
        $fullPath = $baseDir . $sanitizedPath;

        if (!file_exists($fullPath)) {
            throw new RuntimeException("Компонент не найден: $sanitizedPath");
        }

        return file_get_contents($fullPath);
    }
}
