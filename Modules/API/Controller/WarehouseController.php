<?php

namespace Modules\API\Controller;

use Composer\DependencyResolver\Transaction;
use Core\Routing\Attributes\HttpMethod;
use Core\Services\Attributes\Validate;
use Core\Services\Auth\Attributes\Authorize;
use Core\Services\Http\Input;
use Controller;
use Core\Services\Http\ValidatedRequest;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use Modules\API\Model\CompanyModel;
use Modules\API\Model\ProductModel;
use Modules\API\Model\StockMovementHistoryModel;
use Modules\API\Model\StockTransactionsModel;
use Modules\API\Model\WarehouseModel;

/**
 * CompanyController - контроллер для управления данными компаний.
 */
class WarehouseController extends Controller
{
    /**
     * Получение всех активных складов
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn]
	#[HttpMethod(['get'], '/')]
    public function getWarehouseList(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];

                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));


                $warehouseModel = new WarehouseModel();

                $warehouses = $warehouseModel->table()
                    ->select(
                        'Warehouse.WarehouseID',
                        'Warehouse.OwnerID',
                        'Warehouse.WarehouseName',
                        'Warehouse.WarehouseLocation',
                        'Warehouse.Status as WarehouseStatus',
                        'Company.OwnerName as CompanyName',
                        'Company.OfficialName as CompanyOfficialName'
                    )
                    ->leftJoin('Company', 'Warehouse.OwnerID', '=', 'Company.OwnerID')
                    ->whereNull('Warehouse.deleted_at')
                    ->whereNull('Company.deleted_at')
                    ->groupBy(
                        'Warehouse.WarehouseID',
                        'Warehouse.OwnerID',
                        'Warehouse.WarehouseName',
                        'Warehouse.WarehouseLocation',
                        'Warehouse.Status',
                        'Company.OwnerName',
                        'Company.OfficialName'
                    )
                    ->get();



                self::setData(result: ['warehouses' => $warehouses], status: 'success');

            } else {
                self::setData(result: ['warehouses' => [], 'error' => 'auth failed'], statusCode: 500, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['warehouses' => [], 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }

    /**
     * Получение всех активных складов в коротком виде
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn]
    public function getWarehouseListShort(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];

                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));


                $warehouseModel = new WarehouseModel();

                $warehouses = $warehouseModel->table()
                    ->select([
                        'Warehouse.WarehouseID',
                        'Warehouse.WarehouseName',
                        'Warehouse.OwnerID',
                        'Company.OwnerName' // Добавляем OwnerName из таблицы Company
                    ])
                    ->leftJoin('Company', 'Warehouse.OwnerID', '=', 'Company.OwnerID')
                    ->whereNull('Warehouse.deleted_at')
                    ->whereNull('Company.deleted_at')
                    ->where('Warehouse.Status', 1)
                    ->get();


                self::setData(result: ['warehouses' => $warehouses], status: 'success');

            } else {
                self::setData(result: ['warehouses' => [], 'error' => 'auth failed'], statusCode: 500, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['warehouses' => [], 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }



    /**
     * Получает список всех поставок материала с учетом оставшегося количества
     *
     * @return void
     */
    public function materialTransactions(): void
    {
        try {
            // ✅ Проверка авторизации
            if (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                throw new \Exception('Authorization failed');
            }

            $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1] ?? null;
            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }

            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // ✅ Получаем ID склада и продукта из входных параметров
            $warehouseID = Input::json('warehouseID');
            $productID = Input::json('productID');

            if (!$warehouseID || !$productID) {
                throw new \Exception('WarehouseID and ProductID are required.');
            }

            // ✅ Получаем **все поступления** (StockTransactionType = 1)
            $transactions = DB::table('StockTransactions as st')
                ->join('Warehouse as w', 'st.WarehouseID', '=', 'w.WarehouseID')
                ->where('st.WarehouseID', $warehouseID)
                ->where('st.ProductID', $productID)
                ->where('st.StockTransactionType', 1) // Только поступления
                ->select([
                    'st.StockTransactionID',
                    'st.WarehouseID',
                    'w.WarehouseName',
                    'st.StockTransactionDate',
                    'st.Price',
                    'st.Quantity'
                ])
                ->orderBy('st.StockTransactionDate', 'asc')
                ->get();

            // ✅ Получаем **списания и резервы** (StockTransactionType = 2, 3)
            $stockUsages = DB::table('StockTransactions')
                ->where('WarehouseID', $warehouseID)
                ->where('ProductID', $productID)
                ->whereIn('StockTransactionType', [2, 3]) // 2 = списание, 3 = бронь
                ->whereNotNull('StockID') // Привязаны к поступлениям
                ->select('StockID', 'StockTransactionType', DB::raw('SUM(Quantity) as totalUsed'))
                ->groupBy('StockID', 'StockTransactionType')
                ->get();

