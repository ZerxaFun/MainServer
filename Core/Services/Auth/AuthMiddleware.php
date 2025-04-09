<?php

namespace Core\Services\Auth;

use Closure;
use Core\Services\Http\Request;
use Core\Services\Routing\APIControllers;
use Core\Services\Auth\Attributes\Authorize;
use Exception;
use ReflectionMethod;

class AuthMiddleware
{
    public function __construct(protected ReflectionMethod $reflection) {}

    /**
     * @throws Exception
     */
    public function handle(Request $request, Closure $next)
    {
        $attributes = $this->reflection->getAttributes(Authorize::class);

        foreach ($attributes as $attr) {
            $attribute = $attr->newInstance();

            $guard = $attribute->guard ?? 'jwt';
            $perm  = $attribute->permission ?? null;

            $guardInstance = AuthManager::guard($guard);
            $user = $guardInstance->user($request);

            if (!$user) {
                self::fail('unauthorized');
            }

            if ($perm && !AccessGate::allows($user, $perm)) {
                self::fail('no access', 403);
            }

            Auth::authorize($user);
        }

        return $next($request);
    }

    private static function fail(string $message, int $code = 401): never
    {
        APIControllers::setData(['message' => "$message"], $code, 'error');
    }
}
