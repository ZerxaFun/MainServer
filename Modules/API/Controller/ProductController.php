<?php

namespace Modules\API\Controller;

use Carbon\Carbon;
use Core\Routing\Attributes\HttpMethod;
use Core\Services\Attributes\Validate;
use Core\Services\Auth\Attributes\Authorize;
use Core\Services\Http\Input;
use Controller;
use Core\Services\Http\ValidatedRequest;
use DateTime;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use Modules\API\Model\FinTransactionsModel;
use Modules\API\Model\ProductModel;
use Modules\API\Model\ProductTranslationsModel;
use Modules\API\Model\StockTransactionsModel;
use Modules\API\Model\WarehouseModel;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Illuminate\Database\Capsule\Manager as DB;
/**
 * ProductController - контроллер для управления данными продуктами.
 */
class ProductController extends Controller
{
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function translateProduct(): void
    {
        try {
            if (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                throw new \Exception('auth failed');
            }

            $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            $name = Input::json('productName');
            $description = strip_tags(Input::json('description'));
            $sourceLang = Input::json('sourceLang') ?? 'ru';

            if (empty($name)) {
                throw new \Exception('Название обязательно.');
            }

            $tr = new GoogleTranslate();
            $translations = [];

            if ($sourceLang === 'ka') {
                // Если исходный язык грузинский, переводим на русский и английский
                $translations['ka'] = ['name' => $name, 'description' => $description];
                $translations['ru'] = [
                    'name' => $tr->setSource('ka')->setTarget('ru')->translate($name),
                    'description' => $tr->setSource('ka')->setTarget('ru')->translate($description),
                ];
                $translations['en'] = [
                    'name' => $tr->setSource('ka')->setTarget('en')->translate($name),
                    'description' => $tr->setSource('ka')->setTarget('en')->translate($description),
                ];
            } else {
                // Если язык системы НЕ грузинский (ru/en), переводим на грузинский и английский
                $translations[$sourceLang] = ['name' => $name, 'description' => $description];

                if ($sourceLang !== 'ru') {
                    $translations['ru'] = [
                        'name' => $tr->setSource($sourceLang)->setTarget('ru')->translate($name),
                        'description' => $tr->setSource($sourceLang)->setTarget('ru')->translate($description),
                    ];
                } else {
                    $translations['ru'] = ['name' => $name, 'description' => $description];
                }

                $translations['en'] = [
                    'name' => $tr->setSource($sourceLang)->setTarget('en')->translate($name),
                    'description' => $tr->setSource($sourceLang)->setTarget('en')->translate($description),
                ];
                $translations['ka'] = [
                    'name' => $tr->setSource($sourceLang)->setTarget('ka')->translate($name),
                    'description' => $tr->setSource($sourceLang)->setTarget('ka')->translate($description),
                ];
            }

            self::setData(result: ['translations' => $translations], status: 'success');

        } catch (\Exception $e) {
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }

    /**
     * Метод добавления продукта в справочник
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function createProduct(): void
    {
        try {
            // 1) Проверка заголовка авторизации
            if (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                self::setData(
                    result: ['error' => 'auth failed'],
                    statusCode: 401,
                    status: 'error'
                );
            }

            // 2) Декодирование JWT
            $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
            $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // 3) Проверяем, что есть входные данные
            if (!Input::json()) {
                throw new \Exception('Invalid input data');
            }

            $productID = (string) Str::uuid();
            // 4) Считываем базовые поля (которые реально есть в Products)
            $preparedData = [
                'ProductID'    => $productID,
                'Unit'        => Input::json('unit') ?? null,
                'ProductType' => Input::json('productType') ?? null,
                'Status'      => Input::json('status') ?? null,
            ];

            // Можно делать проверку полей, если что-то из них обязательно
            // if (empty($preparedData['ProductType'])) {
            //    throw new \Exception('ProductType is required');
            // }

            // 5) Создаём запись в Products
            $productModel = new ProductModel();
            $productModel->fill($preparedData);
            $productModel->save();

            // 6) Получаем актуальные данные из БД (теперь productModel точно имеет ProductID)


            // 7) Обрабатываем переводы (translations)
            $translations = Input::json('translations') ?? [];
            if (!empty($translations) && is_array($translations)) {
                $now = date('Y-m-d H:i:s');
                foreach ($translations as $langCode => $langData) {
                    $name        = $langData['name']        ?? null;
                    $description = $langData['description'] ?? null;

                    // Пропустить, если ни названия, ни описания нет
                    if (!$name && !$description) {
                        continue;
                    }

                    $translationModel = new ProductTranslationsModel();
                    $translationModel->fill([
                        'ProductID'    => $productModel->ProductID, // GUID или int, в зависимости от вашей схемы
                        'LanguageCode' => $langCode,
                        'Name'         => $name,
                        'Description'  => $description,
                        'CreatedAt'    => $now,
                        'UpdatedAt'    => $now,
                    ]);
                    $translationModel->save();
                }
            }

            // 8) Возвращаем успешный ответ
            self::setData(
                result: ['product_uuid' => $productModel->ProductID],
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



    /**
     * Получение списка продуктов с учетом остатков
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function getProductsList(): void
    {
        try {
            // Проверка авторизации
            if (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                self::setData(
                    result: ['products' => [], 'error' => 'auth failed'],
                    statusCode: 401,
                    status: 'error'
                );
                return;
            }

            $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1] ?? null;
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // 1) Получаем язык из запроса (по умолчанию 'ru')
            $language = Input::json('language') ?? 'ru';

            $stockModel = new StockTransactionsModel();
            $warehouseModel = new WarehouseModel();

            // 2) Берём продукты из таблицы `Products`, делаем LEFT JOIN на `ProductTranslations`
            $products = DB::table('Products')
                ->leftJoin('ProductTranslations AS t', function ($join) use ($language) {
                    $join->on('t.ProductID', '=', 'Products.ProductID')
                        ->where('t.LanguageCode', '=', $language);
                })
                ->select(
                    'Products.ProductID',
                    // Если хотите вернуть ещё поля из Products (Status, ProductType, Unit и т.д.), добавьте их
                    'Products.ProductType',
                    'Products.Status',
                    'Products.Unit',
                    // Переведённые поля (Name, Description).
                    DB::raw("COALESCE(t.Name, 'Unknown') AS ProductName"),
                    DB::raw("COALESCE(t.Description, '') AS ProductDescription")
                )
                ->whereNull('Products.deleted_at')
                ->get();

            // Собираем все ID продуктов
            $productIDs = $products->pluck('ProductID');

            // 3) Считаем общий остаток и резерв по каждому продукту
            //    (логика осталась та же, только products у нас теперь из JOIN’а)
            $totalQuantities = $stockModel->table()
                ->whereIn('ProductID', $productIDs)
                ->selectRaw("
                ProductID,
                SUM(
                    CASE 
                        WHEN StockTransactionType = 1 THEN Quantity             -- Приход
                        WHEN StockTransactionType = 2 THEN -ABS(Quantity)       -- Списание
                        ELSE 0
                    END
                ) as TotalQuantity,
                SUM(
                    CASE 
                        WHEN StockTransactionType = 3 THEN ABS(Quantity)       -- Резерв
                        ELSE 0
                    END
                ) as ReservedQuantity
            ")
                ->groupBy('ProductID')
                ->get()
                ->keyBy('ProductID');

            // 4) Остаток по складам
            $warehouseQuantities = $stockModel->table()
                ->whereIn('ProductID', $productIDs)
                ->groupBy('ProductID', 'WarehouseID')
                ->selectRaw("
                ProductID,
                WarehouseID,
                SUM(
                    CASE 
                        WHEN StockTransactionType = 1 THEN Quantity          
                        WHEN StockTransactionType = 2 THEN -ABS(Quantity)   
                        ELSE 0
                    END
                ) as Quantity,
                SUM(
                    CASE 
                        WHEN StockTransactionType = 3 THEN -ABS(Quantity)
                        ELSE 0
                    END
                ) as ReservedQuantity
            ")
                ->get()
                ->groupBy('ProductID');

            // 5) Собираем всё в один массив
            $productsWithStockInfo = $products->map(function ($product) use ($totalQuantities, $warehouseQuantities, $warehouseModel) {
                $productID = $product->ProductID;

                // Общий остаток и резерв
                $product->totalQuantity    = $totalQuantities[$productID]->TotalQuantity    ?? 0;
                $product->reservedQuantity = $totalQuantities[$productID]->ReservedQuantity ?? 0;

                // Остатки по конкретным складам
                $warehouses = $warehouseQuantities[$productID] ?? collect();
                $product->warehouseQuantities = $warehouses->map(function ($stock) use ($warehouseModel) {
                    $warehouse = $warehouseModel->table()
                        ->where('WarehouseID', '=', $stock->WarehouseID)
                        ->first();

                    return [
                        'WarehouseID'       => $stock->WarehouseID,
                        'WarehouseName'     => $warehouse->WarehouseName ?? 'Unknown',
                        'Quantity'          => $stock->Quantity,
                        'ReservedQuantity'  => $stock->ReservedQuantity,
                    ];
                });

                return $product;
            });

            // 6) Возвращаем результат
            self::setData(
                result: ['products' => $productsWithStockInfo],
                status: 'success'
            );
        } catch (\Exception $e) {
            self::setData(
                result: ['products' => [], 'error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }





    /**
     * Получение списка продуктов, короткий вариант
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function getProductsListShort(): void
    {
        try {
            // Проверка наличия заголовка авторизации
            if (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                self::setData(
                    result: ['products' => [], 'error' => 'auth failed'],
                    statusCode: 401,
                    status: 'error'
                );
                return;
            }

            $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1] ?? null;
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // 1) Получаем язык из тела запроса (по умолчанию 'ru')
            $language = Input::json('language') ?? 'ru';

            // 2) Вместо использования ProductModel с полями ProductName/ProductDescription
            //    делаем JOIN с таблицей переводов
            $productsList = DB::table('Products')
                ->leftJoin('ProductTranslations AS t', function($join) use ($language) {
                    $join->on('t.ProductID', '=', 'Products.ProductID')
                        ->where('t.LanguageCode', '=', $language);
                })
                ->select(
                    'Products.ProductID',
                    DB::raw("COALESCE(t.Name, 'Unknown') AS ProductName"),         // Если перевода нет, ставим "Unknown"
                    DB::raw("COALESCE(t.Description, '') AS ProductDescription")  // Если перевода нет, пустая строка
                )
                ->whereNull('Products.deleted_at')
                ->where('Products.ProductType', 1) // Сырье
                ->where('Products.Status', 1)
                ->get();

            // Возвращаем результат
            self::setData(
                result: ['products' => $productsList],
                status: 'success'
            );

        } catch (\Exception $e) {
            // Ответ при ошибке
            self::setData(
                result: ['products' => [], 'error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }

    /**
     * Получение деталей продукта по ID
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function getProductDetails(): void
    {
        try {
            // 1) Проверка авторизации
            if (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                throw new \Exception('auth failed');
            }
            $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1] ?? null;
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // 2) Получение параметров из запроса
            $productID = Input::json('productID');
            if (empty($productID)) {
                throw new \Exception('ProductID is required');
            }

            // Считаем язык (по умолчанию 'ru')
            $language = Input::json('language') ?? 'ru';

            $stockModel = new StockTransactionsModel();
            $warehouseModel = new WarehouseModel();

            // 3) Вместо "productModel->table()" берём DB::table('Products') и делаем LEFT JOIN на ProductTranslations:
            $product = DB::table('Products')
                ->leftJoin('ProductTranslations AS t', function($join) use ($language) {
                    $join->on('t.ProductID', '=', 'Products.ProductID')
                        ->where('t.LanguageCode', '=', $language);
                })
                ->select(
                    'Products.ProductID',
                    // Если вам нужны поля Unit, ProductType, Status из Products, берите их:
                    'Products.Unit',
                    'Products.ProductType',
                    'Products.Status',
                    // Перевод: если нет – подставляем 'Unknown' или пустую строку
                    DB::raw("COALESCE(t.Name, 'Unknown') AS ProductName"),
                    DB::raw("COALESCE(t.Description, '') AS ProductDescription")
                )
                ->where('Products.ProductID', '=', $productID)
                ->whereNull('Products.deleted_at')
                ->first();

            if (!$product) {
                throw new \Exception('Product not found');
            }

            // 4) Общий остаток и резерв
            $stockData = $stockModel->table()
                ->where('ProductID', '=', $productID)
                ->selectRaw("
                SUM(
                    CASE
                        WHEN StockTransactionType = '1' THEN Quantity
                        WHEN StockTransactionType IN ('2', '3') THEN Quantity
                        ELSE 0
                    END
                ) as TotalQuantity,
                SUM(
                    CASE
                        WHEN StockTransactionType = '3' THEN Quantity
                        ELSE 0
                    END
                ) as TotalReservedQuantity
            ")
                ->first();

            $totalQuantity        = $stockData->TotalQuantity ?? 0;
            $totalReservedQuantity = $stockData->TotalReservedQuantity ?? 0;

            // 5) Остаток и резерв по складам
            $warehouseStocks = $stockModel->table()
                ->where('ProductID', '=', $productID)
                ->groupBy('WarehouseID')
                ->selectRaw("
                WarehouseID,
                SUM(
                    CASE
                        WHEN StockTransactionType = '1' THEN Quantity
                        WHEN StockTransactionType IN ('2', '3') THEN Quantity
                        ELSE 0
                    END
                ) as Quantity,
                SUM(
                    CASE
                        WHEN StockTransactionType = '3' THEN Quantity
                        ELSE 0
                    END
                ) as ReservedQuantity,
                SUM(
                    CASE
                        WHEN StockTransactionType = '1' THEN Weight
                        WHEN StockTransactionType IN ('2', '3') THEN Weight
                        ELSE 0
                    END
                ) as TotalWeight
            ")
                ->havingRaw("
                SUM(CASE WHEN StockTransactionType IN ('1','2','3') THEN Quantity ELSE 0 END) <> 0
                OR
                SUM(CASE WHEN StockTransactionType = '3' THEN Quantity ELSE 0 END) <> 0
            ")
                ->get();

            // 6) Формируем данные по складам
            $warehouseDetails = $warehouseStocks->map(function ($stock) use ($warehouseModel) {
                $warehouse = $warehouseModel->table()
                    ->where('WarehouseID', '=', $stock->WarehouseID)
                    ->first();

                return [
                    'WarehouseID'      => $stock->WarehouseID,
                    'WarehouseName'     => $warehouse->WarehouseName ?? 'Unknown',
                    'Quantity'          => $stock->Quantity,
                    'ReservedQuantity'  => $stock->ReservedQuantity,
                    'TotalWeight'       => $stock->TotalWeight,
                ];
            });

            // 7) Формируем ответ
            $productDetails = [
                'ProductID'           => $product->ProductID,
                'ProductName'         => $product->ProductName,        // взято из переводов
                'ProductDescription'  => $product->ProductDescription, // взято из переводов
                'Unit'                => $product->Unit,
                'ProductType'         => $product->ProductType,
                'Status'              => $product->Status,
                'totalQuantity'       => $totalQuantity,
                'totalReservedQuantity' => $totalReservedQuantity,
                'totalWeight'         => $warehouseStocks->sum('TotalWeight'),
                'warehouseQuantities' => $warehouseDetails,
            ];

            self::setData(result: $productDetails, status: 'success');
        } catch (\Exception $e) {
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }





    /**
     * @throws \Throwable
     * @throws JsonException
     */
    /**
     * @throws \Throwable
     * @throws JsonException
     */
    public function addStockTransaction(): void
    {
        try {
            // Проверка наличия заголовка авторизации
            if (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                self::setData(result: ['error' => 'Authorization required'], statusCode: 401, status: 'error');
                return; // Прекращаем выполнение после отправки ошибки
            }

            $authHeaderParts = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            if (count($authHeaderParts) !== 2) {
                throw new \Exception("Invalid Authorization header format.");
            }

            $jwt = $authHeaderParts[1];
            $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            if (!Input::json()) {
                throw new \Exception('Invalid input data');
            }

            // Проверка обязательных общих полей
            $requiredCommonFields = ['accountID', 'supplierID', 'warehouseID', 'products'];
            foreach ($requiredCommonFields as $field) {
                if (empty(Input::json($field))) {
                    throw new \Exception("The field '{$field}' is required.");
                }
            }

            // Проверка, что 'products' является массивом и не пустым
            $products = Input::json('products');
            if (!is_array($products) || empty($products)) {
                throw new \Exception("The 'products' field must be a non-empty array.");
            }

            // Получение соединения с базой данных
            $connection = (new StockTransactionsModel())->getConnection();

            // Начало транзакции
            $connection->beginTransaction();

            // Функция для преобразования пустых строк в null
            $nullIfEmpty = function($value) {
                return (is_string($value) && trim($value) === '') ? null : $value;
            };

            $createdTransactions = []; // Массив для хранения созданных транзакций

            foreach ($products as $index => $product) {
                // Проверка обязательных полей для каждого товара
                $requiredProductFields = ['productID', 'price', 'quantity'];
                foreach ($requiredProductFields as $pField) {
                    if (!isset($product[$pField]) || empty($product[$pField])) {
                        throw new \Exception("The field '{$pField}' is required for product at index {$index}.");
                    }
                }

                // Создание записи в StockTransactions
                $timeDate = Carbon::now();
                $uuidStockTrans = (string) Str::uuid();

                $stockTransactionEntry = new StockTransactionsModel();
                $stockTransactionEntry->fill([
                    'StockTransactionID' => $uuidStockTrans,
                    'StockTransactionDate' => $timeDate,
                    'StockTransactionType' => 1, // 1 = поступило
                    'ProductID' => $nullIfEmpty($product['productID']),
                    'WarehouseID' => $nullIfEmpty(Input::json('warehouseID')),
                    'StockID' => $uuidStockTrans,
                    'InOutID' => $nullIfEmpty(Input::json('supplierID')),
                    'Price' => $nullIfEmpty($product['price']),
                    'Quantity' => $nullIfEmpty($product['quantity']),
                    'Weight' => isset($product['weight']) ? $nullIfEmpty($product['weight']) : null,
                ]);
                $stockTransactionEntry->save();

                // Получение ID созданной транзакции
                $stockTransactionID = $stockTransactionEntry->StockTransactionID;

                $price = $nullIfEmpty($product['price']);
                $quantity = $nullIfEmpty($product['quantity']);
                // Если количество не указано, использовать только цену
                $amount = !empty($quantity) ? ($price * $quantity) : $price;

                // Создание записи в FinTransactions
                $finTransaction = new FinTransactionsModel();
                $finTransaction->fill([
                    'FinTransactionDate' => date('Y-m-d H:i:s'),
                    'FinTransactionType' => 1,
                    'AccountID' => $nullIfEmpty(Input::json('accountID')),
                    'CorrespondentID' => $nullIfEmpty(Input::json('supplierID')),
                    'TransactionID' => $stockTransactionID,
                    'Amount' => $amount
                ]);
                $finTransaction->save();

                // Добавление в массив созданных транзакций
                $createdTransactions[] = [
                    'stockTransactionID' => $stockTransactionID,
                    'productID' => $product['productID'],
                    'amount' => $amount
                ];
            }

            // Фиксация транзакции
            $connection->commit();

            // Возвращение успешного ответа
            self::setData(
                result: [
                    'createdTransactions' => $createdTransactions
                ],
                status: 'success'
            );
        } catch (\Exception $e) {
            // Откат транзакции базы данных в случае ошибки
            if (isset($connection)) {
                try {
                    $pdo = $connection->getPdo();
                    if ($pdo && $pdo->inTransaction()) { // Используем getPdo()->inTransaction()
                        $connection->rollBack();
                    }
                } catch (\Exception $rollbackException) {
                    // Логирование ошибки отката, если необходимо
                    // Например: error_log($rollbackException->getMessage());
                }
            }
            // Обработка ошибок
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }



    /**
     * Получение всех активных сырьевых запасов (остатков).
     *
     * Метод: POST
     * URL: /api/v1/products/craft-list
     * Требуется авторизация (JWT, роль admin).
     *
     * Вход:
     * - `language` (string, обязательный): Язык для названий (например, "ru", "en").
     *
     * Ответ:
     * - `rawMaterials`: массив остатков с полями ProductID, ProductName, WarehouseID, Price, TotalQuantity, TotalWeight
     *
     * @param ValidatedRequest $request
     * @return void
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/products/craft-list')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    #[Validate([
        'language' => ['required' => true, 'equals' => 2],
    ])]
    public static function getActiveRawMaterials(ValidatedRequest $request): void
    {
        try {
            $request->check(); // Обязательно, как ты указал

            $language = $request->input('language');

            $productModel = new ProductModel();
            $stockModel = new StockTransactionsModel();

            // Получаем активные сырьевые продукты с переводами
            $activeProducts = $productModel
                ->table()
                ->leftJoin('ProductTranslations AS t', function ($join) use ($language) {
                    $join->on('t.ProductID', '=', 'Products.ProductID')
                        ->where('t.LanguageCode', '=', $language);
                })
                ->whereNull('Products.deleted_at')
                ->where('Products.Status', '=', 1)
                ->where('Products.ProductType', '=', 1) // Сырьё
                ->select([
                    'Products.ProductID',
                    't.Name AS ProductName'
                ])
                ->get();

            // Список ID активных продуктов
            $activeProductIDs = $activeProducts->pluck('ProductID');

            // Получение складских остатков по этим продуктам
            $rawMaterials = $stockModel
                ->table()
                ->whereIn('ProductID', $activeProductIDs)
                ->groupBy(['ProductID', 'WarehouseID', 'Price', 'StockTransactionID'])
                ->selectRaw("
                ProductID,
                WarehouseID,
                Price,
                StockTransactionID,
                SUM(CASE WHEN StockTransactionType = '1' THEN Quantity ELSE -Quantity END) AS TotalQuantity,
                SUM(CASE WHEN StockTransactionType = '1' THEN Weight ELSE -Weight END) AS TotalWeight
            ")
                ->havingRaw("
                SUM(CASE WHEN StockTransactionType = '1' THEN Quantity ELSE -Quantity END) > 0
            ")
                ->get();

            // Группируем остатки по ProductID
            $groupedStock = $rawMaterials->groupBy('ProductID');

            // Формируем финальный результат
            $result = $activeProducts->flatMap(function ($product) use ($groupedStock) {
                $productID = $product->ProductID;
                $productName = $product->ProductName ?? 'Unknown';

                if (!$groupedStock->has($productID)) {
                    return [[
                        'ProductID'     => $productID,
                        'ProductName'   => $productName,
                        'WarehouseID'   => null,
                        'Price'         => 0,
                        'TotalQuantity' => 0,
                        'TotalWeight'   => 0,
                    ]];
                }

                return $groupedStock->get($productID)->map(function ($row) use ($productName) {
                    return [
                        'ProductID'     => $row->ProductID,
                        'ProductName'   => $productName,
                        'WarehouseID'   => $row->WarehouseID,
                        'Price'         => $row->Price,
                        'TotalQuantity' => $row->TotalQuantity,
                        'TotalWeight'   => $row->TotalWeight,
                    ];
                })->values()->all();
            });

            self::api([
                'rawMaterials' => $result,
            ]);
        } catch (\Throwable $e) {
            self::api([
                'rawMaterials' => [],
                'error' => $e->getMessage(),
            ], 500, 'error');
        }
    }



    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/products/active-list')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    #[Validate([
        'type'     => ['required' => true, 'type' => 'int'],
        'language' => ['required' => true, 'equals' => 2],
    ])]
    public static function getActiveMaterials(ValidatedRequest $request): void
    {
        try {
            $request->check();

            $materialsType = $request->input('type');
            $language      = $request->input('language');

            $activeProducts = DB::table('Products')
                ->leftJoin('ProductTranslations AS t', function ($join) use ($language) {
                    $join->on('t.ProductID', '=', 'Products.ProductID')
                        ->where('t.LanguageCode', '=', $language);
                })
                ->whereNull('Products.deleted_at')
                ->where('Products.Status', '=', 1)
                ->where('Products.ProductType', '=', $materialsType)
                ->select(
                    'Products.ProductID',
                    DB::raw("COALESCE(t.Name, 'Unknown') AS ProductName")
                )
                ->get();

            self::api([
                'rawMaterials' => $activeProducts
            ]);
        } catch (\Throwable $e) {
            self::api([
                'rawMaterials' => [],
                'error' => $e->getMessage()
            ], 500, 'error');
        }
    }



    /**
     * Получение списка всех активных услуг.
     *
     * Метод доступен по маршруту: GET /api/v1/products/services-list
     * Требуется авторизация по JWT с правами 'admin'.
     *
     * Параметры:
     * - `language` (string, optional): Язык, на котором вернуть название услуги. По умолчанию "ru".
     *
     * Возвращает:
     * - Массив объектов услуг: `ServiceID`, `ServiceName`
     *
     * @return void
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/products/services-list')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    #[Validate([
        'language' => ['required' => true, 'equals' => 2],
    ])]
    public static function getActiveServices(ValidatedRequest $request): void
    {
        $request->check();

        try {
            // Получаем язык из тела запроса или используем "ru" по умолчанию
            $language = Input::json('language') ?? 'ru';

            $productModel = new ProductModel();

            // Получаем активные услуги с переводами на нужном языке
            $services = $productModel
                ->table()
                ->select('Products.ProductID AS ServiceID', 't.Name AS ServiceName')
                ->leftJoin('ProductTranslations AS t', function ($join) use ($language) {
                    $join->on('t.ProductID', '=', 'Products.ProductID')
                        ->where('t.LanguageCode', '=', $language);
                })
                ->whereNull('Products.deleted_at')
                ->where('Products.Status', '=', 1)         // Только активные
                ->where('Products.ProductType', '=', 2)    // Только услуги
                ->get();

            // Возвращаем результат
            self::api([
                'servicesOptions' => $services,
            ]);
        } catch (\Throwable $e) {
            // Ошибка при выполнении запроса
            self::api([
                'servicesOptions' => [],
                'error' => $e->getMessage(),
            ], 500, 'error');
        }
    }

    /**
     * Удаление продукта/услуги
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function deletedProduct(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                $productModel = new ProductModel();

                $productId = Input::json('productID');

                if ($productId === null) {
                    self::setData(result: ['message' => 'ID продукта не был передан'], status: 'error');
                }


                $product = $productModel->table()
                    ->where('ProductID', '=', $productId)
                    ->first();

                if ($product === null) {
                    self::setData(result: ['message' => 'Продукт отсутствует в целевой таблице'], status: 'error');
                }

                $productModel->table()
                    ->where('ProductID', '=', $productId)
                    ->update(['Status' => 0, 'deleted_at' => Carbon::now()]);

                self::setData(result: ['message' => 'Продукт успешно удален'], status: 'success');
            } else {
                self::setData(result: ['servicesOptions' => [], 'error' => 'auth failed'], statusCode: 401, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['servicesOptions' => [], 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }


    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function getProductTransactions(): void
    {
        try {
            // Авторизация
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                self::setData(
                    result: ['error' => 'auth failed'],
                    statusCode: 401,
                    status: 'error'
                );
                return;
            }
            $jwtParts = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            if (count($jwtParts) != 2) {
                self::setData(
                    result: ['error' => 'Invalid Authorization Header'],
                    statusCode: 401,
                    status: 'error'
                );
                return;
            }
            $jwt = $jwtParts[1];
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // Получаем входные данные
            $data = Input::json();
            $productID = $data['productID'] ?? null;
            $startDate = $data['startDate'] ?? null;
            $endDate = $data['endDate'] ?? null;
            $language = $data['language'] ?? 'ru';

            if (!$productID || !$startDate || !$endDate) {
                self::setData(
                    result: ['error' => 'productID, startDate и endDate должны быть заданы'],
                    statusCode: 400,
                    status: 'error'
                );
                return;
            }

            // Преобразуем даты в формат "Y-m-d H:i:s"
            $startDateObj = new \DateTime($startDate);
            $endDateObj = new \DateTime($endDate);
            $startFormatted = $startDateObj->format('Y-m-d H:i:s');
            $endFormatted = $endDateObj->format('Y-m-d') . ' 23:59:59';

            $transactions = DB::table('StockTransactions as st')
                ->leftJoin('Warehouse as w', 'st.WarehouseID', '=', 'w.WarehouseID') // Куда (по старой логике)
                ->leftJoin('Partner as p', 'st.InOutID', '=', 'p.PartnerID')
                ->leftJoin('OrderItems as oi', 'st.InOutID', '=', 'oi.ItemPermID')
                ->leftJoin('Orders as o', 'oi.OrderUUID', '=', 'o.OrderUUID')
                ->leftJoin('ProductTranslations as pt', function ($join) use ($language) {
                    $join->on('pt.ProductID', '=', 'st.ProductID')
                        ->where('pt.LanguageCode', '=', $language);
                })
                ->leftJoin('FinTransactions as ft', 'ft.TransactionID', '=', 'st.StockTransactionID')
                ->leftJoin('Accounts as a', 'a.AccountID', '=', 'ft.AccountID')

                // Новые связи для перемещений
                ->leftJoin('StockMovementHistory as smh', function ($join) {
                    $join->on('st.InOutID', '=', 'smh.MovementID')
                        ->where('st.Movies', '=', 1);
                })
                ->leftJoin('Warehouse as sw', 'smh.SourceWarehouseID', '=', 'sw.WarehouseID')
                ->leftJoin('Warehouse as dw', 'smh.DestinationWarehouseID', '=', 'dw.WarehouseID')

                ->select(
                    'st.StockTransactionID',
                    'st.StockTransactionDate',
                    'st.StockTransactionType',
                    'st.ProductID',
                    DB::raw("COALESCE(pt.Name, 'Unknown') AS ProductName"),
                    'st.Quantity',
                    'st.Price',
                    DB::raw('(st.Quantity * st.Price) AS TotalAmount'),
                    DB::raw("COALESCE(a.Currency, 'GEL') AS Currency"),
                    'st.WarehouseID',
                    'w.WarehouseName',

                    // Направление перемещения
                    DB::raw("CASE 
            WHEN st.Movies = 1 THEN CONCAT(COALESCE(sw.WarehouseName, 'Неизвестно'), ' → ', COALESCE(dw.WarehouseName, 'Неизвестно'))
            ELSE w.WarehouseName
        END AS Direction"),

                    // Инициатор
                    DB::raw("CASE 
    WHEN st.Movies = 1 THEN CAST(smh.InitiatorID AS NVARCHAR(36))
    WHEN st.StockTransactionType = '1' THEN CAST(p.PartnerID AS NVARCHAR(36))
    WHEN st.StockTransactionType IN ('2','3','4') THEN CAST(COALESCE(o.OrderUUID, st.InOutID) AS NVARCHAR(36))
    ELSE NULL
END AS initiatorID"),

                    // Инициатор имя
                    DB::raw("CASE 
            WHEN st.Movies = 1 THEN CONCAT('Перемещение', 
                                           CASE WHEN smh.Notes IS NOT NULL THEN CONCAT(': ', smh.Notes) ELSE '' END)
            WHEN st.StockTransactionType = '1' THEN COALESCE(p.LegalName, 'Неизвестно')
            WHEN st.StockTransactionType IN ('2','3') THEN CONCAT(COALESCE(oi.ProductName, 'Неизвестно'), ' - ', COALESCE(o.OrderName, 'Неизвестно'))
            WHEN st.StockTransactionType = '4' THEN COALESCE(oi.ProductName, pt.Name, 'Неизвестно')
            ELSE NULL
        END AS initiatorName")
                )
                ->where('st.ProductID', $productID)
                ->whereBetween('st.StockTransactionDate', [$startFormatted, $endFormatted])
                ->groupBy(
                    'st.StockTransactionID',
                    'st.StockTransactionDate',
                    'st.StockTransactionType',
                    'st.ProductID',
                    'pt.Name',
                    'st.Quantity',
                    'st.Price',
                    'st.WarehouseID',
                    'w.WarehouseName',
                    'sw.WarehouseName',
                    'dw.WarehouseName',
                    'p.PartnerID',
                    'p.LegalName',
                    'oi.ItemPermID',
                    'oi.ProductName',
                    'o.OrderName',
                    'o.OrderUUID',
                    'st.InOutID',
                    'a.Currency',
                    'st.Movies',
                    'smh.InitiatorID',
                    'smh.Notes'
                )
                ->orderBy('st.StockTransactionDate', 'desc')
                ->get();




            self::setData(result: ['transactions' => $transactions], status: 'success');
        } catch (\Exception $e) {
            self::setData(result: ['transactions' => [], 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }






}