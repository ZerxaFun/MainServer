<?php

namespace Core\Services\Modules;

use Core\Services\Path\Path;
use JsonException;
use RuntimeException;

class LanguageConfig
{
    /**
     * Все загруженные языки модулей
     * [
     *   'ModuleName' => [
     *     'module' => 'ModuleName',
     *     'languages' => [
     *       'ka' => [...],
     *       'en' => [...],
     *     ]
     *   ]
     * ]
     */
    public static array $modules = [];

    private static string $languageDir = 'Language';
    private static string $langManifest = 'lang.json';

    /**
     * Загрузка языковых конфигураций всех модулей
     *
     * @return void
     * @throws JsonException|RuntimeException
     */
    public static function load(): void
    {
        $moduleBasePath = Path::module();

        if (!is_dir($moduleBasePath)) {
            throw new RuntimeException('Директория Modules не существует.');
        }

        foreach (scandir($moduleBasePath) as $module) {
            if (in_array($module, ['.', '..'])) continue;

            $langPath = $moduleBasePath . $module . DIRECTORY_SEPARATOR . self::$languageDir;

            if (!is_dir($langPath)) continue;

            $languageDirs = array_filter(scandir($langPath), fn($entry) =>
                !in_array($entry, ['.', '..']) && is_dir($langPath . DIRECTORY_SEPARATOR . $entry)
            );

            if (empty($languageDirs)) continue;

            self::$modules[$module]['module'] = $module;

            foreach ($languageDirs as $languageDir) {
                $manifestPath = $langPath . DIRECTORY_SEPARATOR . $languageDir . DIRECTORY_SEPARATOR . self::$langManifest;

                if (!is_file($manifestPath)) {
                    throw new RuntimeException("Отсутствует $manifestPath");
                }

                $manifest = json_decode(file_get_contents($manifestPath), false, 512, JSON_THROW_ON_ERROR);

                if (!isset($manifest->Prefix, $manifest->iso, $manifest->name, $manifest->http)) {
                    throw new RuntimeException("Файл $manifestPath неполный — нужны Prefix, iso, name и http.");
                }

                self::$modules[$module]['languages'][$manifest->iso] = [
                    'name'    => $manifest->name,
                    'dir'     => $languageDir,
                    'prefix'  => $manifest->Prefix,
                    'iso'     => $manifest->iso,
                    'http'    => $manifest->http,
                    'default' => isset($manifest->default) && $manifest->default === true
                ];
            }

            // Назначаем default язык, если явно не указан
            $hasDefault = false;
            foreach (self::$modules[$module]['languages'] as $iso => $lang) {
                if (!empty($lang['default'])) {
                    $hasDefault = true;
                    break;
                }
            }

            if (!$hasDefault) {
                $first = array_key_first(self::$modules[$module]['languages']);
                self::$modules[$module]['languages'][$first]['default'] = true;
            }
        }
    }

    /**
     * Перезагрузка языков (например, после смены языка)
     */
    public static function reloadConfig(): void
    {
        self::load();
    }

    /**
     * Получить ISO-код языка по умолчанию для модуля
     */
    public static function getDefaultLanguageModule(string $module): string
    {
        $langs = self::$modules[$module]['languages'] ?? [];

        foreach ($langs as $iso => $lang) {
            if (!empty($lang['default'])) {
                return $iso;
            }
        }

        return array_key_first($langs); // fallback
    }
}
