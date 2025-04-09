<?php

namespace Modules\API\Controller;

use Controller;
use Core\Services\Auth\Attributes\Authorize;
use Core\Services\Localization\Lang;
use Core\Services\Modules\LanguageConfig;
use Core\Services\Routing\Attributes\HttpMethod;
use Core\Services\Auth\Auth;
use Core\Services\Routing\Router;
use Core\Services\Validation\Validator;
use Core\Services\Validation\Exceptions\ValidationException;
use Core\Services\Http\Input;
use Illuminate\Database\Capsule\Manager as Capsule;
use JetBrains\PhpStorm\NoReturn;
use Ramsey\Uuid\Uuid;
use Random\RandomException;

/**
 * Контроллер для обработки пользовательской авторизации, регистрации и токенов.
 */
class AccountController extends Controller
{
    /**
     * Регистрация нового пользователя.
     *
     * @return void
     */
    #[HttpMethod(['post'], '/register')]
    #[NoReturn]
    public function register(): void
    {
        $rules = [
            'name' => ['required' => true, 'type' => 'string'],
            'login' => ['required' => true, 'type' => 'string'],
            'email' => ['required' => true, 'type' => 'email'],
            'password' => ['required' => true, 'type' => 'string', 'min' => 6]
        ];

        $validator = new Validator($rules);

        // Валидация входящих данных
        try {
            $validator->validate(Input::json());
        } catch (ValidationException $e) {
            self::api(['errors' => $e->errors()], 422, 'error');
        }

        $email = Input::json('email');
        $login = Input::json('login');

        // Проверка существования email или логина
        $exists = Capsule::table('UsersAccount')->where('email', $email)->orWhere('login', $login)->exists();

        if ($exists) {
            self::api(['message' => 'Пользователь с таким email или логином уже существует'], 409, 'error');
        }

        $userId = Uuid::uuid4()->toString();

        // Создание пользователя
        Capsule::table('UsersAccount')->insert([
            'id' => $userId,
            'email' => $email,
            'login' => $login,
            'name' => Input::json('name'),
            'password' => password_hash(Input::json('password'), PASSWORD_BCRYPT),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Авторизация
        $user = Capsule::table('UsersAccount')->select(['id'])->where('id', $userId)->first();
        Auth::authorize($user);

        self::api([
            'token' => Auth::JWT(),
            'user' => $user
        ]);
    }

    /**
     * Аутентификация пользователя.
     *
     * @return void
     * @throws RandomException
     */
    #[HttpMethod(['post'], '/login')]
    #[NoReturn]
    public function login(): void
    {
        $rules = [
            'email' => ['required' => true, 'type' => 'email'],
            'password' => ['required' => true, 'type' => 'string']
        ];

        $validator = new Validator($rules);

        try {
            $validator->validate(Input::json());
        } catch (ValidationException $e) {
            self::api(['errors' => $e->errors()], 422, 'error');
        }

        $email = Input::json('email');
        $password = Input::json('password');

        $user = Capsule::table('UsersAccount')->where('email', $email)->first();

        // Проверка наличия пользователя и валидности пароля
        if (!$user || !password_verify($password, $user->password)) {
            self::api(['message' => 'Неверные данные'], 401, 'error');
        }

        // Проверка на бан/деактивацию
        if (!$user->is_active || $user->banned) {
            self::api(['message' => 'Пользователь заблокирован или отключён'], 403, 'error');
        }
        $cleanUser = (object)[
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        Auth::authorize($cleanUser);

        self::api([
            'token' => Auth::JWT(),
            'user' => $cleanUser
        ]);
    }

    /**
     * Завершение сессии пользователя, отзыв токена.
     *
     * @return void
     */
    #[HttpMethod(['post'], '/logout')]
    #[NoReturn]
    public function logout(): void
    {
        $jti = Auth::getJti();

        if ($jti) {
            Capsule::table('UsersTokens')->where('jti', $jti)->update(['revoked' => 1]);
        }

        self::api(['message' => 'Выход выполнен']);
    }

    /**
     * Обновление токена доступа.
     *
     * @return void
     * @throws RandomException
     */
    #[HttpMethod(['post'], '/refresh')]
    #[NoReturn]
    public function refresh(): void
    {
        $decoded = Auth::getDecodedPayload();

        if (!$decoded || !isset($decoded->user->id, $decoded->jti)) {
            self::api(['message' => 'Недопустимый токен'], 401, 'error');
        }

        // Отзываем старый токен
        Capsule::table('UsersTokens')->where('jti', $decoded->jti)->update(['revoked' => 1]);

        $user = Capsule::table('UsersAccount')->where('id', $decoded->user->id)->first();

        Auth::authorize($user);

        self::api([
            'token' => Auth::JWT(),
            'user' => $user
        ]);
    }

    /**
     * Получение информации о текущем авторизованном пользователе.
     *
     * @return void
     */
    #[Authorize(guard: 'jwt', permission: ['admin', 'manager'])]
    #[HttpMethod(['get'], '/me')]
    #[NoReturn]
    public function me(): void
    {
        $user = Auth::user();

        if (!$user) {
            self::api(['message' => 'Пользователь не найден'], 404, 'error');
        }

        self::api(['user' => $user]);
    }
}