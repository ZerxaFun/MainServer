<?php

namespace Modules\API\Controller;

use Core\Routing\Attributes\HttpMethod;
use Core\Services\Attributes\Validate;
use Core\Services\Auth\Attributes\Authorize;
use Core\Services\Http\Input;
use Controller;
use Core\Services\Http\ValidatedRequest;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Database\Capsule\Manager as DB;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use Modules\API\Model\FinTransactionsModel;
use Modules\API\Model\MasterProductsModel;
use Modules\API\Model\MastersModel;
use Modules\API\Model\PaymentAccountModel;
use Modules\API\Model\StockTransactionsModel;
use Ramsey\Uuid\Uuid;

/**
 * OrderController - контроллер для управления заказами и их версиями.
 */
class MastersController extends Controller
{
    /**
     * Получение списка мастеров для заказа
     *
     * @return void
     * @throws JsonException
     */
    public static function MastersList(): void
    {
        try {
            // 1) Проверяем авторизацию
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new \Exception('Authorization failed');
            }

            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $jwt = explode(" ", $authHeader)[1] ?? null;
            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // 2) Считываем входные данные
            $data = Input::json();
            $itemUUID         = $data['itemUUID']         ?? null;
            $selectedServiceId= $data['selectedServiceId'] ?? null;
            // Если нужно, считываем язык. По умолчанию ru:
            $language         = $data['language']         ?? 'ru';

            if (!$itemUUID || !$selectedServiceId) {
                throw new \Exception('itemUUID and selectedServiceId required');
            }

            // 3) Собираем список мастеров + услуги
            $masters = new MastersModel();
            // Подзапрос FOR JSON PATH с LEFT JOIN на ProductTranslations
            $activeMasters = $masters->table()
                ->leftJoin('UserAccount', 'Masters.by_user', '=', 'UserAccount.UserID')
                ->select(
                    'Masters.*',
                    'UserAccount.Username as by_username',
                    \Illuminate\Database\Capsule\Manager::raw("
                    (
                      SELECT
                        p.ProductID,
                        COALESCE(t.Name, 'Unknown') AS ProductName
                      FROM MasterProducts mp
                      JOIN Products p ON mp.ProductID = p.ProductID
                      LEFT JOIN ProductTranslations t 
                             ON t.ProductID = p.ProductID
                             AND t.LanguageCode = '{$language}'
                      WHERE mp.MasterID = Masters.MasterID
                      FOR JSON PATH
                    ) as services_json
                ")
                )
                ->where('Masters.deleted', '=', 0)
                ->get();

            // 4) Данные о «выбранных» мастерах по заданному itemUUID + selectedServiceId
            $stockTransactionModel = new StockTransactionsModel();
            $finTransactionModel   = new FinTransactionsModel();

            $masterSelectedTask = $stockTransactionModel->table()
                ->where('InOutID', '=', $itemUUID)
                ->where('ProductID', '=', $selectedServiceId)
                ->get();

            $paymentAccount = null;
            $selectedMasters = [];

            foreach ($masterSelectedTask as $master) {
                $masterGetId = $finTransactionModel->table()
                    ->where('TransactionID', '=', $master->StockID)
                    ->first();

                if ($masterGetId) {
                    $paymentAccount = $masterGetId->AccountID;
                    $selectedMasters[] = [
                        'masterId'   => $masterGetId->CorrespondentID,
                        'Amount'     => $masterGetId->Amount,
                        'created_at' => $masterGetId->FinTransactionDate,
                    ];
                }
            }

            // 5) Декодируем JSON услуг для каждого мастера
            foreach ($activeMasters as $master) {
                $json = $master->services_json;
                $master->services = $json ? json_decode($json, true) : [];
                unset($master->services_json);
            }

            // 6) Возвращаем результат
            self::setData(
                result: [
                    "masters"          => $activeMasters,
                    'selected_master'  => $selectedMasters,
                    'selected_account' => $paymentAccount,
                ],
                status: 'success'
            );

        } catch (\Exception $e) {
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }


    /**
     * Получение списка выбранных мастеров для заказа с ценой, банком и датой
     *
     * @return void
     * @throws JsonException
     */
    public static function MastersTaskList(): void
    {
        try {
            // 1) Проверяем авторизацию
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new \Exception('Authorization failed');
            }
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $jwt = explode(" ", $authHeader)[1] ?? null;
            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // 2) Считываем входные данные
            $data = Input::json();
            $itemUUID          = $data['itemUUID']          ?? null;
            $selectedServiceId = $data['selectedServiceId'] ?? null;
            $language          = $data['language']          ?? 'ru';

            if (!$itemUUID || !$selectedServiceId) {
                throw new \Exception('itemUUID and selectedServiceId required');
            }

            // 3) Получаем данные о выбранных мастерах для заданного itemUUID и selectedServiceId
            $stockTransactionModel = new StockTransactionsModel();
            $finTransactionModel   = new FinTransactionsModel();

            $masterSelectedTask = $stockTransactionModel->table()
                ->where('InOutID', '=', $itemUUID)
                ->where('ProductID', '=', $selectedServiceId)
                ->get();

            $selectedMasters = [];

            foreach ($masterSelectedTask as $master) {
                $masterGetId = $finTransactionModel->table()
                    ->where('TransactionID', '=', $master->StockID)
                    ->first();

                if ($masterGetId) {
                    // Получаем данные платежного аккаунта
                    $paymentAccountDetails = (new PaymentAccountModel())->table()
                        ->select(['AccountID', 'Bank', 'Currency'])
                        ->where('AccountID', '=', $masterGetId->AccountID)
                        ->first();

                    $paymentAccountInfo = null;
                    if ($paymentAccountDetails) {
                        $paymentAccountInfo = [
                            'AccountID'   => $paymentAccountDetails->AccountID,
                            'Currency'    => $paymentAccountDetails->Currency,
                            'Bank'        => $paymentAccountDetails->Bank,
                        ];
                    }

                    // Получаем данные мастера (его имя)
                    $masterDetails = (new MastersModel())->table()
                        ->select(['MasterID', 'name'])
                        ->where('MasterID', '=', $masterGetId->CorrespondentID)
                        ->first();

                    $masterName = $masterDetails ? $masterDetails->name : 'Unknown';

                    $selectedMasters[] = [
                        'masterId'       => $masterGetId->CorrespondentID,
                        'name'           => $masterName,
                        'Amount'         => $masterGetId->Amount,
                        'created_at'     => $masterGetId->FinTransactionDate,
                        'PaymentAccount' => $paymentAccountInfo,
                    ];
                }

            }

            // 4) Возвращаем только нужные данные
            self::setData(
                result: [
                    'selected_master' => $selectedMasters,
                ],
                status: 'success'
            );

        } catch (\Exception $e) {
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }


    /**
     * Получение полного списка мастеров.
     *
     * Метод доступен по маршруту: POST /api/v1/masters/all
     * Требуется авторизация по JWT с правами 'admin'.
     *
     * Возвращает список мастеров с полной информацией.
     *
     * @return void
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/masters/all')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    public static function MastersListAll(): void
    {
        try {
            // Инициализируем модель мастеров
            $mastersModel = new MastersModel();

            // Получаем все записи мастеров
            $masters = $mastersModel
                ->table()
                ->get();

            // Возвращаем успешный JSON-ответ
            self::api([
                'masters' => $masters,
            ]);
        } catch (\Throwable $e) {
            // Логируем и возвращаем общую ошибку сервера
            self::api([
                'error' => $e->getMessage(),
            ], 500, 'error');
        }
    }

    /**
     * Получение краткого списка всех мастеров (только ID и имя)
     *
     * Доступ разрешён только администраторам.
     * Требует JWT авторизации.
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/masters/all-small')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    public static function MastersListAllSmall(): void
    {
        try {
            // Получаем список мастеров (без лишних связей)
            $masters = MastersModel::query()
                ->select(['MasterID', 'name'])
                ->get();

            self::api(
                 ['masters' => $masters]
            );

        } catch (\Throwable $e) {
            self::api(
                ['error' => $e->getMessage()],
                500,
                'error'
            );
        }
    }



    /**
     * Получение транзакций выбранного мастера
     *
     * @return void
     * @throws JsonException
     */
    public static function MastersTransaction(): void
    {
        try {
            // Проверка авторизации
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new \Exception('Authorization failed');
            }

            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $jwt = explode(" ", $authHeader)[1] ?? null;
            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }

            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            $MasterID = Input::json('MasterID');
            $language = Input::json('language');

            if (!$MasterID) {
                throw new \Exception('Неверные входные данные.');
            }

            $transactionModel = new FinTransactionsModel();

            $transactions = $transactionModel->table()
                ->leftJoin('StockTransactions as st', 'st.StockID', '=', 'FinTransactions.TransactionID')
                ->leftJoin('OrderItems as oi', 'oi.OrderItemID', '=', 'st.InOutID')
                ->leftJoin('Orders as o', 'o.OrderUUID', '=', 'oi.OrderUUID')
                ->leftJoin('Products as p', 'p.ProductID', '=', 'st.ProductID')
                ->leftJoin('Accounts as a', 'a.AccountID', '=', 'FinTransactions.AccountID')
                ->leftJoin('Company as c', 'c.OwnerID', '=', 'a.OwnerID')
                ->leftJoin('ProductTranslations as pt', function ($join) use ($language) {
                    $join->on('pt.ProductID', '=', 'p.ProductID')
                        ->where('pt.LanguageCode', '=', $language);
                })
                ->select([
                    'FinTransactions.FinTransactionDate',
                    'FinTransactions.AccountID',
                    'FinTransactions.Amount',
                    'FinTransactions.Status',
                    'FinTransactions.Comments',
                    'oi.OrderUUID',
                    'a.Bank',
                    'a.Currency',
                    'c.OwnerName',
                    DB::raw("COALESCE(pt.Name, oi.ProductName, 'Unknown') as ProductName"),
                    'o.OrderName',
                    'st.ProductID',
                    'st.Price',
                ])
                ->where('FinTransactions.CorrespondentID', '=', $MasterID)
                ->orderBy('FinTransactions.FinTransactionDate', 'desc')
                ->get();

            self::setData(
                result: [
                    "transactions" => $transactions,
                ],
                status: 'success'
            );
        } catch (\Exception $e) {
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }




