<?php

namespace Core\Providers;

use Core\Foundation\ServiceProvider;
use Core\Services\Auth\AuthService;
use Core\Services\Auth\Contracts\AuthServiceInterface;
use DI;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        DI::instance()->set(AuthServiceInterface::class, fn() => new AuthService());
    }
}
