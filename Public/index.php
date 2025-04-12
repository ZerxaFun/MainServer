<?php

declare(strict_types=1);
set_time_limit(-1);

/**
 *=====================================================
 * Majestic Next Engine - by Zerxa Fun                =
 *-----------------------------------------------------
 * @url: http://majestic-studio.com/                  =
 *-----------------------------------------------------
 * @copyright: 2021 Majestic Studio and ZerxaFun      =
 *=====================================================
 * @license GPL version 3                             =
 *=====================================================
 * index.php - исполняемый файл и точка входа         =
 * в систему.                                         =
 * Подключение composer и констант фреймворка         =
 *=====================================================
 */



use Core\Bootstrap\Bootstrap;


require '../vendor/autoload.php';




try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Max-Age: 86400");
        exit(0);
    }

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");



    Bootstrap::run(dirname(__DIR__));
} catch (Exception $exception) {
    dd($exception);
}
