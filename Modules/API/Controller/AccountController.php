<?php

namespace Modules\API\Controller;

use Controller;
use Core\Routing\Attributes\HttpGet;
use Core\Routing\Repository;
use Core\Services\Attributes\Validate;
use Core\Services\Http\Request;
use Core\Services\Http\ValidatedRequest;
use Core\Routing\Attributes\HttpMethod;
use Core\Services\Modules\Language;
use Core\Services\Modules\LanguageConfig;
use Core\Services\Session\Facades\Session;
use JetBrains\PhpStorm\NoReturn;
use View;

class AccountController extends Controller
{
    #[NoReturn] #[HttpMethod(['post'], '/register')]
    #[Validate([
        'name'     => ['required' => true],
        'login'    => ['required' => true],
        'email'    => ['required' => true, 'email' => true],
        'password' => ['required' => true, 'min' => 6],
    ])]
    public function register(ValidatedRequest $request): void
    {
        self::api($this->user()->register($request, ['name' => $request->get('name'), 'login' => $request->get('login')]));
    }

    #[NoReturn] #[HttpMethod(['post'], '/login')]
    #[Validate([
        'email'    => ['required' => true, 'email' => true],
        'password' => ['required' => true, 'min' => 2],
    ])]
    public function login(ValidatedRequest $request): void
    {
        self::api($this->user()->login($request));
    }

    #[NoReturn] #[HttpMethod(['post'], '/logout')]
    public function logout(): void
    {
        $this->user()->logout();
        self::api(['message' => 'Выход выполнен']);
    }

    #[NoReturn] #[HttpMethod(['post'], '/refresh')]
    public function refresh(): void
    {
        self::api(
            $this->user()->refresh()
        );
    }

    #[HttpMethod(['get'], '/me')]
    public function me(): void
    {
        self::apiAdd('user', 'god');
        self::apiAdd('token', 'xyz');
        self::apiFlush();
    }
    #[HttpGet('/')]
    public function home(): View
    {

        return View::make('home');
    }
    #[HttpMethod(['get'], '/home')]
    public function homeD(): View
    {
        return View::make('home');
    }
}
