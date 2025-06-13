<?php

namespace Core\Services\Auth;

use Core\Services\Auth\Contracts\AuthServiceInterface;
use Core\Services\Config\Config;
use Core\Services\Http\ValidatedRequest;
use Core\Routing\APIControllers;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;
use Illuminate\Database\Capsule\Manager as Capsule;

class AuthService implements AuthServiceInterface
{
    public function register(ValidatedRequest $request, array $extra = []): array
    {
        $data = $request->validated();

        // Проверка дубликатов по email / login
        $exists = Capsule::table('UserAccount')
            ->where('email', $data['email'])
            ->orWhere('login', $data['login'])
            ->exists();

        if ($exists) {
            APIControllers::setData(['message' => 'Пользователь с таким email или логином уже существует'], 409);
        }

        $userId = Uuid::uuid4()->toString();

        // Базовая запись
        $insert = [
            'id'         => $userId,
            'email'      => $data['email'],
            'password'   => password_hash($data['password'], PASSWORD_BCRYPT),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        // Дополнительные поля
        foreach ($extra as $key => $value) {
            $insert[$key] = $value;
        }

        Capsule::table('UserAccount')->insert($insert);

        $user = Capsule::table('UserAccount')
            ->select(['id', 'email'])
            ->where('id', $userId)
            ->first();

        Auth::authorize($user);

        return [
            'token' => Auth::JWT(),
            'user'  => $user,
        ];
    }


    public function login(ValidatedRequest $request): array
    {
        if (!empty($request->errors())) {
            APIControllers::setData([
                'message' => 'Ошибка валидации',
                'errors'  => $request->errors(),
            ], 422, 'fail');
        }

        $username = $request->get('username');
        $password = $request->get('password');

        if (!Config::has('userTable')) {
            throw new \RuntimeException('Конфигурационный ключ "userTable" не задан.');
        }

        $user = Capsule::table(Config::item('userTable'))
            ->where('username', $username)
            ->first();

        $hasPassword = $_ENV['has_password'] === 'true';

        if (
            !$user ||
            ($hasPassword && !password_verify($password, $user->Password))
        ) {
            APIControllers::setData(['message' => 'Неверные данные'], 401);
        }


        if (!$user->IsActive) {
            APIControllers::setData(['message' => 'Пользователь заблокирован или отключён'], 403);
        }

        $cleanUser = (object)[
            'UserID'    => $user->UserID,
            'username'  => $user->Username
        ];

        Auth::authorize($cleanUser);

        return [
            'token' => Auth::JWT(),
            'user'  => $cleanUser,
        ];
    }


    public function logout(): void
    {
        $jti = Auth::getJti();

        if ($jti) {
            Capsule::table('UsersTokens')->where('jti', $jti)->update(['revoked' => 1]);
        }
    }

    public function refresh(): array
    {
        $decoded = Auth::getDecodedPayload();


        if (!$decoded || !isset($decoded->user->UserID, $decoded->jti)) {
            APIControllers::setData(['message' => 'Недопустимый токен'], 401);
        }

        Capsule::table('UsersTokens')->where('jti', $decoded->jti)->update(['revoked' => 1]);

        $user = Capsule::table('UserAccount')->where('UserID', $decoded->user->UserID)->first();

        if (!$user) {
            APIControllers::setData(['message' => 'Пользователь не найден'], 404);
        }

        Auth::authorize($user);

        return [
            'token' => Auth::JWT(),
            'user'  => $user,
        ];
    }

    public function me(ValidatedRequest $request): array
    {
        $user = Auth::user($request);

        if (!$user) {
            APIControllers::setData(['message' => 'Пользователь не найден'], 404);
        }

        return ['user' => $user];
    }
}
