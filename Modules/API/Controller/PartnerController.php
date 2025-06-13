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
use Illuminate\Support\Carbon;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use Modules\API\Helpers\PartnerInfo;
use Modules\API\Model\FinTransactionsModel;
use Modules\API\Model\OrderModel;
use Modules\API\Model\PartnerModel;
use Modules\API\Model\StockTransactionsModel;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * CompanyController - контроллер для управления данными компаний.
 *
 * company type:
 * 0 = Покупатели и продавцы
 * 1 = Продавец
 * 2 = Покупатель
 */
class PartnerController extends Controller
{
    /**
     * Получение всех активных партнеров
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function getPartnerList(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];

                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));


                $partnerModel = new PartnerModel();

                $partners = $partnerModel->table()
                    ->where('deleted_at', '=', null)
                    ->get();


                self::setData(result: ['partners' => $partners], status: 'success');

            } else {
                self::setData(result: ['partners' => [], 'error' => 'auth failed'], statusCode: 500, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['partners' => [], 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }

    /**
     * Добавление нового партнера.
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function addPartner(): void
    {
        try {
            // Проверка авторизации
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));
            } else {
                self::setData(result: ['error' => 'auth failed'], statusCode: 401, status: 'error');
            }

            // Получение входных данных
            $input = Input::json();

            // Валидация данных
            if (empty($input['legalName'])) {
                self::setData(result: ['error' => 'All fields are required'], statusCode: 400, status: 'error');
            }

            // Создание нового партнера
            $partnerModel = new PartnerModel();
            $partnerModel->LegalName = $input['legalName']; // Юридическое название
            $partnerModel->ShortName = $input['shortName']; // Краткое название
            $partnerModel->TaxID = $input['taxID'];         // ИНН
            $partnerModel->Comments = $input['comments'] ?? null; // Комментарии (опционально)
            $partnerModel->CompanyType = $input['companyType'];   // Тип компании (0, 1, 2)
            $partnerModel->Status = $input['status'];            // Статус (0 или 1)

            $partnerModel->save();

            // Возвращение успешного ответа
            self::setData(result: ['message' => 'Partner added successfully'], status: 'success');
        } catch (\Exception $e) {
            // Обработка ошибок
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }

    /**
     * Обновление данных партнера.
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function update(): void
    {
        try {
            // Проверка авторизации
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));
            } else {
                self::setData(result: ['error' => 'auth failed'], statusCode: 401, status: 'error');
            }

            // Получение входных данных
            $input = Input::json();

            // Проверка обязательных полей
            if (empty($input['PartnerID']) || !isset($input['Status']) || !isset($input['CompanyType'])) {
                self::setData(result: ['error' => 'Partner ID, Status, and Company Type are required'], statusCode: 400, status: 'error');
            }

            // Обновление данных партнера
            $partnerModel = new PartnerModel();
            $partnerModel->table()
                ->where('PartnerID', '=', $input['PartnerID'])
                ->update([
                    'CompanyType' => $input['CompanyType'],
                    'Comments' => $input['Comments'],
                    'LegalName' => $input['LegalName'],
                    'ShortName' => $input['ShortName'],
                    'Status' => $input['Status'],
                    'TaxID' => $input['TaxID'],
                ]);

            self::setData(result: ['message' => 'Partner updated successfully'], status: 'success');
        } catch (\Exception $e) {
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }

    /**
     * Удаление партенра.
     *
     * @param $id
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function deletePartner(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
                JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                $partnerModel = new PartnerModel();

                $partnerModel->table()
                    ->where('PartnerID', '=', Input::json('partnerID'))
                    ->update(['deleted_at' => Carbon::now()]);

                self::setData(result: ['message' => 'Partner deleted successfully'], status: 'success');
            } else {
                self::setData(result: ['error' => 'Unauthorized'], statusCode: 401, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }


    /**
     * Получение всех активных партнеров для создания продукта
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function getPartnerListFotProduct(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];

                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));


                $partnerModel = new PartnerModel();
                $partners = $partnerModel->table()
                    ->select(['LegalName', 'ShortName', 'PartnerID'])
                    ->where('deleted_at', '=', null)
                    ->whereIn('CompanyType', ['0', '1']) // Условие на выбор нескольких значений
                    ->where('Status', '=', '1')
                    ->get();


                self::setData(result: ['partners' => $partners], status: 'success');

            } else {
                self::setData(result: ['partners' => [], 'error' => 'auth failed'], statusCode: 500, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['partners' => [], 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }

    /**
     * Получение всех активных клиентов.
     *
     * Метод: POST
     * URL: /api/v1/partner/get-client
     * Требуется авторизация по JWT (роль: admin).
     *
     * Ответ:
     * - `partners`: массив клиентов с полями LegalName, ShortName, PartnerID
     *
     * @return void
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/partner/get-client')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    public static function getClient(): void
    {
        try {
            // Получение активных клиентов типа 0 или 2
            $partners = (new PartnerModel())
                ->table()
                ->select(['LegalName', 'ShortName', 'PartnerID'])
                ->whereNull('deleted_at')
                ->whereIn('CompanyType', ['0', '2'])
                ->where('Status', '=', '1')
                ->get();

            self::api([
                'partners' => $partners,
            ]);
        } catch (\Throwable $e) {
            self::api([
                'partners' => [],
                'error' => $e->getMessage(),
            ], 500, 'error');
        }
    }


    /**
     * @return void
     * @throws JsonException
     */
    public function getPartnerTransactions(): void
    {
        try {
            $startDate = Input::json('startDate') ?? null;
            $endDate = Input::json('endDate') ?? null;
            $partnerId = Input::json('partnerID');

            $stockModel = new StockTransactionsModel();

            $transactions = $stockModel->table()
                ->join('Products', 'StockTransactions.ProductID', '=', 'Products.ProductID')
                ->join('Partner', 'StockTransactions.InOutID', '=', 'Partner.PartnerID')
                ->leftJoin('FinTransactions', 'StockTransactions.StockTransactionID', '=', 'FinTransactions.TransactionID')
                ->leftJoin('Accounts', 'FinTransactions.AccountID', '=', 'Accounts.AccountID')
                ->leftJoin('Company', 'Accounts.OwnerID', '=', 'Company.OwnerID')
                ->selectRaw('
        StockTransactions.StockTransactionDate,
        StockTransactions.StockTransactionType,
        Products.ProductName,
        Products.Unit,
        Products.ProductID,
        StockTransactions.Quantity,
        StockTransactions.Price,
        (StockTransactions.Quantity * StockTransactions.Price) as TotalAmount,
        Partner.ShortName as PartnerName,
        Accounts.Currency as Currency,
        Accounts.AccountID as BankId,
        Accounts.Bank as BankName,
        Accounts.Description as BankDescription,
        Company.OwnerName as CompanyName,
        Company.OfficialName as CompanyOfficialName,
        Company.OwnerID as CompanyOwnerID
    ')
                ->where('Partner.PartnerID', '=', $partnerId);


            if ($startDate) {
                $transactions->where('StockTransactions.StockTransactionDate', '>=', $startDate);
            }
            if ($endDate) {
                $transactions->where('StockTransactions.StockTransactionDate', '<=', $endDate);
            }
            $orderModel = new OrderModel();

            $clientOrders = $orderModel->table()
                ->selectRaw('OrderUUID, MAX(Version) as MaxVersion, OrderName, EstimatedCost, CreatedAt')
                ->where('PartnerID', '=', $partnerId)
                ->groupBy('OrderUUID', 'OrderName', 'EstimatedCost', 'CreatedAt'); // Группируем по полям, кроме агрегатного

            if ($startDate) {
                $transactions->where('StockTransactions.StockTransactionDate', '>=', $startDate);
                $clientOrders->where('CreatedAt', '>=', $startDate);
            }
            if ($endDate) {
                $transactions->where('StockTransactions.StockTransactionDate', '<=', $endDate);
                $clientOrders->where('CreatedAt', '<=', $endDate);
            }

            $clientOrders = $orderModel->table()
                ->selectRaw('
                CreatedAt as StockTransactionDate,
                2 as StockTransactionType,
                OrderName as ProductName,
                NULL as Unit,
                NULL as ProductID,
                NULL as Quantity,
                EstimatedCost as Price,
                EstimatedCost as TotalAmount,
                NULL as PartnerName,
                NULL as Currency,
                NULL as BankId,
                NULL as BankName,
                NULL as BankDescription,
                NULL as CompanyName,
                NULL as CompanyOfficialName,
                NULL as CompanyOwnerID
            ')
                ->where('PartnerID', '=', $partnerId);

            if ($startDate) {
                $clientOrders->where('CreatedAt', '>=', $startDate);
            }
            if ($endDate) {
                $clientOrders->where('CreatedAt', '<=', $endDate);
            }

            $clientOrdersData = $clientOrders->get();

            // ➕ **Добавляем поле `Type = 2` в каждую продажу**
            $clientOrdersData = $clientOrdersData->map(function ($item) {
                $item->StockTransactionType = 2; // 2 - это продажа
                $item->Quantity = 1; // 2 - это продажа
                return $item;
            });

            // 🔹 3. Объединяем покупки и продажи в один массив
            $mergedTransactions = array_merge($transactions->get()->toArray(), $clientOrdersData->toArray());


            self::setData(result: ['transaction' => $mergedTransactions], status: 'success');
        } catch (\Exception $e) {
            self::setData(result: ['message' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }


    /**
     * @return void
     */
    public function partnerOrderList()
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                $orderModel = new OrderModel();
                $partnerId = Input::json('uuid');

                // Получаем даты из запроса или ставим дефолтные (год назад -> сегодня)
                $startDate = Input::json('startDate', date('Y-m-d', strtotime('-1 year')));
                $endDate = Input::json('endDate', date('Y-m-d'));

                // 1. Получаем заказы партнёра (только последние версии)
                $orders = $orderModel->table()
                    ->whereIn('OrderID', function ($query) {
                        $query->selectRaw('OrderID')
                            ->from('Orders as o1')
                            ->whereRaw('Version = (SELECT MAX(Version) FROM Orders WHERE OrderUUID = o1.OrderUUID)');
                    })
                    ->where('PartnerID', '=', $partnerId)
                    ->whereBetween('CreatedAt', [$startDate, $endDate])
                    ->orderByDesc('CreatedAt')
                    ->get();

                // Преобразуем заказы в массив
                $ordersArray = $orders->map(fn($order) => (array)$order)->toArray();

                // Получаем все финансовые транзакции по OrderUUID для заказов
                $orderUUIDs = array_column($ordersArray, 'OrderUUID');
                $transactions = (new FinTransactionsModel())->table()
                    ->whereIn('TransactionID', $orderUUIDs)
                    ->get()
                    ->groupBy('TransactionID');

                // Добавляем транзакции к заказам
                foreach ($ordersArray as &$order) {
                    $order['Transactions'] = $transactions->get($order['OrderUUID'], []);
                }

                // 2. Получаем данные о партнёре
                $partnerData = (new PartnerModel())->table()
                    ->where('PartnerID', '=', $partnerId)
                    ->first();

                // 3. Получаем поставки от партнёра с фильтрами по дате
                $suppliesQuery = DB::table('StockTransactions as st')
                    ->select(
                        'st.StockTransactionID',
                        'st.StockTransactionDate',
                        'st.Quantity as supplyQuantity',
                        DB::raw('COALESCE(SUM(spent.Quantity), 0) as spentQuantity'),
                        'w.WarehouseID',
                        'w.WarehouseName',
                        'a.AccountID',
                        'a.Currency',
                        'ft.CorrespondentID',
                        'c.OfficialName as companyName'
                    )
                    ->leftJoin('StockTransactions as spent', function ($join) {
                        $join->on('spent.StockID', '=', 'st.StockTransactionID')
                            ->where('spent.StockTransactionType', '=', 2);
                    })
                    ->leftJoin('Warehouse as w', 'st.WarehouseID', '=', 'w.WarehouseID')
                    ->leftJoin('FinTransactions as ft', 'st.StockID', '=', 'ft.TransactionID')
                    ->leftJoin('Accounts as a', 'ft.AccountID', '=', 'a.AccountID')
                    ->leftJoin('Company as c', 'a.OwnerID', '=', 'c.OwnerID')
                    ->where('st.StockTransactionType', '=', 1)
                    ->whereBetween('st.StockTransactionDate', [$startDate, $endDate])
                    ->groupBy(
                        'st.StockTransactionID',
                        'st.StockTransactionDate',
                        'st.Quantity',
                        'w.WarehouseID',
                        'w.WarehouseName',
                        'a.AccountID',
                        'a.Currency',
                        'a.Description',
                        'ft.CorrespondentID',
                        'c.OfficialName'
                    )
                    ->orderByDesc('st.StockTransactionDate');

                $suppliesArray = $suppliesQuery->get()->map(function ($supply) {
                    $availableQuantity = $supply->supplyQuantity + $supply->spentQuantity;
                    return [
                        'StockTransactionDate' => $supply->StockTransactionDate,
                        // Дополнительное поле с отформатированной датой
                        'formattedDate'        => date('d.m.Y', strtotime($supply->StockTransactionDate)),
                        'supplyQuantity'       => $supply->supplyQuantity,
                        'spentQuantity'        => $supply->spentQuantity,
                        'availableQuantity'    => max($availableQuantity, 0),
                        'WarehouseID'          => $supply->WarehouseID,
                        'WarehouseName'        => $supply->WarehouseName,
                        'accountID'            => $supply->AccountID,
                        'Currency'             => $supply->Currency,
                        'companyName'          => $supply->companyName,
                    ];
                });

                // 4. Вычисляем суммарные показатели поставок за всё время (без фильтра по дате)
                $supplyTotals = DB::table('StockTransactions')
                    ->selectRaw('COUNT(*) as totalPurchases, COALESCE(SUM(Quantity * Price), 0) as totalSupplySum')
                    ->where('StockTransactionType', '=', 1)
                    ->where('InOutID', '=', $partnerId)
                    ->first();

                // Получаем баланс через существующую функцию
                $balance = PartnerInfo::getBalance($partnerId);
                // Если $balance – это массив, то используем массивную нотацию:
                $balance['totalPurchases'] = $supplyTotals->totalPurchases;
                $balance['totalSuppliesSum'] = $supplyTotals->totalSupplySum;

                // Формируем итоговый ответ
                self::setData(result: [
                    'partner' => $partnerData,
                    'orders'  => $ordersArray,
                    'supplies'=> $suppliesArray,
                    'balance' => $balance
                ], status: 'success');

            } else {
                self::setData(result: [
                    'partner' => [],
                    'orders'  => [],
                    'supplies'=> [],
                    'balance' => [],
                    'error'   => 'auth failed'
                ], statusCode: 500, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: [
                'partner' => [],
                'orders'  => [],
                'supplies'=> [],
                'balance' => [],
                'error'   => $e->getMessage()
            ], statusCode: 500, status: 'error');
        }
    }


    /**
     * Получение информации об оплате по заказу.
     * Возвращает: общую стоимость, оплачено, долг, переплату по другим заказам,
     * возможную скидку и её процент.
     *
     * @param ValidatedRequest $request
     * @return void
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/orders/payment-info')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    #[Validate([
        'orderUUID' => ['required' => true, 'type' => 'uuid'],
    ])]
    public function orderPaymentInfo(ValidatedRequest $request): void
    {
        try {
            $request->check();
            $orderUUID = $request->input('orderUUID');

            // Получаем сам заказ
            $order = OrderModel::query()->where('OrderUUID', $orderUUID)->first();
            if (!$order) {
                throw new \Exception('Order not found');
            }

            $estimatedCost = (float)$order->EstimatedCost;

            // Получаем все транзакции по этому заказу
            $transactions = FinTransactionsModel::query()
                ->where('TransactionID', $orderUUID)
                ->where('Status', 1)
                ->get();

            $paidForThisOrder = $transactions->sum('Amount');
            $debt = $estimatedCost - $paidForThisOrder;

            // Получаем переплату по другим заказам этого партнёра
            $otherOrders = OrderModel::query()
                ->select('orders.*')
                ->joinSub(
                    OrderModel::query()
                        ->selectRaw('[OrderUUID], MAX([Version]) as max_version')
                        ->groupBy('OrderUUID'),
                    'latest_versions',
                    function ($join) {
                        $join->on('orders.OrderUUID', '=', 'latest_versions.OrderUUID')
                            ->whereColumn('orders.Version', '=', 'latest_versions.max_version');
                    }
                )
                ->where('orders.PartnerID', $order->PartnerID)
                ->where('orders.OrderUUID', '!=', $orderUUID)
                ->get();

            $overpaidFromOtherOrders = 0.0;

            foreach ($otherOrders as $otherOrder) {
                $total = (float)$otherOrder->EstimatedCost;
                $paid = FinTransactionsModel::query()
                    ->where('TransactionID', $otherOrder->OrderUUID)
                    ->where('Status', 1)
                    ->sum('Amount');

                $delta = $paid - $total;
                if ($delta > 0) {
                    $overpaidFromOtherOrders += $delta;
                }
            }

            // Скидка может быть только если есть переплата и текущий долг > 0
            $discountAmount = max(0, min($overpaidFromOtherOrders, $debt));
            $discountPercent = $estimatedCost > 0 ? round(($discountAmount / $estimatedCost) * 100, 1) : 0;

            self::api([
                'estimatedCost'           => $estimatedCost,
                'paidForThisOrder'        => $paidForThisOrder,
                'debt'                    => $debt,
                'overpaidFromOtherOrders' => $overpaidFromOtherOrders,
                'discountAmount'          => $discountAmount,
                'discountPercent'         => $discountPercent,
            ]);
        } catch (\Throwable $e) {
            self::api(
                ['error' => $e->getMessage()],
                500,
                'error'
            );
        }
    }


    /**
     * Получение баланса партнёра по UUID.
     *
     * Требуется JWT авторизация.
     *
     * @param ValidatedRequest $request
     * @return void
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/partner/partner-balance')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    #[Validate([
        'uuid' => ['required' => true, 'type' => 'uuid'],
    ])]
    public static function partnerBalance(ValidatedRequest $request): void
    {
        try {
            $request->check();
            $partnerId = $request->input('uuid');

            $balance = PartnerInfo::getBalance($partnerId);

            self::api(
                ['balance' => $balance]
            );
        } catch (\Throwable $e) {
            self::api(
                [
                    'balance' => [],
                    'error' => $e->getMessage(),
                ],
                500,
                'error'
            );
        }
    }



}