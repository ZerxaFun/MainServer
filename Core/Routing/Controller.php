<?php

namespace Core\Routing;

use Core\Services\Auth\Contracts\AuthServiceInterface;
use Core\Template\Engine;
use DI;
use JsonException;
use View;
use JetBrains\PhpStorm\NoReturn;

/**
 * Базовый контроллер для всех модулей
 *
 * Поддерживает:
 * - передачу данных в шаблон
 * - рендер через Layout::get()
 * - API-ответы
 * - инициализацию текущего модуля и темы
 */
abstract class Controller
{
    /**
     * Имя layout-шаблона, который будет использоваться (без .php)
     */
    public static string $layout = 'layout';

    /**
     * Набор данных, которые передаются во view-файлы
     */
    protected static array $data = [];

    /**
     * Устанавливает переменную для шаблона
     *
     * @param string $key
     * @param mixed $value
     */
    public static function setData(string $key, mixed $value): void
    {
        static::$data[$key] = $value;
    }

    /**
     * Возвращает все текущие переменные
     *
     * @return array
     */
    public static function getData(): array
    {
        return static::$data;
    }

    /**
     * Добавляет значение в API-ответ (отложенный)
     */
    public static function apiAdd(string $key, mixed $value): void
    {
        APIControllers::addResult($key, $value);
    }

    /**
     * Полностью заменяет API-ответ (если нужен полный reset)
     */
    public static function apiSetRaw(array $data): void
    {
        APIControllers::setRawResult($data);
    }

    /**
     * Финализирует API-ответ (аналог api(), но с буфером)
     */
    #[NoReturn]
    public static function apiFlush(int $code = 200, string $status = 'success'): void
    {
        APIControllers::set($code, $status);
    }

    /**
     * Очистка буфера API-ответа (по желанию)
     */
    public static function apiClear(): void
    {
        APIControllers::clear();
    }

    /**
     * Инициализация контроллера:
     * - определяет активный модуль через Router
     * - подключает тему (если есть)
     * - сохраняет модуль в DI и в шаблонные данные
     *
     * @return void
     */
    public static function init(): void
    {
        $router = new Router();
        $module = $router::module();

        // Сразу устанавливаем модуль в DI, чтобы он был доступен везде
        DI::instance()->set(['module', 'this'], $module);

        // Тема грузится только для обычных модулей


            if (!DI::instance()->has('theme')) {
                $themeEngine = new Engine();
                DI::instance()->set('theme', $themeEngine);
            }

            $theme = DI::instance()->get('theme');

            if (array_key_exists($module->module, $theme->use)) {
                $themeName = $theme->use[$module->module];
                $themeDir = $theme->themeDir;

                $module->theme = [
                    'dir'    => $themeDir . DIRECTORY_SEPARATOR . $themeName,
                    'name'   => $themeName,
                    'public' => $theme->themes[$module->module][$themeName]['resources']['public'] ?? '',
                ];
            }


        // После наполнения темы — прокидываем в шаблон
        self::setData('module', $module);
    }


    /**
     * Быстрый рендер шаблона без layout
     *
     * @param string $template
     * @param array $data
     * @return View
     */
    public function view(string $template, array $data = []): View
    {
        return View::make($template, array_merge(self::getData(), $data));
    }

    protected function user(): AuthServiceInterface
    {
        return DI::instance()->get(AuthServiceInterface::class);
    }

    /**
     * Немедленно выводит API-ответ и завершает выполнение.
     */
    #[NoReturn]
    public static function api(array $data = [], int $code = 200, string $status = 'success'): void
    {
        APIControllers::setData($data, $code, 'json', $status);
    }

    /**
     * Добавляет данные в буфер (накапливает).
     */
    public static function setApi(array $data): void
    {
        foreach ($data as $key => $value) {
            APIControllers::addResult($key, $value);
        }
    }

    /**
     * Возвращает текущие буферизованные данные.
     */
    public static function getApi(): array
    {
        return APIControllers::getBuffer();
    }
}