    /**
     * Создание исполнителя
     *
     * @return void
     * @throws JsonException
     */
    public static function CreatedContractor(): void
    {
        try {
            // Проверяем наличие заголовка авторизации
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new \Exception('Authorization failed');
            }
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $jwt = explode(" ", $authHeader)[1] ?? null;
            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }

            // Декодирование JWT (если невалидный токен – выбрасывается исключение)
            $jwtDecode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // Получаем входные данные (JSON)
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            if (!$data) {
                throw new \Exception('Неверные входные данные.');
            }

            // Если идентификатор пользователя не нужен, можно задать фиксированное значение
            $by_user = $jwtDecode->UserID;

            // Генерируем новый UUID для мастера
            $masterId = Uuid::uuid4()->toString();

            // Создаем новую запись в таблице Masters
            $mastersModel = new MastersModel();
            $mastersModel->MasterID = $masterId;
            $mastersModel->type    = $data['type'];
            $mastersModel->name    = $data['name'];
            $mastersModel->tax_id  = $data['tax_id'];
            $mastersModel->comment = $data['comment'];
            $mastersModel->account = $data['account'];
            $mastersModel->phone   = $data['phone'];
            $mastersModel->by_user = $by_user;
            $mastersModel->deleted = 0;
            $mastersModel->save();

