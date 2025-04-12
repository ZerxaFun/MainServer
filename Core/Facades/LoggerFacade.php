<?php
declare(strict_types=1);

namespace Core\Facades;

use Core\Logger\Logger;
use Core\Logger\LoggerFactory;
use Core\Logger\LoggerInterface;

final class LoggerFacade
{
    private static ?LoggerInterface $instance = null;

    private static function instance(): LoggerInterface
    {
        if (self::$instance === null) {
            self::$instance = new Logger(LoggerFactory::make());
        }

        return self::$instance;
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::instance()->emergency($message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::instance()->alert($message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::instance()->critical($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::instance()->error($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::instance()->warning($message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::instance()->notice($message, $context);
    }

    public static function info(string $message, array $context = []): void
    {

        self::instance()->info($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::instance()->debug($message, $context);
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        self::instance()->log($level, $message, $context);
    }
}
