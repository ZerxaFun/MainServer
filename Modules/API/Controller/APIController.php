<?php

namespace Modules\API\Controller;


use Controller;
use Core\Routing\Attributes\HttpMethod;
use Core\Routing\Attributes\HttpGet;
use Core\Services\Auth\AccessGate;
use Core\Services\Auth\Attributes\Authorize;
use Core\Services\Auth\AuthManager;
use Illuminate\Database\Capsule\Manager;
use View;

class APIController extends Controller
{
    /**
     * Проверка соединения с базой данных
     *
     * @return void
     * @throws JsonException
     */
    #[HttpMethod(['get'], '/api/v1/connect')]
    public function checkDatabase(): void
    {
        try {
            $connection = Manager::connection();
            $connection->getPdo(); // Попытка подключиться к PDO

            self::api(['db' => 'ok']);
        } catch (\Throwable $e) {
            self::api(['db' => 'fail', 'error' => $e->getMessage()], 500, 'fail');
        }
    }

    #[HttpGet('/')]
    public function home(): View
    {

        return View::make('home');
    }
}