            // Если указаны услуги, вставляем соответствующие записи в MasterProducts
            if (!empty($data['services']) && is_array($data['services'])) {
                foreach ($data['services'] as $productID) {
                    $masterProduct = new MasterProductsModel();
                    $masterProduct->MasterID  = $masterId;
                    $masterProduct->ProductID = $productID;
                    $masterProduct->save();
                }
            }

            self::setData(
                result: [
                    "MasterID" => $masterId
                ],
                status: 'success'
            );
        } catch (\Exception $e) {
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }

    public static function AddMasterService(): void
    {
        try {
            // Проверяем наличие заголовка авторизации
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new \Exception('Authorization failed');
            }
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $jwt = explode(" ", $authHeader)[1] ?? null;
            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }

            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // Получаем входные данные
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            if (!$data || empty($data['MasterID']) || empty($data['ProductID'])) {
                throw new \Exception('Неверные входные данные.');
            }

            // Проверяем, что такая услуга ещё не добавлена (по желанию)
            $existing = \Modules\API\Model\MasterProductsModel::where('MasterID', $data['MasterID'])
                ->where('ProductID', $data['ProductID'])
                ->first();
            if ($existing) {
                throw new \Exception('Услуга уже добавлена.');
            }

            // Создаём запись
            $masterProduct = new \Modules\API\Model\MasterProductsModel();
            $masterProduct->MasterID = $data['MasterID'];
            $masterProduct->ProductID = $data['ProductID'];
            $masterProduct->save();

            self::setData(
                result: ["message" => "Услуга добавлена"],
                status: 'success'
            );
        } catch (\Exception $e) {
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }

    public static function DeleteMasterService(): void
    {
        try {
            // Проверяем наличие заголовка авторизации
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new \Exception('Authorization failed');
            }
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $jwt = explode(" ", $authHeader)[1] ?? null;
            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }

            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // Получаем входные данные
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            if (!$data || empty($data['MasterID']) || empty($data['ProductID'])) {
                throw new \Exception('Неверные входные данные.');
            }

            \Modules\API\Model\MasterProductsModel::where('MasterID', $data['MasterID'])
                ->where('ProductID', $data['ProductID'])
                ->delete();

            self::setData(
                result: ["message" => "Услуга удалена"],
                status: 'success'
            );
        } catch (\Exception $e) {
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }




    public function UpdatedContractorToTask(): void
    {
        try {
            // Проверяем наличие заголовка авторизации
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new \Exception('Authorization failed: заголовок не предоставлен.');
            }
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $tokenParts = explode(" ", $authHeader);
            if (count($tokenParts) < 2 || empty($tokenParts[1])) {
                throw new \Exception('Token not provided.');
            }
            $jwt = $tokenParts[1];
            // Декодирование JWT (если токен невалидный – выбросится исключение)
            \Firebase\JWT\JWT::decode($jwt, new \Firebase\JWT\Key($_ENV['s_key'], 'HS256'));

            // Получаем входные данные (JSON)
            $data = \Core\Services\Http\Input::json();

            if (
                empty($data['orderID']) ||
                empty($data['paymentAccount']) ||
                empty($data['serviceID']) ||
                empty($data['masters']) ||
                !is_array($data['masters'])
            ) {
                throw new \Exception('Некорректные входные данные.');
            }

            // Проверяем существование заказа
            $orderItemTask = (new \Modules\API\Model\OrderItemModel())->table()
                ->where('OrderItemID', $data['orderID'])
                ->orderBy('version', 'desc')
                ->first();
            if (!$orderItemTask) {
                throw new \Exception('Заказ не найден.');
            }

            // Начинаем транзакцию
            \Illuminate\Database\Capsule\Manager::beginTransaction();

            // Получаем список StockID из StockTransactions, связанных с данным заказом, услугой и где IsContractor = true
            $stockIds = \Modules\API\Model\StockTransactionsModel::where('InOutID', $data['orderID'])
                ->where('ProductID', $data['serviceID'])
                ->where('IsContractor', true)
                ->pluck('StockID')
                ->toArray();

            // Удаляем записи из FinTransactions, связывая их по TransactionID (который совпадает со StockID в StockTransactions)
            \Modules\API\Model\FinTransactionsModel::whereIn('TransactionID', $stockIds)
                ->where('FinTransactionType', 1)
                ->where('IsContractor', true)
                ->delete();

            // Удаляем старые записи из StockTransactions для данного заказа, услуги и мастера (IsContractor = true)
            \Modules\API\Model\StockTransactionsModel::where('InOutID', $data['orderID'])
                ->where('ProductID', $data['serviceID'])
                ->where('IsContractor', true)
                ->delete();

            // Для каждого мастера из переданного массива создаём новые записи
            foreach ($data['masters'] as $master) {
                if (empty($master['masterId']) || !isset($master['price'])) {
                    throw new \Exception('Некорректные данные мастера. 2');
                }
                // Генерируем новый уникальный идентификатор для связи
                $genUUID = \Ramsey\Uuid\Uuid::uuid4()->toString();

                // Создаём новую запись в FinTransactions
                \Modules\API\Model\FinTransactionsModel::create([
                    'FinTransactionDate' => \Carbon\Carbon::now(),
                    'FinTransactionType' => 1,
                    'AccountID'          => $data['paymentAccount'],
                    'CorrespondentID'    => $master['masterId'],
                    'TransactionID'      => $genUUID,
                    'Amount'             => $master['price'],
                    'Status'             => 1,
                    'IsContractor'       => true,
                    'Comments'           => 'Обновление оплаты работы мастера на сумму "' . $master['price'] .
                        '" изделия "' . $orderItemTask->ProductName . '"',
                ]);

                // Создаём новую запись в StockTransactions
                \Modules\API\Model\StockTransactionsModel::create([
                    'StockTransactionDate' => \Carbon\Carbon::now(),
                    'StockTransactionType' => 4,
                    'ProductID'            => $data['serviceID'],
                    'WarehouseID'          => null,
                    'StockID'              => $genUUID,
                    'InOutID'              => $data['orderID'],
                    'Weight'               => null,
                    'Quantity'             => 1,
                    'Price'                => $master['price'],
                    'Movies'               => 0,
                    'IsContractor'         => true,
                ]);
            }

            \Illuminate\Database\Capsule\Manager::commit();

            self::setData(
                result: ["message" => "Данные мастеров успешно обновлены."],
                status: 'success'
            );
        } catch (\Exception $e) {
            \Illuminate\Database\Capsule\Manager::rollBack();
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }


    public function UpdatedContractorToTransaction(): void
    {
        try {
            // Проверяем наличие заголовка авторизации
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new \Exception('Authorization failed: заголовок не предоставлен.');
            }
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $tokenParts = explode(" ", $authHeader);
            if (count($tokenParts) < 2 || empty($tokenParts[1])) {
                throw new \Exception('Token not provided.');
            }
            $jwt = $tokenParts[1];
            // Декодирование JWT (если токен невалидный – выбросится исключение)
            \Firebase\JWT\JWT::decode($jwt, new \Firebase\JWT\Key($_ENV['s_key'], 'HS256'));

            // Получаем входные данные (JSON)
            $data = \Core\Services\Http\Input::json();

            if (
                empty($data['orderID']) ||
                empty($data['paymentAccount']) ||
                empty($data['serviceID']) ||
                empty($data['masters']) ||
                !is_array($data['masters'])
            ) {
                throw new \Exception('Некорректные входные данные.');
            }

            // Проверяем существование заказа
            $orderItemTask = (new \Modules\API\Model\OrderItemModel())->table()
                ->where('OrderItemID', $data['orderID'])
                ->orderBy('version', 'desc')
                ->first();
            if (!$orderItemTask) {
                throw new \Exception('Заказ не найден.');
            }

            // Начинаем транзакцию
            \Illuminate\Database\Capsule\Manager::beginTransaction();

            // Получаем список StockID из StockTransactions, связанных с данным заказом, услугой и где IsContractor = true
            $stockIds = \Modules\API\Model\StockTransactionsModel::where('InOutID', $data['orderID'])
                ->where('ProductID', $data['serviceID'])
                ->where('IsContractor', true)
                ->pluck('StockID')
                ->toArray();


            // Для каждого мастера из переданного массива создаём новые записи
            foreach ($data['masters'] as $master) {
                if (empty($master['masterId']) || !isset($master['price'])) {
                    throw new \Exception('Некорректные данные мастера. 2');
                }
                // Генерируем новый уникальный идентификатор для связи
                $genUUID = \Ramsey\Uuid\Uuid::uuid4()->toString();

                // Создаём новую запись в FinTransactions
                \Modules\API\Model\FinTransactionsModel::create([
                    'FinTransactionDate' => \Carbon\Carbon::now(),
                    'FinTransactionType' => 1,
                    'AccountID'          => $data['paymentAccount'],
                    'CorrespondentID'    => $master['masterId'],
                    'TransactionID'      => $genUUID,
                    'Amount'             => $master['price'],
                    'Status'             => 1,
                    'IsContractor'       => true,
                    'Comments'           => 'Обновление оплаты работы мастера на сумму "' . $master['price'] .
                        '" изделия "' . $orderItemTask->ProductName . '"',
                ]);

                // Создаём новую запись в StockTransactions
                \Modules\API\Model\StockTransactionsModel::create([
                    'StockTransactionDate' => \Carbon\Carbon::now(),
                    'StockTransactionType' => 4,
                    'ProductID'            => $data['serviceID'],
                    'WarehouseID'          => null,
                    'StockID'              => $genUUID,
                    'InOutID'              => $data['orderID'],
                    'Weight'               => null,
                    'Quantity'             => 1,
                    'Price'                => $master['price'],
                    'Movies'               => 0,
                    'IsContractor'         => true,
                ]);
            }

            \Illuminate\Database\Capsule\Manager::commit();

            self::setData(
                result: ["message" => "Данные мастеров успешно обновлены."],
                status: 'success'
            );
        } catch (\Exception $e) {
            \Illuminate\Database\Capsule\Manager::rollBack();
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }


    /**
     * Возвращает список транзакций мастеров по itemUUID
     *
     * @param ValidatedRequest $request
     * @return void
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/masters/task-transaction-list')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    #[Validate([
        'itemUUID' => ['required' => true, 'type' => 'uuid'],
        'language' => ['required' => true, 'equals' => 2],
    ])]
    public static function TaskSelectedMastersList(ValidatedRequest $request): void
    {
        try {
            $request->check();

            $itemUUID = $request->input('itemUUID');
            $language = $request->input('language');

            $transactions = DB::table('StockTransactions as st')
                ->join('FinTransactions as ft', 'ft.TransactionID', '=', 'st.StockID')
                ->join('Masters as m', 'm.MasterID', '=', 'ft.CorrespondentID')
                ->join('Products as p', 'st.ProductID', '=', 'p.ProductID')
                ->leftJoin('ProductTranslations as t', function ($join) use ($language) {
                    $join->on('t.ProductID', '=', 'p.ProductID')
                        ->where('t.LanguageCode', '=', $language);
                })
                ->select(
                    'st.StockTransactionID',
                    'st.StockTransactionDate',
                    'st.StockTransactionType',
                    'st.ProductID',
                    'st.InOutID as itemUUID',
                    'ft.CorrespondentID as MasterID',
                    'ft.AccountID as AccountID',
                    'm.name as MasterName',
                    'm.type as MasterType',
                    DB::raw("COALESCE(t.Name, 'Unknown') as ProductName"),
                    'st.Price',
                    'st.Quantity',
                    'st.IsContractor'
                )
                ->where('st.InOutID', '=', $itemUUID)
                ->where('st.IsContractor', '=', 1)
                ->orderBy('st.StockTransactionDate')
                ->get();

            self::api(['transactions' => $transactions]);

        } catch (\Throwable $e) {
            self::api(
                ['error' => $e->getMessage()],
                500,
                'error'
            );
        }
    }




    public function MastersContractorsList(): void
    {
        try {
            // Проверяем наличие заголовка авторизации
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new \Exception('Authorization failed');
            }
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $jwt = explode(" ", $authHeader)[1] ?? null;
            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }

            // Декодирование JWT
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // Получаем входные данные
            $data = Input::json();

            $itemUUID = $data['itemUUID'];
            if (empty($itemUUID)) {
                throw new \Exception('Не указаны itemUUID.');
            }

            $transactions = DB::table('StockTransactions as st')
                ->join('FinTransactions as ft', 'ft.TransactionID', '=', 'st.StockID')
                ->join('Masters as m', 'm.MasterID', '=', 'ft.CorrespondentID')
                ->join('Products as p', 'st.ProductID', '=', 'p.ProductID') // <-- добавили джойн
                ->select(
                    'st.StockTransactionID',
                    'st.StockTransactionDate',
                    'st.StockTransactionType',
                    'st.ProductID',
                    'st.InOutID as itemUUID',

                    'ft.CorrespondentID as MasterID',

                    'm.name as MasterName',
                    'm.type as MasterType',

                    'p.ProductName',

                    'st.Price',
                    'st.Quantity',
                    'st.IsContractor'
                )
                ->where('st.InOutID', '=', $itemUUID)
                ->where('st.IsContractor', '=', 1)
                ->orderBy('st.StockTransactionDate')
                ->get();

            self::setData(
                result: ['transactions' => $transactions],
                status: 'success'
            );
        } catch (\Exception $e) {
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }



    public static function DeleteContractorTransaction(): void
    {
        try {
            // 1) JWT авторизация
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new \Exception('Authorization failed');
            }
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $jwt = explode(" ", $authHeader)[1] ?? null;
            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // 2) Принимаем входные данные
            $data = Input::json();
            if (empty($data['stockTransactionID'])) {
                throw new \Exception('stockTransactionID не передан.');
            }
            $stockTransactionID = $data['stockTransactionID'];

            // 3) Находим транзакцию в StockTransactions
            $stRow = StockTransactionsModel::where('StockTransactionID', $stockTransactionID)->first();
            if (!$stRow) {
                throw new \Exception('Запись StockTransactions не найдена.');
            }

            // 4) Для выполнения удаления FinTransactions нужно TransactionID (который = StockID)
            $stockID = $stRow->StockID;

            \Illuminate\Database\Capsule\Manager::beginTransaction();

            // Удаляем из FinTransactions (если нужно удалять все исполнители-оплаты)
            FinTransactionsModel::where('TransactionID', $stockID)
                ->where('FinTransactionType', 1)
                ->where('IsContractor', true)
                ->delete();

            // Удаляем саму StockTransaction
            StockTransactionsModel::where('StockTransactionID', $stockTransactionID)->delete();

            \Illuminate\Database\Capsule\Manager::commit();

            self::setData(
                result: ['message' => 'Исполнитель удалён.'],
                status: 'success'
            );
        } catch (\Exception $e) {
            \Illuminate\Database\Capsule\Manager::rollBack();
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }
}
