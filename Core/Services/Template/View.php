<?php
/**
 *=====================================================
 * Majestic Engine - by Zerxa Fun (Majestic Studio)   =
 *-----------------------------------------------------
 * @url: http://majestic-studio.ru/                   -
 *-----------------------------------------------------
 * @copyright: 2020 Majestic Studio and ZerxaFun      -
 *=====================================================
 *                                                    =
 *                                                    =
 *                                                    =
 *=====================================================
 */

namespace Core\Services\Template;

use Core;
use Core\Services\Routing\Router;
use Exception;
use JetBrains\PhpStorm\Pure;
use RuntimeException;

/**
 * Class View
 * @package Core\Services\Template
 */
class View
{
    /**
     * @var string The view file.
     */
    private string $file = '';

    /**
     * @var array The view data.
     */
    private array $data = [];

    /**
     * @var string
     */
	public const TEMPLATE_EXTENSION = '.php';
	private static Engine $engine;

	/**
	 * View constructor.
	 */
	public function __construct()
	{
		static::$engine = new Engine();
	}


    /**
     * @return Engine
     */
	public static function engine(): Engine
	{
        return static::$engine ?? new Engine();
    }


    /**
     * Возвращает данные просмотра.
     *
     * @return array
     */
    final public function data(): array
    {
        return $this->data;
    }


	/**
	 * @return string
     * @throws Exception
	 */
    final public function respond(): string
    {
		# Получить экземпляр действия модуля.
		$instance = Router::module()->instance();

		# Если у нас нет макета, то напрямую выводим представление.
		if (isset($instance->layout) && $instance->layout === '') {
            echo $this->render();
        } else {
		    Layout::view($this);
        }
        return '';
	}


    /**
     * @return string
     */
    #[Pure] public static function path(): string
    {
        return static::engine()->ViewDirectory();
    }

	/**
	 * @return string
	 * @throws Exception
	 */
	final public function render(): string
	{
		# Получение путь для просмотров.
		$path = static::path() . $this->file . self::TEMPLATE_EXTENSION;

		# Возвращение View.
		return self::load($path, $this->data);
	}

	public static function make(string $file, array $data = []): View
	{
		# Экземпляр класса.
		$name           = static::class;
		$class          = new $name;
		$class->file    = $file;
		$class->data    = $data;

		# Возвращение нового объекта.
		return $class;
	}

    public static function load(string $path, array $data = []): string
    {
        # Проверка, что данные доступны в виде переменных.
        extract($data);
        # Проверка, существует ли файл.
        if (is_file($path)) {

            # Загрузите компонент в переменную.
            ob_start();
            # Подключение файла
            include $path;

            # Вернуть загруженный компонент.
            return ob_get_clean();
        }

        throw new RuntimeException(
            sprintf('View файл %s не найден!', $path)
        );
    }
}
