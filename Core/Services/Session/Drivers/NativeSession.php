<?php

namespace Core\Services\Session\Drivers;

use Core\Services\Config\Config;
use Core\Services\Http\Request;
use Core\Services\Session\SessionInterface;

class NativeSession implements SessionInterface
{
    protected bool $started = false;

    public function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $name = Config::item('name', 'session') ?? 'majestic_session';
            $lifetime = Config::item('lifetime', 'session') ?? 7200;

            ini_set('session.gc_maxlifetime', $lifetime);

            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'httponly' => true,
                'secure' => Request::https(),
                'samesite' => 'Lax'
            ]);

            session_name($name);
            session_start();
        }

        $this->started = true;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flush(): void
    {
        $_SESSION = [];
    }

    public function all(): array
    {
        return $_SESSION;
    }

    public function id(): string
    {
        return session_id();
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}

