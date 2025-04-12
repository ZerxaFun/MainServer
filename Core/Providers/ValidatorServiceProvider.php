<?php

namespace Core\Providers;

use Core\Foundation\ServiceProvider;
use Core\Services\Validation\Validator;
use DI;

class ValidatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Простой placeholder. Можно сделать фабрику позже
        DI::instance()->set('validator', fn() => new Validator([], []));
    }
}
