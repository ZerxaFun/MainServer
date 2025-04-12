<?php

namespace Core\Services\Session;

use Core\Services\Config\Config;
use Core\Services\Session\Drivers\NativeSession;

class SessionManager
{
    protected static ?SessionInterface $driver = null;

    public static function driver(): SessionInterface
    {
        if (static::$driver) return static::$driver;

        $driver = Config::item('driver', 'session');

        return static::$driver = match ($driver) {
            'native' => new NativeSession(),
            default  => new NativeSession(),
        };
    }

    public static function start(): void
    {
        static::driver()->start();
    }
}
