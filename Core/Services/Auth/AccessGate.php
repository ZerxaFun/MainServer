<?php

namespace Core\Services\Auth;

class AccessGate
{

    /**
     * Проверяет, имеет ли пользователь указанное разрешение.
     *
     * @param object $user Объект пользователя
     * @param string|array $permission Разрешение или массив разрешений
     * @return bool
     */
    public static function allows(object $user, string|array $permission): bool
    {

        if (!isset($user->permissions)) return false;

        $userPerms = is_array($user->permissions)
            ? $user->permissions
            : explode(',', (string)$user->permissions);

        foreach ((array)$permission as $perm) {
            if (in_array($perm, $userPerms, true)) {
                return true;
            }
        }

        return false;
    }



}
