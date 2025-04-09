<?php

namespace Core\Services\Auth;

use Core\Services\Auth\GuardInterface;
use Core\Services\Auth\AuthManager;

class Auth
{
    protected static ?GuardInterface $guardInstance = null;

    /**
     * Получить текущий guard (по умолчанию "jwt").
     */
    public static function guard(string $name = 'jwt'): GuardInterface
    {
        if (!self::$guardInstance) {
            self::$guardInstance = AuthManager::getGuard($name);
        }

        return self::$guardInstance;
    }

    /**
     * Авторизовать пользователя.
     */
    public static function authorize(object $user): void
    {
        self::guard()->authorize($user);
    }

    /**
     * Получить текущего пользователя.
     */
    public static function user(): ?object
    {
        return self::guard()->user();
    }

    /**
     * Получить ID текущего пользователя.
     */
    public static function id(): ?string
    {
        return self::user()?->id ?? null;
    }

    /**
     * Получить текущий JWT токен.
     */
    public static function JWT(): ?string
    {
        return self::guard()->token();
    }

    /**
     * Получить payload токена.
     */
    public static function getDecodedPayload(): ?object
    {
        return self::guard()->payload();
    }

    /**
     * Получить jti текущего токена.
     */
    public static function getJti(): ?string
    {
        return self::guard()->getJti();
    }

    /**
     * Отозвать токен.
     */
    public static function revoke(string $jti): void
    {
        self::guard()->revokeToken($jti);
    }

    /**
     * Проверка токена вручную.
     */
    public static function validate(string $token): bool
    {
        return self::guard()->validateToken($token);
    }
}
