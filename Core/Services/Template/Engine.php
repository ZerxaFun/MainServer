<?php

namespace Core\Services\Template;

use Core\Services\Config\Config;
use Core\Services\Container\DI;
use Core\Services\Path\Path;
use Core\Services\Routing\Module;
use Core\Services\Routing\Router;
use JsonException;
use RuntimeException;


class Engine
{
    public array $themes = [];
    public array $use = [];
    public string $themeDir;
    public string $pathTheme;

    public function __construct()
    {
        $module = Router::module(); // Получаем текущий модуль
        $this->themeDir = Path::module("{$module->module}/Themes");

        $this->use = Config::group('theme');
        $this->loadThemes();
        $this->validateThemes();

        DI::instance()->set('theme', $this);
    }

    public function ViewDirectory(): string
    {
        $module = Router::module();
        return Path::module("{$module->module}/View");
    }

    private function loadThemes(): void
    {
        foreach (scandir($this->themeDir) as $themeDir) {
            if ($themeDir === '.' || $themeDir === '..') continue;

            $themePath = $this->themeDir . $themeDir;
            if (!is_dir($themePath)) continue;

            $manifestPath = $themePath . DIRECTORY_SEPARATOR . 'manifest.json';
            if (!is_file($manifestPath)) {
                throw new RuntimeException("Manifest not found for theme '$themeDir'");
            }

            // Чтение manifest.json
            $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            $assetsDir = $manifest['assets'] ?? 'assets'; // Получаем assets из manifest
            $resourcesDir = $themePath . DIRECTORY_SEPARATOR . $assetsDir;

            if (!is_dir($resourcesDir)) {
                throw new RuntimeException("Assets directory missing in theme '$themeDir'");
            }

            $publicPath = Path::public() . 'assets/' . $themeDir;
            if (!is_link($publicPath)) {
                if (!is_dir(dirname($publicPath))) {
                    mkdir(dirname($publicPath), 0777, true);
                }
                symlink($resourcesDir, $publicPath);
            }

            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

            $this->themes[$themeDir] = [
                'path' => [
                    'theme'     => $themePath,
                    'resources' => $resourcesDir,
                    'manifest'  => $manifestPath,
                ],
                'manifest' => $manifest,
                'resources' => [
                    'in'      => $resourcesDir,
                    'path'    => $publicPath,
                    'symlink' => true,
                    'public'  => "//$host/assets/$themeDir"
                ]
            ];
        }
    }

    private function validateThemes(): void
    {
        foreach ($this->use as $module => $theme) {
            if (!isset($this->themes[$theme])) {
                throw new RuntimeException("Theme '$theme' not found for module '$module'");
            }
        }
    }
}
