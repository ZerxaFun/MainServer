<?php

namespace Core\Services\Auth;

use Core\Services\Auth\Contracts\AuthServiceInterface;
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
        $exists = Capsule::table('UsersAccount')
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

        Capsule::table('UsersAccount')->insert($insert);

        $user = Capsule::table('UsersAccount')
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
        $email = $request->get('email');
        $password = $request->get('password');

        $user = Capsule::table('UsersAccount')
            ->where('email', $email)
            ->first();

        if (!$user || !password_verify($password, $user->password)) {
            APIControllers::setData(['message' => 'Неверные данные'], 401);
        }

        if (!$user->is_active || $user->banned) {
            APIControllers::setData(['message' => 'Пользователь заблокирован или отключён'], 403);
        }

        $cleanUser = (object)[
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
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


        if (!$decoded || !isset($decoded->user->id, $decoded->jti)) {
            APIControllers::setData(['message' => 'Недопустимый токен'], 401);
        }

        Capsule::table('UsersTokens')->where('jti', $decoded->jti)->update(['revoked' => 1]);

        $user = Capsule::table('UsersAccount')->where('id', $decoded->user->id)->first();

        Auth::authorize($user);

        return [
            'token' => Auth::JWT(),
            'user'  => $user,
        ];
    }

    public function me(): array
    {
        $user = Auth::user();

        if (!$user) {
            APIControllers::setData(['message' => 'Пользователь не найден'], 404);
        }

        return ['user' => $user];
    }
}
