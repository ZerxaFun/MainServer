<?php

namespace Modules\API\Controller;

use Core\Routing\Attributes\HttpMethod;
use Core\Routing\Repository;
use Core\Services\Auth\AuthManager;
use Core\Services\Http\Input;
use Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use Modules\API\Model\UserAccount;


use Core\Services\Attributes\Validate;
use Core\Services\Http\ValidatedRequest;

use View;
class UserAccountController extends Controller
{
    /**
     * Аунтефикация и получение JWT
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['post'], '/api/v1/auth')]
    #[Validate([
        'username'    => ['required' => true, 'min' => 3],
        'password' => ['type' => 'string', 'required' => true, 'min' => 3, 'max' => 15],
    ])]
    public function login(ValidatedRequest $request): void
    {
        self::api($this->user()->login($request));
    }

    /**
     * Список пользователей
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function userList(): void
    {
        try {
            // Проверяем заголовок Authorization
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                // Модель
                $userModel = new UserAccount();

                // Делаем JOIN самой таблицы, чтобы вместо `ByUser` (ID) показать имя создателя
                $userList = $userModel->table() // или table('UserAccount') без "as u"
                ->leftJoin('UserAccount as c', 'UserAccount.ByUser', '=', 'c.UserID')
                    ->select([
                        'UserAccount.Username',
                        'UserAccount.UserID',
                        'UserAccount.CreatedAt',
                        'UserAccount.Role',
                        'c.Username as CreatedByUser'
                    ])
                    ->where('UserAccount.IsActive', '=', 1)
                    ->get();


                self::setData(result: ['users' => $userList], status: 'success');
            } else {
                self::setData(
                    result: ['users' => [], 'error' => 'auth failed'],
                    statusCode: 500,
                    status: 'error'
                );
            }
        } catch (\Exception $e) {
            self::setData(
                result: ['users' => [], 'error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }

    /**
     * Обновление данных пользователя (логин, роль, пароль)
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function userUpdate(): void
    {
        try {
            // Проверяем токен авторизации
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                self::setData(result: ['error' => 'Unauthorized'], statusCode: 401, status: 'error');
                return;
            }

            $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
            $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            $userModel = new UserAccount();

            // Получаем данные из запроса
            $userId = Input::json('UserID');
            $newUsername = Input::json('Username');
            $newRole = Input::json('Role');
            $newPassword = Input::json('Password'); // Может быть пустым

            // Проверяем, существует ли пользователь
            $user = $userModel->table()->where('UserID', '=', $userId)->first();

            if (!$user) {
                self::setData(result: ['error' => 'User not found'], statusCode: 404, status: 'error');
            }

            // Обновляем данные
            $updateData = [
                'Username' => $newUsername,
                'Role' => $newRole
            ];

            // Если передан новый пароль, хэшируем и обновляем
            if (!empty($newPassword)) {
                $updateData['Password'] = $newPassword;
            }

            // Обновляем пользователя в БД
            $userModel->table()->where('UserID', '=', $userId)->update($updateData);

            // Если обновили текущего пользователя, передаём новый токен
            if ($decode->UserID == $userId) {
                $payload = [
                    "UserID" => $user->UserID,
                    "Role" => $newRole,
                    "Username" => $newUsername,
                    "exp" => time() + (7 * 24 * 60 * 60),
                ];

                $newJwt = JWT::encode($payload, $_ENV['s_key'], 'HS256');

                self::setData(
                    result: ['message' => 'User updated successfully', 'token' => $newJwt],
                    status: 'success'
                );
            } else {
                self::setData(result: ['message' => 'User updated successfully'], status: 'success');
            }

        } catch (\Exception $e) {
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }



    public function userDelete(): void
    {
        try {
            // Авторизация
            $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
            $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            $username = Input::json('username');
            if (!$username) {
                self::setData(result: ['message' => 'Username is required'], status: 'error');
            }

            $userModel = new UserAccount();

            // Пометить deleted_at = now()
            $userModel->table()
                ->where('Username', '=', $username)
                ->update([
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'IsActive'   => 0, // Если хотите, можно и IsActive=0
                ]);

            self::setData(result: ['message' => 'User deleted successfully'], status: 'success');
        } catch (\Exception $e) {
            self::setData(
                result: ['message' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }


    /**
     * Создание нового пользователя
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function userCreated(): void
    {
        try {
            // Проверяем наличие заголовка Authorization
            if (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                self::setData(
                    result: ['message' => 'auth failed'],
                    statusCode: 401,
                    status: 'error'
                );
            }

            // Извлекаем токен из заголовка
            $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
            // Декодируем, чтобы получить данные пользователя, создавшего новую запись
            $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // Извлекаем данные нового пользователя из POST/JSON
            $username = Input::json('username');
            $password = Input::json('password');
            $role = Input::json('role'); // "0" или "1"

            // Простейшая проверка
            if (empty($username) || empty($password)) {
                self::setData(
                    result: ['message' => 'Username or password cannot be empty'],
                    statusCode: 400,
                    status: 'error'
                );
            }

            // Модель
            $userModel = new UserAccount();

            if ($userModel->table()->where('Username', '=', $username)->first()) {
                self::setData(
                    result: ['message' => 'Username already exists'],
                    statusCode: 409,
                    status: 'error'
                );
            }

            // Вставляем в таблицу
            $userModel->table()->insert([
                'Username' => $username,
                'Password' => $password,           // !!! РЕКОМЕНДУЕТСЯ ХЭШИРОВАТЬ !!!
                'IsActive' => 1,
                'CreatedAt' => date('Y-m-d H:i:s'),
                'Role' => $role,               // "0" = менеджер, "1" = админ
                'ByUser' => $decode->UserID,     // тот, кто создал
            ]);

            // Возвращаем успех
            self::setData(
                result: ['message' => 'User created successfully'],
                status: 'success'
            );

        } catch (\Exception $e) {
            // Обработка ошибок
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }


    /**
     * Проверка профиля
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/api/v1/profile')]
    public function profile(ValidatedRequest $request): void
    {

        self::api(
            $this->user()->me($request)
        );
    }
}