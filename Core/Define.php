<?php

declare(strict_types=1);

/**
 *=====================================================
 * Majestic Engine                                    =
 *=====================================================
 * @package Core\Bootstrap                            =
 *-----------------------------------------------------
 * @url http://majestic-studio.com/                   =
 *-----------------------------------------------------
 * @copyright 2021 Majestic Studio                    =
 *=====================================================
 * @author ZerxaFun aKa Zerxa                         =
 *=====================================================
 * @license GPL version 3                             =
 *=====================================================
 *                                                    =
 *                                                    =
 *=====================================================
 */

namespace Core;

/**
 * Различный набор констант проекта
 */
class Define
{
    public const NAME_HEAD = 'Majestic Next Engine';
    public const SERVE = 'Majestic modded PHP web server';
    public const VERSION = 0.2;
    /**
     * Тип проекта, в разработке, либо публичный проект
     *
     * @var bool $_ENV['developer']
     */
    public static bool $developer = false;

    /**
     * @return bool
     */
    public static function isDeveloper(): bool
    {
        self::setDeveloper();

        return self::$developer;
    }

    /**
     * @return void
     */
    private static function setDeveloper(): void
    {

        self::$developer = (bool) $_ENV['developer'];
    }
}
