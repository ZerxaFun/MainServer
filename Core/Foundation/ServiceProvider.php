<?php

namespace Core\Foundation;

abstract class ServiceProvider
{
    /**
     * Метод для регистрации сервисов в DI
     */
    abstract public function register(): void;

    /**
     * Метод вызывается после регистрации всех провайдеров
     */
    public function boot(): void
    {
        // Необязательный метод
    }
}
