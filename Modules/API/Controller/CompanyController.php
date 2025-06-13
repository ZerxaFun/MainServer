<?php

namespace Modules\API\Controller;

use Carbon\Carbon;
use Core\Routing\Attributes\HttpMethod;
use Core\Services\Auth\Attributes\Authorize;
use Core\Services\Http\Input;
use Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use Modules\API\Model\CompanyModel;
use Modules\API\Model\PaymentAccountModel;

/**
 * CompanyController - контроллер для управления данными компаний.
 */
class CompanyController extends Controller
{
    /**
     * Проверка соединения с базой данных
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn]
    #[HttpMethod(['get'], '/s')]
    public function getCompanyList(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];

                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                $companyModel = new CompanyModel();

                // Выполняем запрос с объединением и подсчетом количества счетов для каждой компании
                $company = $companyModel->table()
                    ->leftJoin('Accounts', function ($join) {
                        $join->on('Company.OwnerID', '=', 'Accounts.OwnerID')
                            ->whereNull('Accounts.deleted_at'); // Условие для учета только активных аккаунтов
                    })
                    ->select([
                        'Company.OwnerID',
                        'Company.OwnerName',
                        'Company.OfficialName',
                        'Company.TaxID',
                        'Company.TaxPayer',
                        'Company.Status'
                    ])
                    ->selectRaw('COUNT(Accounts.OwnerID) as accountCount') // Считаем только активные аккаунты
                    ->whereNull('Company.deleted_at')
                    ->groupBy(
                        'Company.OwnerID',
                        'Company.OwnerName',
                        'Company.OfficialName',
                        'Company.TaxID',
                        'Company.TaxPayer',
                        'Company.Status'
                    )
                    ->get();


                self::setData(result: ['company' => $company], status: 'success');

            } else {
                self::setData(result: ['company' => [], 'error' => 'auth failed'], statusCode: 500, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['company' => [], 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }

    /**
     * Краткий список компаний (ID и названия) для создания платёжного аккаунта.
     *
     * Метод: POST
     * URL: /api/v1/company/small-list
     * Требуется авторизация (JWT, роль admin).
     *
     * Ответ:
     * - `companyList`: массив компаний с полями OwnerID, OwnerName, OfficialName
     *
     * @return void
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/company/small-list')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    public static function getCompanySmallList(): void
    {
        try {
            // Получаем компании с названиями, ID и привязкой к аккаунтам
            $companies = (new CompanyModel())
                ->table()
                ->leftJoin('Accounts', 'Company.OwnerID', '=', 'Accounts.OwnerID')
                ->select([
                    'Company.OwnerID',
                    'Company.OwnerName',
                    'Company.OfficialName',
                ])
                ->whereNull('Company.deleted_at')
                ->groupBy(
                    'Company.OwnerID',
                    'Company.OwnerName',
                    'Company.OfficialName',
                )
                ->get();

            self::api([
                'companyList' => $companies,
            ]);
        } catch (\Throwable $e) {
            self::api([
                'companyList' => [],
                'error' => $e->getMessage(),
            ], 500, 'error');
        }
    }


    /**
     * Метод создания новой компании с горячим добавлением счетов
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function createCompany(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];

                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                $data = json_decode(file_get_contents('php://input'), true);

                $companyModel = new CompanyModel();
                $paymentAccountModel = new PaymentAccountModel();

                // Создание новой записи компании
                $newCompany = $companyModel->create([
                    'OwnerName' => $data['OwnerName'] ?? null,
                    'OfficialName' => $data['OfficialName'] ?? null,
                    'TaxID' => $data['TaxID'] ?? null,
                    'TaxPayer' => isset($data['Taxpayer']) && $data['Taxpayer'],
                    'Status' => isset($data['Status']) && $data['Status'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'deleted_at' => null,
                    'deleted_user' => null,
                ]);

                // Поиск компании по полям, которые мы только что использовали для вставки
                $foundCompany = $companyModel->where([
                    'OwnerName' => $data['OwnerName'] ?? null,
                    'OfficialName' => $data['OfficialName'] ?? null,
                    'TaxID' => $data['TaxID'] ?? null,
                    'created_at' => $newCompany->created_at, // Время вставки
                ])->first();

                // Теперь можно получить OwnerID
                $ownerId = $foundCompany->OwnerID;


                // Проверяем, есть ли платежные аккаунты для добавления
                if (!empty($data['paymentAccounts'])) {
                    foreach ($data['paymentAccounts'] as $account) {
                        $paymentAccountModel->create([
                            'Description' => $account['Description'] ?? null,
                            'Currency' => $account['Currency'] ?? null,
                            'Bank' => $account['Bank'] ?? null,
                            'OwnerID' => $ownerId, // Используем ID компании
                            'Status' => isset($account['Status']) ? (bool) $account['Status'] : true,
                        ]);
                    }
                }

                self::setData(result: ['company' => $newCompany], status: 'success');
            } else {
                self::setData(result: ['company' => [], 'error' => 'auth failed'], statusCode: 500, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['company' => [], 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }

    /**
     * Метод получения детальной информации о компании
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function detailsCompany(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];

                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                $data = json_decode(file_get_contents('php://input'), true);

                $companyModel = new CompanyModel();
                $paymentAccountModel = new PaymentAccountModel();

                $selectCompany = $companyModel->table()
                    ->select(['OwnerName', 'OfficialName', 'TaxID', 'TaxPayer', 'Status'])
                    ->where('OwnerID', $data['company'])
                    ->first();

                $selectPaymentAccount = $paymentAccountModel->table()
                    ->select(['Description', 'Currency', 'Bank', 'Status', 'AccountID'])
                    ->whereNull('deleted_at')
                    ->where('OwnerID', $data['company'])
                    ->get();


                self::setData(result: [
                    'OwnerID' => $data['company'],
                    'company' => $selectCompany,
                    'paymentAccounts' => $selectPaymentAccount,
                ], status: 'success');
            } else {
                self::setData(result: [[
                    'OwnerID' => [],
                    'company' => [],
                    'paymentAccounts' => [],
                ], 'error' => 'auth failed'], statusCode: 500, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: [
                'OwnerID' => [],
                'company' => [],
                'paymentAccounts' => [], 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }


    /**
     * Метод логического удаления компании и её счетов
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function deletedCompany(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                // Получаем данные из тела запроса
                $data = Input::json();

                // Получаем модели компании и платежного аккаунта
                $companyModel = new CompanyModel();
                $paymentAccountModel = new PaymentAccountModel();

                // Устанавливаем deleted_at для компании
                $companyModel->table()
                    ->where('OwnerID', $data['ownerID'])
                    ->update(['deleted_at' => Carbon::now()]);

                // Устанавливаем deleted_at для всех счетов компании
                $paymentAccountModel->table()
                    ->where('OwnerID', $data['ownerID'])
                    ->update(['deleted_at' => Carbon::now()]);

                self::setData(status: 'success');
            } else {
                self::setData(result: ['error' => 'auth failed'], statusCode: 500, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }
}