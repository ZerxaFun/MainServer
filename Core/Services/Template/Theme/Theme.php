<?php

namespace Core\Services\Template\Theme;

use Core\Services\Container\DI;
use Core\Services\Modules\LanguageConfig;
use Core\Services\Path\Path;
use Core\Services\Http\Uri;

class Theme
{
    private string $template = '';
    private string $copyTemplate = '';
    private array $data = [];
    private array $blockData = [];
    private array $result = [];
    private string $themeDir;
    private TemplateLoader $loader;
    private PlaceholderReplacer $replacer;
    private ComponentResolver $components;
    private I18nProcessor $i18n;
    private HeaderBuilder $headers;
    private Renderer $renderer;

    public static string $header = '';

    public function __construct()
    {
        // Автоматическое определение пути к теме через DI
        $module = DI::instance()->get('module')['this'];

        $this->themeDir = $module->theme['dir'] ?? '';

        if (!$this->themeDir) {
            throw new \RuntimeException("Не указан путь к теме. Убедись, что theme['dir'] установлен.");
        }

        $this->loader = new TemplateLoader();
        $this->replacer = new PlaceholderReplacer();
        $this->components = new ComponentResolver();
        $this->i18n = new I18nProcessor();
        $this->headers = new HeaderBuilder();
        $this->renderer = new Renderer();

        DI::instance()->set('themeInstance', $this); // если надо глобально
    }

    /**
     * Загрузка MJT-шаблона (например: layout.mjt)
     */
    public function load(string $templateName): void
    {

        $this->template = $this->loader->load($templateName, $this->themeDir);
        $this->copyTemplate = $this->template;
    }

    /**
     * Установка переменной (или массива переменных)
     */
    public function set(string $name, mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $this->set($key, $val);
            }
        } else {
            $this->data[$name] = $value;
        }
    }

    /**
     * Установка блока
     */
    public function set_block(string $name, mixed $value): void
    {
        $this->blockData[$name] = $value;
    }

    /**
     * Отображение финального результата шаблона
     */
    public function result(string $container = 'main', bool $minify = false): void
    {
        // Строим заголовки, если они не заданы
        if (self::$header === '') {
            self::$header = $this->headers->build();
        }

        $this->set('{headers}', self::$header);

        // Вставляем базовые переменные
        $this->set('{BASE_URL}', Uri::base());
        $this->set('{ASSETS_URI}', Uri::assets());
        $this->set('{THEME}', DI::instance()->get('module')['this']->theme['public'] ?? '');
        $this->set('{site_name}', $_ENV['project_name'] ?? 'Site');

        // Шаг 1. Вставляем переменные
        $this->copyTemplate = $this->replacer->replace($this->data, $this->copyTemplate);

        // Шаг 2. Компоненты
        $this->copyTemplate = $this->components->resolve($this->copyTemplate, $this->themeDir);

        // Шаг 3. Мультиязычные блоки
        $this->copyTemplate = $this->i18n->process($this->copyTemplate);

        // Шаг 4. Финальный рендер + блоки
        $output = $this->renderer->render(
            $this->copyTemplate,       // Шаблон
            $this->data,               // Данные
            $this->blockData,          // Блоки
            $this->themeDir,           // Путь к директории шаблона
            $container                 // Контейнер
        );

        $this->result[$container] = $output;

        echo $output;
    }

    /**
     * Очистка всех данных (глобальная)
     */
    public function global_clear(): void
    {
        $this->data = [];
        $this->blockData = [];
        $this->copyTemplate = $this->template;
        $this->result = [];
    }

    /**
     * Алиас для обратной совместимости (старый метод)
     */
    public function load_template(string $templateName): void
    {
        $this->load($templateName);
    }
}
