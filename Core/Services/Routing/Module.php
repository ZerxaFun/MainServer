<?php

namespace Core\Services\Routing;

use Core\Services\Path\Path;
use JsonException;
use RuntimeException;

/**
 * Класс Module содержит информацию о текущем маршруте, типе и контроллере
 */
class Module
{
    /**
     * @var string Тип модуля: module | api | cli
     */
    public string $type = 'module';

    /**
     * @var string Имя модуля (папка в /Modules)
     */
    public string $module = '';

    /**
     * @var string Имя контроллера
     */
    public string $controller = '';

    /**
     * @var string Имя метода (action)
     */
    public string $action = '';

    /**
     * @var array Параметры, переданные маршрутом
     */
    public array $parameters = [];

    /**
     * @var array Уровни защиты маршрута
     */
    public array $protected = [];

    /**
     * @var array Тема (настройки, если переданы)
     */
    public array $theme = [];

    /**
     * @var array Атрибуты маршрута (Authorize и др.)
     */
    public array $attributes = [];

    /**
     * @var bool Флаг sitemap (если нужно)
     */
    public bool $sitemap = false;

    /**
     * @var bool Флаг мультиязычности
     */
    private bool $multiLanguage = false;

    /**
     * @var Controller|null Экземпляр контроллера (только для type = module)
     */
    public ?Controller $instance = null;

    /**
     * Конструктор: инициализирует объект из массива
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // Атрибуты — явно обрабатываем отдельно (вдруг не было в property_exists)
        if (isset($config['attributes']) && is_array($config['attributes'])) {
            $this->attributes = $config['attributes'];
        }

        if (empty($this->module)) {
            throw new RuntimeException("Ошибка: module->module пуст в Module::__construct(). Конфиг: " . print_r($config, true));
        }
    }

    /**
     * Возвращает экземпляр контроллера, если есть
     *
     * @return Controller|null
     */
    public function instance(): ?Controller
    {
        return $this->instance;
    }

    /**
     * Возвращает, поддерживает ли модуль мультиязык
     *
     * @return bool
     */
    public function supportsMultiLanguage(): bool
    {
        return $this->multiLanguage;
    }

    /**
     * Устанавливает флаг мультиязычности (можно вызвать из ModuleRunner)
     *
     * @param bool $value
     */
    public function setMultiLanguage(bool $value): void
    {
        $this->multiLanguage = $value;
    }

    /**
     * Загружает и возвращает содержимое манифеста модуля
     *
     * @return array
     * @throws JsonException
     */
    public function getManifest(): array
    {
        $path = Path::module($this->module) . 'manifest.json';

        if (!is_file($path)) {
            throw new RuntimeException("Файл manifest.json не найден: $path");
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function permissions(): array
    {
        return $this->attributes['Authorize']['permission'] ?? [];
    }

}
