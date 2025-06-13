<?php
/**
 *=====================================================
 * Majestic Engine - by Zerxa Fun (Majestic Studio)   =
 *-----------------------------------------------------
 * @url: http://majestic-studio.ru/                   -
 *-----------------------------------------------------
 * @copyright: 2020 Majestic Studio and ZerxaFun      -
 *=====================================================
 *                                                    =
 *                                                    =
 *                                                    =
 *=====================================================
 */


namespace Core\Services\Database;


use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;

/**
 * Class Database
 */
class Database
{
    public static function initialize(): void
    {
        $deploy = $_ENV['db_host'] ;

        // Определяем нужные переменные окружения
        $prefix = $deploy . '_';

        $dbConfig = [
            'driver'    => $_ENV[$prefix . 'db_driver'],
            'host'      => $_ENV[$prefix . 'db_host'],
            'database'  => $_ENV[$prefix . 'db_name'],
            'username'  => $_ENV[$prefix . 'db_username'],
            'password'  => $_ENV[$prefix . 'db_password'],
            'charset'   => $_ENV[$prefix . 'db_charset'],
            'collation' => $_ENV[$prefix . 'db_collation'],
        ];

        $capsule = new Capsule;
        $capsule->addConnection(array_merge($dbConfig, [
            'prefix' => '',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
            ],
            'trust_server_certificate' => true,
        ]), 'default');

        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
