<?php

namespace Core\Services\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Core\Services\Http\Request;
use Illuminate\Database\Capsule\Manager as Capsule;
use JetBrains\PhpStorm\NoReturn;
use Random\RandomException;

class JWTGuard implements GuardInterface
{
    protected ?object $user = null;
    protected ?string $token = null;
    protected ?object $payload = null;

    public function token(): ?string { return $this->token; }
    public function payload(): ?object { return $this->payload; }
    public function getJti(): ?string { return $this->payload->jti ?? null; }
    public function revokeToken(string $jti): void {
        Capsule::table('UsersTokens')->where('jti', $jti)->update(['revoked' => 1]);
    }

    /**
     * @throws RandomException
     */
    public function authorize(object $user): void
    {
        $jti = bin2hex(random_bytes(16));
        $exp = time() + (60 * 60); // 1 час

        $payload = [
            'jti' => $jti,
            'exp' => $exp
        ];

        $jwt = JWT::encode($payload, $_ENV['s_key'], 'HS256');

        Capsule::table('UsersTokens')->insert([
            'id'         => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'user_id'    => $user->id,
            'jti'        => $jti,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', $exp),
            'revoked'    => 0,
        ]);

        $this->user = $user;
        $this->payload = (object) $payload;
        $this->token = $jwt;

        $_SESSION['jwt'] = $jwt;
    }
    public function user(?Request $request = null): ?object
    {
        // Если реквест не передали — создаём сами
        if (!$request) {
            $request = new Request();
        }

        $token = $request->bearerToken();
        if (!$token) return null;

        try {
            $decoded = JWT::decode($token, new Key($_ENV['s_key'], 'HS256'));
        } catch (\Throwable) {
            return null;
        }

        $jti = $decoded->jti ?? null;
        if (!$jti) return null;

        $tokenRecord = Capsule::table('UsersTokens')
            ->where('jti', $jti)
            ->where('revoked', 0)
            ->where('expires_at', '>=', date('Y-m-d H:i:s'))
            ->first();

        if (!$tokenRecord) return null;

        if ($tokenRecord->ip_address !== ($_SERVER['REMOTE_ADDR'] ?? '')
            || $tokenRecord->user_agent !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            return null;
        }

        $user = Capsule::table('UsersAccount')->where('id', $tokenRecord->user_id)->first();

        if (!$user || $user->banned || !$user->is_active) return null;

        $user->permissions = Capsule::table('UsersPermissions')
            ->join('Permissions', 'UsersPermissions.permission_id', '=', 'Permissions.id')
            ->where('UsersPermissions.user_id', $user->id)
            ->where('UsersPermissions.is_active', 1)
            ->pluck('Permissions.name')
            ->toArray();

        return $user;

    }

    public function check(Request $request): bool
    {
        return $this->user($request) !== null;
    }
}

