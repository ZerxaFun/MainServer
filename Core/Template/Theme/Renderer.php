<?php

namespace Core\Template\Theme;

use Core\Services\Config\Config;
use Core\Services\Http\Uri;
use Core\Services\Container\DI;

class Renderer
{
    public function render(string $template, array $data, array $blockData, string $baseDir, string $container = 'main'): string
    {
        $template = ComponentResolver::resolve($template, $baseDir);
        $template = I18nProcessor::process($template);

        $headers = HeaderBuilder::build();

        $defaultVars = [
            '{BASE_URL}'    => Uri::base(),
            '{ASSETS_URI}'  => Uri::assets(),
            '{THEME}'       => DI::instance()->get('module')['this']->theme['public'],
            '{headers}'     => $headers,
            '{site_name}'   => $_ENV['project_name'] ?? 'Site',
        ];

        $template = PlaceholderReplacer::replace(array_merge($defaultVars, $data), $template);
        return PlaceholderReplacer::replaceBlocks($blockData, $template);
    }
}
