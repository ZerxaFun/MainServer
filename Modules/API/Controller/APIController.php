<?php

namespace Modules\API\Controller;

use Controller;
use Core\Routing\Attributes\HttpMethod;


class APIController extends Controller
{

    #[HttpMethod(['get'], '/connect')]
    public function checkDatabase(): void
    {
        try {
            $connection = \Illuminate\Database\Capsule\Manager::connection();
            $connection->getPdo(); // Попытка подключиться к PDO

            self::api(['db' => 'ok']);
        } catch (\Throwable $e) {
            self::api(['db' => 'fail', 'error' => $e->getMessage()], 500, 'fail');
        }
    }
}

