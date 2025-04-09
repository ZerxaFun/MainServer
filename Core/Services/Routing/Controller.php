<?php

namespace Core\Services\Routing;

use Core\Services\Template\Engine;
use DI;
use Exception;
use Layout;
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
     * Быстрый API-ответ (в JSON)
     *
     * @param array $result
     * @param int $statusCode
     * @param string $status
     * @return void
     */
    #[NoReturn]
    public static function api(array $result = [], int $statusCode = 200, string $status = 'success'): void
    {
        APIControllers::setData($result, $statusCode, $status);
    }

    /**
     * Инициализация контроллера:
     * - определяет активный модуль через Router
     * - подключает тему (если есть)
     * - сохраняет модуль в DI и в шаблонные данные
     *
     * @return void
     */
    public function init(): void
    {
        $router = new Router();
        $module = $router::module();

        // Сразу устанавливаем модуль в DI, чтобы он был доступен везде
        DI::instance()->set(['module', 'this'], $module);

        // Тема грузится только для обычных модулей
        if ($module->type === 'module') {

            if (!DI::instance()->has('theme')) {
                $themeEngine = new \Core\Services\Template\Engine();
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


    /**
     * @param string $template
     * @param array $data
     * @return View
     */
    public function render(string $template = 'index', array $data = []): View
    {
        return View::make($template, array_merge(static::$data, $data));
    }
}
