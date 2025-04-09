<?php

namespace Modules\API\Controller;

use Controller;
use Core\Services\Routing\Attributes\HttpGet;
use Illuminate\Database\QueryException;
use Illuminate\Database\Capsule\Manager as DB;
use PDOException;

class APIController extends Controller
{
    #[HttpGet('api/v1/connect')]
    public function connect(): void
    {
        try {
            DB::connection()->getPdo();
            self::api(status: 'success');
        } catch (QueryException | PDOException $e) {
            self::api(statusCode: 500, status: 'error');
        }
    }
}