            // ✅ Группируем данные по StockID
            $reservedQuantities = [];
            $takenQuantities = [];
            foreach ($stockUsages as $usage) {
                if ($usage->StockTransactionType == 3) {
                    $reservedQuantities[$usage->StockID] = abs($usage->totalUsed);
                } elseif ($usage->StockTransactionType == 2) {
                    $takenQuantities[$usage->StockID] = abs($usage->totalUsed);
                }
            }

            // ✅ Формируем список поставок с реальным остатком
            $availableStocks = [];
            foreach ($transactions as $transaction) {
                $stockTransactionID = $transaction->StockTransactionID;
                $reserved = $reservedQuantities[$stockTransactionID] ?? 0;
                $taken = $takenQuantities[$stockTransactionID] ?? 0;

                // ✅ Вычисляем доступное количество
                $availableQuantity = max($transaction->Quantity - $reserved - $taken, 0);

                if ($availableQuantity > 0) {
                    $availableStocks[] = [
                        'stockTransactionID' => $stockTransactionID,
                        'warehouseID' => $transaction->WarehouseID,
                        'WarehouseName' => $transaction->WarehouseName,
                        'stockDate' => $transaction->StockTransactionDate,
                        'price' => $transaction->Price,
                        'availableQuantity' => $availableQuantity
                    ];
                }
            }

