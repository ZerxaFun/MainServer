<?php
declare(strict_types=1);

/**
 *=====================================================
 * Majestic Next Engine                               =
 *=====================================================
 *
 * @package   Path.php                              =
 *-----------------------------------------------------
 * @url http://majestic-studio.com/                   =
 *-----------------------------------------------------
 * @copyright 2021 Majestic Studio                 =
 *=====================================================
 * @author    ZerxaFun aKa Zerxa                         =
 *=====================================================
 * @license   GPL version 3                            =
 *=====================================================
 *                                                    =
 *                                                    =
 *=====================================================
 */

namespace Core\Services\Path;

use Core\Services\Container\DI;
use Exception;

/**
 *
 * @package Core\Services\Path
 */
class Path
{
    /**
     * Путь к корню проекта
     * @var string
     */
    private string $basePath;

    /**
     * Слеш разделения папок для различных операционных систем
     *
     * @var string
     */
    private string $separator = DIRECTORY_SEPARATOR;

    /**
     * Название папки содержащей в себе различные плагины и темы проекта
     *
     * @var string
     */
    private string $contentDir = 'Content';
    private string $configDir = 'Config';
    private string $cacheDir = 'cache';

    /**
     * Название папки содержащей в себе модули проекта
     *
     * @var string
     */
    private string $moduleDir = 'Modules';

    /**
     * Директория логов системы
     *
     * @var string
     */
    private string $logDir = 'Logs';
    private string $exceptionLogDir = 'Exception';
    private string $userLogDir = 'Users';

    /**
     * Директория содержащая в себе различные ресурсы, в основном содержит файлы стиля и различные скрипты
     *
     * @var string
     */
    private string $resourceDir = 'Resource';

    /**
     * Публичная папка для сервера
     *
     * @var string
     */
    private string $publicDir = 'Public';

    /**
     * Директория тем
     *
     * @var string
     */
    private string $themeDir = 'Themes';


    private string $upload = 'uploads';

    /**
     * Директория плагинов
     *
     * @var string
     */
    private string $pluginsDir = 'plugins';

    public string $httpPath = '/';


    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->basePath = DI::instance()->get('baseDir');
        if (!in_array('HTTP_HOST', $_SERVER, true)) {
            $this->httpPath = '';
        } else {
            $this->httpPath = $_SERVER['HTTP_HOST'];
        }
    }

    private static function instance(): Path
    {
        return new self();
    }

    private static function path(string $section, string $path = ''): string
    {
        $sep = self::instance()->separator;

        $result = match ($section) {
            'base'          => self::instance()->basePath,
            'cache'          => self::instance()->basePath . $sep . self::instance()->cacheDir,
            'config'        => self::instance()->basePath . $sep . self::instance()->configDir,
            'content'       => self::instance()->basePath . $sep . self::instance()->contentDir,
            'module'        => self::instance()->basePath . $sep . self::instance()->moduleDir,
            'log.user'      => self::instance()->basePath . $sep . self::instance()->logDir . $sep . self::instance()->userLogDir,
            'log.exception' => self::instance()->basePath . $sep . self::instance()->logDir . $sep . self::instance()->exceptionLogDir,
            'resource'      => self::instance()->basePath . $sep . self::instance()->resourceDir,
            'public'        => self::instance()->basePath . $sep . self::instance()->publicDir,
            'theme'         => self::content() . self::instance()->themeDir,
            'plugin'        => self::content() . self::instance()->pluginsDir,
            'uploads'        => self::instance()->basePath . $sep . self::instance()->publicDir . $sep . self::instance()->upload,
        };

        if($path !== '') {
            $result .= $sep . $path;
        }

        return $result . $sep;
    }


    public static function http(string $section, string $path = ''): string
    {
        $sep = '/';

        $result = match ($section) {
            'base'          => self::instance()->httpPath,
            'module'        => self::instance()->httpPath . $sep . self::instance()->moduleDir,
            'cache'         => self::instance()->httpPath . $sep . self::instance()->cacheDir,
            'log'           => self::instance()->httpPath . $sep . self::instance()->logDir,
            'log.user'      => self::instance()->httpPath . $sep . self::instance()->logDir . $sep . self::instance()->userLogDir,
            'log.exception' => self::instance()->httpPath . $sep . self::instance()->logDir . $sep . self::instance()->exceptionLogDir,
            'resource'      => self::instance()->httpPath . $sep . self::instance()->resourceDir,
            'public'        => self::instance()->httpPath . $sep . self::instance()->publicDir,
            'content'       => self::instance()->httpPath . $sep . self::instance()->contentDir,
            'theme'         => self::instance()->httpPath . $sep . self::instance()->contentDir . $sep . self::instance()->themeDir,
            'plugin'        => self::instance()->httpPath . $sep . self::instance()->contentDir . $sep . self::instance()->pluginsDir,
            'upload'        => self::instance()->httpPath . $sep . self::instance()->upload,
        };

        if($path !== '') {
            $result .= $sep . $path;
        }

        return $sep . $sep . $result . $sep;
    }

    public static function base(string $path = ''): string
    {
        return self::path('base', $path);
    }

    public static function content(string $path = ''): string
    {
        return self::path('content', $path);
    }

    public static function theme(string $module = ''): string
    {
        return self::path('theme', $module);
    }

    public static function plugin(string $pluginName = ''): string
    {
        return self::path('plugin', $pluginName);
    }

    public static function upload(string $folder = ''): string
    {
        return self::path('uploads', $folder);
    }

    public static function module(string $moduleName = ''): string
    {

        return self::path('module', $moduleName);
    }

    public static function cache(string $cache = ''): string
    {
        return self::path('cache', $cache);
    }

    public static function logs(string $path = ''): string
    {
        return self::path('logs', $path);
    }

    public static function userLog(string $userName = ''): string
    {
        return self::path('log.user', $userName);
    }

    public static function exceptionLog(): string
    {
        return self::path('log.exception');
    }

    public static function resource(string $path = ''): string
    {
        return self::path('resource', $path);
    }

    public static function public(string $path = ''): string
    {
        return self::path('public', $path);
    }


    public static function caches(string $path = ''): string
    {
        return self::path('cache', $path);
    }


    public static function config(): string
    {
        return self::path('config');
    }
}
