<?php

namespace Modules\API\Controller;


use Controller;
use Core\Routing\Attributes\HttpGet;
use Core\Routing\Attributes\HttpMethod;
use Illuminate\Database\Capsule\Manager;
use View;

class ErrorController extends Controller
{
    #[HttpGet('/')]
    public function page404(): View
    {

        return View::make('home');
    }
}