            // ✅ Возвращаем JSON с результатом
            self::setData(
                result: ['stocks' => $availableStocks],
                status: 'success'
            );

        } catch (\Exception $e) {
            // ❌ Ошибка
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }



    /**
     * Получение всех активных складов для создания продукта
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn]
    public function getWarehouseListFotProduct(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];

                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));


                $warehouseModel = new WarehouseModel();

                $warehouses = $warehouseModel->table()
                    ->select(
                        'Warehouse.WarehouseID',
                        'Warehouse.OwnerID',
                        'Warehouse.WarehouseName',
                        'Warehouse.WarehouseLocation',
                        'Warehouse.Status as WarehouseStatus',
                        'Company.OwnerName as CompanyName',
                        'Company.OfficialName as CompanyOfficialName'
                    )
                    ->leftJoin('Company', 'Warehouse.OwnerID', '=', 'Company.OwnerID')
                    ->whereNull('Warehouse.deleted_at')
                    ->whereNull('Company.deleted_at')
                    ->get();

                self::setData(result: ['warehouses' => $warehouses], status: 'success');

            } else {
                self::setData(result: ['warehouses' => [], 'error' => 'auth failed'], statusCode: 500, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['warehouses' => [], 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }

    /**
     * Удаление склада из компании
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn]
    public function deletedWarehouse(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
                JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                $warehouseModel = new WarehouseModel();

                $warehouseModel->table()
                    ->where('WarehouseID', '=', Input::json('WarehouseID'))
                    ->update(['deleted_at' => Carbon::now()]);

                self::setData(result: ['message' => 'WarehouseID deleted successfully'], status: 'success');
            } else {
                self::setData(result: ['error' => 'Unauthorized'], statusCode: 401, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }


    /**
     * Редактирование склада
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn]
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
            if (empty($input['WarehouseID']) || !isset($input['WarehouseLocation']) || !isset($input['WarehouseName']) || !isset($input['WarehouseStatus'])) {
                self::setData(result: ['error' => 'WarehouseID, WarehouseLocation, and WarehouseName are required'], statusCode: 400, status: 'error');
            }

            // Обновление данных партнера
            $warehouseModel = new WarehouseModel();
            $warehouseModel->table()
                ->where('WarehouseID', '=', $input['WarehouseID'])
                ->update([
                    'WarehouseLocation' => $input['WarehouseLocation'],
                    'WarehouseName' => $input['WarehouseName'],
                    'Status' => $input['WarehouseStatus'],
                ]);

            self::setData(result: ['message' => 'Partner updated successfully'], status: 'success');
        } catch (\Exception $e) {
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }


    /**
     * Создание нового склада
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn]
    public function createWarehouse(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];

                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                // Получаем данные из тела запроса
                $data = Input::json();

                $selectCompany = $data['OwnerID'] ?? null;
                $warehouseName = $data['WarehouseName'] ?? null;
                $warehouseLocation = $data['WarehouseLocation'] ?? null;
                $warehouseStatus = isset($data['WarehouseStatus']) ? $data['WarehouseStatus'] : '1';

                if (!$selectCompany || !$warehouseName) {
                    // Код сообщения: 1 - Отсутствуют обязательные поля
                    self::setData(result: ['message' => 1], statusCode: 400, status: 'error');
                } else {
                    $companyModel = new CompanyModel();
                    $warehouseModel = new WarehouseModel();

                    // Проверяем, существует ли компания в базе данных
                    $selectCompanyDB = $companyModel->table()
                        ->where('OwnerID', '=', $selectCompany)
                        ->where('deleted_at', '=', null)
                        ->first();

                    if ($selectCompanyDB === null) {
                        // Код сообщения: 2 - Компания не найдена или удалена
                        self::setData(result: ['message' => 2], statusCode: 404, status: 'error');
                    } else {
                        // Подготовка данных для вставки
                        $warehouseData = [
                            'OwnerID' => $selectCompany,
                            'WarehouseName' => trim($warehouseName),
                            'WarehouseLocation' => trim($warehouseLocation),
                            'Status' => $warehouseStatus
                        ];

                        try {
                            // Вставка данных склада в базу
                            $warehouseModel->table()->insert($warehouseData);

                            // Код сообщения: 3 - Склад успешно создан
                            self::setData(result: ['message' => 3], status: 'success');
                        } catch (\Exception $e) {
                            // Код сообщения: 4 - Ошибка при сохранении склада
                            self::setData(result: ['message' => 4, 'error' => $e->getMessage()], statusCode: 500, status: 'error');
                        }
                    }
                }
            } else {
                // Код сообщения: 5 - Не авторизован
                self::setData(result: ['message' => 5], statusCode: 401, status: 'error');
            }
        } catch (\Exception $e) {
            // Код сообщения: 6 - Общая ошибка
            self::setData(result: ['message' => 6, 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }


    /**
     * Получение списка товаров на складе.
     *
     * @return void
     */
    /**
     * Получение списка товаров на складе с учетом остатков, заморозки и средней цены.
     *
     * @return void
     */
    public function getWarehouseStock(): void
    {
        try {
            // Получение данных из запроса
            $warehouseID = Input::json('warehouseID'); // UUID склада из POST-запроса
            if (!$warehouseID) {
                throw new \Exception("Warehouse ID is required");
            }

            // Читаем язык (по умолчанию 'ru')
            $language = Input::json('language') ?? 'ru';

            // Проверяем, существует ли склад
            $warehouseModel = new WarehouseModel();
            $warehouse = $warehouseModel->where('WarehouseID', '=', $warehouseID)->first();
            if (!$warehouse) {
                throw new \Exception("Warehouse not found");
            }

            // Получаем транзакции для данного склада
            $stockModel = new StockTransactionsModel();
            // Вместо Products.ProductName – делаем LEFT JOIN на ProductTranslations
            $transactions = $stockModel->table()
                ->join('Products', 'Products.ProductID', '=', 'StockTransactions.ProductID')
                ->leftJoin('ProductTranslations as t', function($join) use ($language) {
                    $join->on('t.ProductID', '=', 'Products.ProductID')
                        ->where('t.LanguageCode', '=', $language);
                })
                ->where('StockTransactions.WarehouseID', '=', $warehouseID)
                ->select([
                    'Products.ProductID',
                    // Заменяем поле ProductName на COALESCE(t.Name, 'Unknown')
                    DB::raw("COALESCE(t.Name, 'Unknown') AS ProductName"),
                    'StockTransactions.StockTransactionType',
                    'StockTransactions.Quantity',
                    'StockTransactions.Price',
                    'StockTransactions.StockTransactionDate'
                ])
                ->orderBy('StockTransactions.StockTransactionDate', 'desc')
                ->get();

            // Инициализация массива для обработки данных
            $stockSummary = [];
            foreach ($transactions as $transaction) {
                $productId = $transaction->ProductID;

                if (!isset($stockSummary[$productId])) {
                    $stockSummary[$productId] = [
                        'ProductID'        => $transaction->ProductID,
                        'ProductName'      => $transaction->ProductName,  // уже с учётом перевода
                        'TotalQuantity'    => 0, // Общий остаток
                        'FrozenQuantity'   => 0, // Заморожено
                        'AvailableQuantity'=> 0, // Доступное количество
                        'AveragePrice'     => 0, // Средняя цена за последние 3 прихода
                        'RecentPrices'     => [] // Хранилище цен для последних 3 транзакций прихода
                    ];
                }

                // Учет типов транзакций
                if ($transaction->StockTransactionType == 1) {       // Приход
                    $stockSummary[$productId]['TotalQuantity']     += $transaction->Quantity;
                    $stockSummary[$productId]['AvailableQuantity'] += $transaction->Quantity;
                    if (count($stockSummary[$productId]['RecentPrices']) < 3) {
                        $stockSummary[$productId]['RecentPrices'][] = $transaction->Price;
                    }
                } elseif ($transaction->StockTransactionType == 2) { // Списание
                    $stockSummary[$productId]['TotalQuantity']     -= abs($transaction->Quantity);
                    $stockSummary[$productId]['AvailableQuantity'] -= abs($transaction->Quantity);
                } elseif ($transaction->StockTransactionType == 3) { // Заморозка
                    $stockSummary[$productId]['FrozenQuantity']    += abs($transaction->Quantity);
                    $stockSummary[$productId]['AvailableQuantity'] -= abs($transaction->Quantity);
                }
            }

            // Вычисляем среднюю цену, убираем товары с нулевым количеством
            $filteredStockSummary = [];
            foreach ($stockSummary as $product) {
                // Пропускаем товары с нулевым общим количеством
                if ($product['TotalQuantity'] == 0) {
                    continue;
                }

                $recentPrices = $product['RecentPrices'];
                $product['AveragePrice'] = count($recentPrices) > 0
                    ? array_sum($recentPrices) / count($recentPrices)
                    : 0;

                unset($product['RecentPrices']); // Убираем вспомогательные данные
                $filteredStockSummary[] = $product; // Добавляем только товары с ненулевым количеством
            }

            // Формируем успешный ответ
            self::setData(
                result: [
                    'warehouseName' => $warehouse->WarehouseName,
                    'stock'         => $filteredStockSummary,
                ],
                status: 'success'
            );
        } catch (\Exception $e) {
            // Обработка ошибок
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }




    public function getWarehouseTransactions(): void
    {
        try {
            // Получение параметров из запроса
            $warehouseID = Input::json('warehouseID') ?? null;
            $startDate   = Input::json('startDate')   ?? null;
            $endDate     = Input::json('endDate')     ?? null;

            // Получаем язык (по умолчанию 'ru')
            $language = Input::json('language') ?? 'ru';

            if (!$warehouseID) {
                self::setData(result: ['message' => 'Warehouse ID is required.'], statusCode: 400, status: 'error');
                return;
            }

            $stockModel = new StockTransactionsModel();

            // Основной запрос
            // Вместо Products.ProductName берём перевод из ProductTranslations
            $transactions = $stockModel->table()
                ->join('Products', 'StockTransactions.ProductID', '=', 'Products.ProductID')
                ->leftJoin('ProductTranslations as t', function ($join) use ($language) {
                    $join->on('t.ProductID', '=', 'Products.ProductID')
                        ->where('t.LanguageCode', '=', $language);
                })
                ->leftJoin('Partner', 'StockTransactions.InOutID', '=', 'Partner.PartnerID')
                ->leftJoin('FinTransactions', 'StockTransactions.StockTransactionID', '=', 'FinTransactions.TransactionID')
                ->leftJoin('Accounts', 'FinTransactions.AccountID', '=', 'Accounts.AccountID')
                ->selectRaw("
                StockTransactions.StockTransactionDate,
                StockTransactions.StockTransactionType,
                -- Вместо Products.ProductName:
                COALESCE(t.Name, 'Unknown') as ProductName,
                Products.ProductID,
                StockTransactions.Quantity,
                StockTransactions.Price,
                StockTransactions.Weight,
                (StockTransactions.Quantity * StockTransactions.Price) as TotalAmount,

                Partner.ShortName as PartnerName,
                Partner.PartnerID as PartnerId,

                Accounts.Bank as BankAccount,
                Accounts.AccountID as BankId,
                Accounts.Description as BankDescription,
                Accounts.Currency as Currency,

                StockTransactions.InOutID
            ")
                ->where('StockTransactions.WarehouseID', '=', $warehouseID);

            // Фильтр по дате
            if ($startDate) {
                $transactions->where('StockTransactions.StockTransactionDate', '>=', $startDate);
            }
            if ($endDate) {
                $transactions->where('StockTransactions.StockTransactionDate', '<=', $endDate);
            }

            // Сортируем и загружаем
            $transactions = $transactions
                ->orderBy('StockTransactions.StockTransactionDate', 'desc')
                ->get();

            // Обрабатываем записи без партнёра
            foreach ($transactions as &$transaction) {
                if (is_null($transaction->PartnerName)) {
                    $orderUUID = DB::table('OrderItems')
                        ->where('ItemPermID', '=', $transaction->InOutID)
                        ->value('OrderUUID');

                    if ($orderUUID) {
                        $orderName = DB::table('Orders')
                            ->where('OrderUUID', '=', $orderUUID)
                            ->value('OrderName');

                        $transaction->PartnerId   = $orderUUID;
                        $transaction->PartnerName = $orderName;
                    }
                }
            }

            // Возвращаем данные
            self::setData(result: ['transaction' => $transactions], status: 'success');

        } catch (\Exception $e) {
            // Обработка ошибок
            self::setData(
                result: ['message' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }



    public function WarehouseTransfer(): void
    {
        try {
            // Проверка авторизации
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                self::setData(result: ['error' => 'Unauthorized'], statusCode: 401, status: 'error');
            }

            $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // Получение данных из запроса
            $data = Input::json();
            $fromWarehouseID = $data['fromWarehouseID'] ?? null;
            $toWarehouseID = $data['toWarehouseID'] ?? null;
            $productID = $data['productID'] ?? null;
            $selectedStocks = $data['selectedSupplies'] ?? [];
            $notes = $data['notes'] ?? null;
            $initiatorID = $data['initiatorID'] ?? null;

            // Проверка обязательных параметров
            if (!$fromWarehouseID || !$toWarehouseID || !$productID || empty($selectedStocks)) {
                self::setData(result: ['error' => 'All parameters (fromWarehouseID, toWarehouseID, productID, selectedSupplies) are required.'], statusCode: 400, status: 'error');
            }

            // Рассчитываем общее перемещаемое количество
            $totalQuantity = array_reduce($selectedStocks, function ($carry, $item) {
                return $carry + ($item['takenQuantity'] ?? 0);
            }, 0);

            if ($totalQuantity <= 0) {
                self::setData(result: ['error' => 'Invalid total quantity for transfer.'], statusCode: 400, status: 'error');
            }

            // Проверка доступного количества на исходном складе
            $stockTransactionsModel = new StockTransactionsModel();
            $availableQuantity = $stockTransactionsModel->table()
                ->where('WarehouseID', $fromWarehouseID)
                ->where('ProductID', $productID)
                ->sum(DB::raw('CASE 
                WHEN StockTransactionType = 1 THEN Quantity 
                WHEN StockTransactionType = 2 THEN -Quantity 
                WHEN StockTransactionType = 3 THEN -Quantity 
                ELSE 0 
            END'));

            if ($totalQuantity > $availableQuantity) {
                self::setData(result: ['error' => 'Insufficient quantity available in the source warehouse.'], statusCode: 400, status: 'error');
            }

            // Создаем ID перемещения
            $movementID = Str::uuid();

            DB::beginTransaction();

            // Логируем перемещение
            $stockMovementModel = new StockMovementHistoryModel();
            $stockMovementModel->table()->insert([
                'MovementID' => $movementID,
                'ProductID' => $productID,
                'WarehouseID' => $fromWarehouseID,
                'MovementDate' => Carbon::now(),
                'InitiatorID' => $initiatorID,
                'SourceWarehouseID' => $fromWarehouseID,
                'DestinationWarehouseID' => $toWarehouseID,
                'Notes' => $notes,
                'CreatedAt' => Carbon::now(),
            ]);

            // Обрабатываем каждую поставку
            foreach ($selectedStocks as $stock) {
                $stockTransactionID = $stock['stockTransactionID'];
                $takenQuantity = $stock['takenQuantity'];
                $price = $stock['price'];

                if ($takenQuantity <= 0) {
                    continue;
                }

                // Получаем вес из оригинальной поставки
                $originalStock = $stockTransactionsModel->table()
                    ->where('StockTransactionID', $stockTransactionID)
                    ->first();

                $weight = $originalStock->Weight ?? null;

                // Уменьшаем количество на исходном складе (Списание)
                $stockTransactionsModel->table()->insert([
                    'StockTransactionID' => DB::raw('NEWID()'),
                    'StockTransactionDate' => Carbon::now(),
                    'StockTransactionType' => 2, // Списание
                    'ProductID' => $productID,
                    'WarehouseID' => $fromWarehouseID,
                    'StockID' => $stockTransactionID, // Откуда списываем
                    'Quantity' => -$takenQuantity,
                    'Weight' => $weight,
                    'Price' => $price,
                    'InOutID' => $movementID, // ID перемещения
                    'Movies' => 1,
                ]);

                // Увеличиваем количество на целевом складе (Приход)
                $stockTransactionsModel->table()->insert([
                    'StockTransactionID' => DB::raw('NEWID()'),
                    'StockTransactionDate' => Carbon::now(),
                    'StockTransactionType' => 1, // Приход
                    'ProductID' => $productID,
                    'WarehouseID' => $toWarehouseID,
                    'StockID' => $stockTransactionID, // Сохранение ID поставки
                    'Quantity' => $takenQuantity,
                    'Weight' => $weight,
                    'Price' => $price,
                    'InOutID' => $movementID, // ID перемещения
                    'Movies' => 1,
                ]);
            }

            DB::commit();

            self::setData(result: ['message' => 'Transfer completed successfully.'], status: 'success');
        } catch (\Exception $e) {
            DB::rollBack();
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }



    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/stock/recent-deliveries')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    public function getRecentDeliveries(): void
    {
        try {
            // 1) Считываем параметры
            $limit    = Input::json('limit', 10);               // По умолчанию 10
            // Добавим язык для вывода названия продукта
            $language = Input::json('language') ?? 'ru';

            // Получаем активные склады
            $warehouseModel = new WarehouseModel();
            $warehouses = $warehouseModel->table()
                ->whereNull('deleted_at')
                ->where('Status', '=', 1)
                ->pluck('WarehouseID');

            if ($warehouses->isEmpty()) {
                self::api(
                    [
                        'stock' => [],
                        'message' => 'No active warehouses found for the given company.'
                    ],
                    status: 'error'
                );
            }

            // 2) Получаем последние поставки
            $stockModel = new StockTransactionsModel();
            $deliveries = $stockModel->table()
                ->whereIn('WarehouseID', $warehouses)
                ->where('StockTransactionType', '=', 1)  // 1 = только поставки
                ->select([
                    'StockTransactionID',
                    'StockTransactionDate',
                    'ProductID',
                    'WarehouseID',
                    'Quantity',
                    'Weight',
                    'Price',
                ])
                ->orderBy('StockTransactionDate', 'desc')
                ->limit($limit)
                ->get();

            if ($deliveries->isEmpty()) {
                self::api(
                    [
                        'stock' => [],
                        'message' => 'No recent deliveries found.'
                    ],
                    status: 'error'
                );
            }

            // 3) Добавляем информацию о продуктах и складах
            $response = $deliveries->map(function ($delivery) use ($warehouseModel, $language) {
                // Получаем название продукта из переводов
                $materialName = DB::table('ProductTranslations')
                    ->where('ProductID', $delivery->ProductID)
                    ->where('LanguageCode', $language)
                    ->value('Name') ?? 'Unknown';

                // Получаем название склада
                $warehouse = $warehouseModel->table()
                    ->where('WarehouseID', '=', $delivery->WarehouseID)
                    ->first();

                return [
                    'StockTransactionID' => $delivery->StockTransactionID,
                    'TransactionDate'    => $delivery->StockTransactionDate,
                    'ProductID'          => $delivery->ProductID,
                    // Вместо product->ProductName используем $materialName
                    'ProductName'        => $materialName,
                    'WarehouseID'        => $delivery->WarehouseID,
                    'WarehouseName'      => $warehouse->WarehouseName ?? 'Unknown',
                    'Quantity'           => $delivery->Quantity,
                    'Weight'             => $delivery->Weight,
                    'Price'              => $delivery->Price,
                ];
            });

            // 4) Возвращаем результат
            self::api(
                ['stock' => $response]
            );

        } catch (\Exception $e) {
            // Обработка ошибок
            self::api(
                ['error' => $e->getMessage()],
                500,
                'error'
            );
        }
    }



    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/warehouse/item-materials')]
    #[Authorize(guard: 'jwt', permission: ['admin'])] // поправь роли под себя
    #[Validate([
        'orderUUID'     => ['required' => true, 'type' => 'uuid'],
        'ItemPermID'    => ['required' => true, 'type' => 'uuid'],
        'ProductID'     => ['required' => false, 'type' => 'uuid'],
        'materials'     => ['required' => true, 'type' => 'array'],
        'language' => ['required' => true, 'equals' => 2],
    ])]
    public static function getMaterialsForOrderItem(ValidatedRequest $request): void
    {
        try {
            $request->check();

            $orderUUID     = $request->input('orderUUID');
            $ItemPermID    = $request->input('ItemPermID');
            $productHTTPID = $request->input('ProductID');
            $items         = $request->input('materials');
            $language      = $request->input('language', 'ru');

            // Заказ
            $order = DB::table('orders')->where('OrderUUID', $orderUUID)->first();
            if (!$order) {
                throw new \Exception('Order not found.');
            }

            // Склады исполнителя
            $warehouses = DB::table('Warehouse')
                ->whereNull('deleted_at')
                ->pluck('WarehouseID')
                ->toArray();

            if (empty($warehouses)) {
                throw new \Exception('No warehouses found for the executor company.');
            }

            $response = [];

            foreach ($items as $item) {
                $productID = $item['productID'] ?? null;
                $requiredQuantity = $item['quantity'] ?? 0;

                if (!$productID || $requiredQuantity <= 0) {
                    throw new \Exception('Invalid ProductID or quantity in materials.');
                }

                $transactions = DB::table('StockTransactions as t')
                    ->join('Warehouse as w', 't.WarehouseID', '=', 'w.WarehouseID')
                    ->whereIn('t.WarehouseID', $warehouses)
                    ->where('t.StockTransactionType', 1)
                    ->where('t.ProductID', $productID)
                    ->select([
                        't.StockTransactionID',
                        't.WarehouseID',
                        'w.WarehouseName',
                        't.StockTransactionDate',
                        't.Price',
                        't.Quantity'
                    ])
                    ->orderBy('t.StockTransactionDate', 'asc')
                    ->get();

                $stockUsages = DB::table('StockTransactions')
                    ->whereIn('WarehouseID', $warehouses)
                    ->where('ProductID', $productID)
                    ->whereIn('StockTransactionType', [2, 3])
                    ->whereNotNull('StockID')
                    ->select('StockID', 'StockTransactionType', DB::raw('SUM(Quantity) as totalUsed'))
                    ->groupBy('StockID', 'StockTransactionType')
                    ->get();

                $reservedQuantities = [];
                $takenQuantities = [];

                foreach ($stockUsages as $usage) {
                    $used = abs($usage->totalUsed);
                    if ($usage->StockTransactionType === 3) {
                        $reservedQuantities[$usage->StockID] = $used;
                    } elseif ($usage->StockTransactionType === 2) {
                        $takenQuantities[$usage->StockID] = $used;
                    }
                }

                $availableStocks = [];
                $remainingQuantity = $requiredQuantity;
                $totalFactuallyCost = 0;
                $factuallyTaken = 0;
                $totalPlannedCost = 0;

                foreach ($transactions as $transaction) {
                    $stockID = $transaction->StockTransactionID;
                    $reserved = $reservedQuantities[$stockID] ?? 0;
                    $taken = $takenQuantities[$stockID] ?? 0;

                    $availableQuantity = max($transaction->Quantity - $reserved - $taken, 0);
                    $takenQuantity = min($remainingQuantity, $availableQuantity);

                    if ($availableQuantity > 0 && $remainingQuantity > 0) {
                        $availableStocks[] = [
                            'stockTransactionID' => $stockID,
                            'warehouseID' => $transaction->WarehouseID,
                            'WarehouseName' => $transaction->WarehouseName,
                            'stockDate' => $transaction->StockTransactionDate,
                            'price' => $transaction->Price,
                            'availableQuantity' => $availableQuantity,
                            'takenQuantity' => $takenQuantity,
                            'totalCost' => $takenQuantity * $transaction->Price,
                        ];
                        $remainingQuantity -= $takenQuantity;
                    }

                    $factuallyTaken += $taken;
                    $totalFactuallyCost += $taken * $transaction->Price;
                    $totalPlannedCost += $transaction->Price * $requiredQuantity;
                }

                $response[] = [
                    'productID' => $productID,
                    'requiredQuantity' => $requiredQuantity,
                    'plannedCost' => round($totalPlannedCost, 2),
                    'factuallyCost' => round($totalFactuallyCost, 2),
                    'factuallyTaken' => $factuallyTaken,
                    'reservedQuantity' => array_sum($reservedQuantities),
                    'availableStocks' => $availableStocks,
                ];
            }

            $stockTransactions = DB::table('StockTransactions as st')
                ->join('Warehouse as w', 'st.WarehouseID', '=', 'w.WarehouseID')
                ->join('Products as p', 'st.ProductID', '=', 'p.ProductID')
                ->leftJoin('ProductTranslations as t', function ($join) use ($language) {
                    $join->on('t.ProductID', '=', 'p.ProductID')
                        ->where('t.LanguageCode', '=', $language);
                })
                ->where('st.InOutID', $ItemPermID)
                ->select([
                    'st.StockTransactionID',
                    'st.StockTransactionDate',
                    'st.StockTransactionType',
                    DB::raw("COALESCE(t.Name, 'Unknown') AS ProductName"),
                    'w.WarehouseName',
                    DB::raw('ABS(st.Quantity) as Quantity'),
                    DB::raw('ROUND(st.Price, 2) as Price')
                ]);

            if ($productHTTPID) {
                $stockTransactions->where('st.ProductID', $productHTTPID);
            }

            $transactionResults = $stockTransactions->get();

            self::api([
                'items' => $response,
                'transaction' => $transactionResults
            ]);

        } catch (\Throwable $e) {
            self::api(['error' => $e->getMessage()], 500, 'error');
        }
    }



    /**
     * Получение всех складских транзакций по конкретному изделию в заказе.
     * Доступно только для пользователей с правами `admin`.
     *
     * @param ValidatedRequest $request
     * @return void
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/warehouse/item-trans-materials')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    #[Validate([
        'orderUUID'   => ['required' => true, 'type' => 'uuid'],
        'ItemPermID'  => ['required' => true, 'type' => 'uuid'],
        'language' => ['required' => true, 'equals' => 2],
    ])]
    public static function getItemAllTransactions(ValidatedRequest $request): void
    {
        try {
            $request->check();

            $orderUUID  = $request->input('orderUUID');
            $ItemPermID = $request->input('ItemPermID');
            $language = $request->input('language');

            // Получаем данные заказа
            $order = DB::table('Orders')->where('OrderUUID', $orderUUID)->first();
            if (!$order) {
                throw new \Exception('Order not found.');
            }



            // Получаем ID складов, принадлежащих исполнителю
            $warehouses = DB::table('Warehouse')
                ->whereNull('deleted_at')
                ->pluck('WarehouseID')
                ->toArray();

            if (empty($warehouses)) {
                throw new \Exception('No warehouses found for the executor company.');
            }

            // Получаем все транзакции по изделию


            $stockTransactions = DB::table('StockTransactions as st')
                ->join('Warehouse as w', 'st.WarehouseID', '=', 'w.WarehouseID')
                ->join('ProductTranslations as pt', function ($join) use ($language) {
                    $join->on('st.ProductID', '=', 'pt.ProductID')
                        ->where('pt.LanguageCode', '=', $language);
                })
                ->where('st.InOutID', $ItemPermID)
                ->whereIn('st.WarehouseID', $warehouses)
                ->select([
                    'st.StockTransactionID',
                    'st.StockTransactionDate',
                    'st.StockTransactionType',
                    'pt.Name as ProductName',
                    'w.WarehouseName',
                    DB::raw('ABS(st.Quantity) as Quantity'),
                    DB::raw('ROUND(st.Price, 2) as Price'),
                ])
                ->get();

            // Ответ
            self::api([
                'transaction' => $stockTransactions
            ]);
        } catch (\Throwable $e) {
            self::api(
                ['error' => $e->getMessage()],
                500,
                'error'
            );
        }
    }





    public function deleteStockTransaction(): void
    {
        try {
            // Проверяем авторизацию
            if (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                throw new \Exception('Authorization failed');
            }

            $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1] ?? null;
            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }

            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // Получаем ID транзакции из запроса
            $transactionID = Input::json('transactionID');
            if (!$transactionID) {
                throw new \Exception('Transaction ID is required.');
            }

            // Проверяем существование транзакции
            $transaction = DB::table('StockTransactions')
                ->where('StockTransactionID', $transactionID)
                ->first();

            if (!$transaction) {
                throw new \Exception('Transaction not found.');
            }

            // Удаляем транзакцию
            DB::table('StockTransactions')
                ->where('StockTransactionID', $transactionID)
                ->delete();

            // Возвращаем успешный ответ
            self::setData(
                result: ['message' => 'Transaction deleted successfully.'],
                status: 'success'
            );
        } catch (\Exception $e) {
            // Обработка ошибки
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }










    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/warehouse/save-selected-supplies')]
    #[Authorize(guard: 'jwt', permission: ['admin', 'manager'])] // подставь свои роли
    #[Validate([
        'materialID'        => ['required' => true, 'type' => 'uuid'],
        'ItemPermID'        => ['required' => true, 'type' => 'uuid'],
        'selectedSupplies'  => ['required' => true, 'type' => 'array'],
    ])]
    public static function saveSelectedSupplies(ValidatedRequest $request): void
    {
        try {
            $request->check();

            $materialID       = $request->input('materialID');
            $ItemPermID       = $request->input('ItemPermID');
            $selectedSupplies = $request->input('selectedSupplies');

            if (empty($selectedSupplies)) {
                throw new \Exception("selectedSupplies array is empty.");
            }

            DB::beginTransaction();

            foreach ($selectedSupplies as $supply) {
                $stockTransactionID = $supply['stockTransactionID'] ?? null;
                $takenQuantity      = $supply['takenQuantity'] ?? 0;

                if (!$stockTransactionID || $takenQuantity <= 0) {
                    throw new \Exception("Invalid supply data provided.");
                }

                // Проверяем транзакцию поступления
                $transaction = DB::table('StockTransactions')
                    ->where('StockTransactionID', $stockTransactionID)
                    ->where('ProductID', $materialID)
                    ->where('StockTransactionType', 1)
                    ->first();

                if (!$transaction) {
                    throw new \Exception("StockTransaction with ID {$stockTransactionID} not found.");
                }

                if ($transaction->Quantity < $takenQuantity) {
                    throw new \Exception("Not enough quantity in StockTransaction {$stockTransactionID}.");
                }

                // Вставляем списание
                DB::table('StockTransactions')->insert([
                    'StockTransactionID'   => Str::uuid(),
                    'StockTransactionDate' => Carbon::now(),
                    'StockTransactionType' => 3,
                    'ProductID'            => $materialID,
                    'WarehouseID'          => $transaction->WarehouseID,
                    'Quantity'             => -$takenQuantity,
                    'Price'                => $transaction->Price,
                    'StockID'              => $transaction->StockTransactionID,
                    'InOutID'              => $ItemPermID,
                    'Movies'               => 0,
                ]);
            }

            DB::commit();

            self::api([
                'message' => 'Supplies saved successfully'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            self::api(['error' => $e->getMessage()], 500, 'error');
        }
    }



}