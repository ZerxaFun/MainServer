<?php

namespace Modules\API\Controller;

use Carbon\Carbon;
use Core\Routing\Attributes\HttpMethod;
use Core\Services\Attributes\Validate;
use Core\Services\Auth\Attributes\Authorize;
use Core\Services\Http\Input;
use Core\Services\Http\ValidatedRequest;
use Core\Services\Path\Path;
use Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use Modules\API\Model\FileModel;
use Modules\API\Model\FinTransactionsModel;
use Modules\API\Model\OrderItemModel;
use Modules\API\Model\OrderModel;
use Modules\API\Model\ProductModel;
use Modules\API\Model\StockTransactionsModel;
use Modules\API\Model\WarehouseModel;

/**
 * OrderController - контроллер для управления заказами и их версиями.
 */
class OrderController extends Controller
{
    /**
     * Получение активного заказа по UUID с деталями и подсчётом фактической стоимости.
     * Возвращает заказ последней версии, связанные изделия, дату создания первого заказа,
     * общую стоимость списаний и броней по изделиям.
     *
     * Требует авторизацию по JWT и валидный UUID.
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/orders/get-task')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    #[Validate([
        'uuid' => ['required' => true, 'type' => 'uuid'],
    ])]
    public static function getOrder(ValidatedRequest $request): void
    {
        try {
            $request->check();
            $uuid = $request->input('uuid');

            // Получаем заказ последней версии с автором и партнёром
            $order = OrderModel::query()
                ->where('OrderUUID', $uuid)
                ->join('UserAccount', 'Orders.CreatedBy', '=', 'UserAccount.UserID')
                ->join('Partner', 'Orders.PartnerID', '=', 'Partner.PartnerID')
                ->orderByDesc('Orders.Version')
                ->select([
                    'Orders.*',
                    'UserAccount.Username as UserName',
                    'Partner.LegalName as PartnerName',
                    'Partner.ShortName as PartnerShortName',
                ])
                ->first();

            if (!$order) {
                throw new \Exception("Order not found.");
            }

            // Получаем дату создания самого первого заказа (версия 1)
            $orderFirstDate = OrderModel::query()
                ->where('OrderUUID', $uuid)
                ->where('Version', 1)
                ->value('CreatedAt');

            // Получаем изделия этой версии
            $orderItems = OrderItemModel::query()
                ->where('OrderUUID', $uuid)
                ->where('Version', $order->Version)
                ->orderByDesc('CreatedAt')
                ->get();

            $factuallyTotalCost = 0;
            $factuallyReservTotalCost = 0;
            $itemsWithDetails = [];

            // Подсчитываем стоимость по каждому изделию
            foreach ($orderItems as $item) {
                $stockTransactions = DB::table('StockTransactions')
                    ->where('InOutID', $item->ItemPermID)
                    ->whereIn('StockTransactionType', [2, 3]) // 2 = списание, 3 = бронь
                    ->select(['Quantity', 'Price', 'StockTransactionType'])
                    ->get();

                $factuallyCost = 0;
                $factuallyReservCost = 0;

                foreach ($stockTransactions as $stock) {
                    $cost = abs($stock->Quantity) * $stock->Price;
                    match ((int)$stock->StockTransactionType) {
                        2 => $factuallyCost += $cost,
                        3 => $factuallyReservCost += $cost,
                        default => null,
                    };
                }

                $factuallyTotalCost += $factuallyCost;
                $factuallyReservTotalCost += $factuallyReservCost;

                $itemsWithDetails[] = [
                    'item' => $item,
                    'factuallyCost' => round($factuallyCost, 2),
                    'factuallyReservCost' => round($factuallyReservCost, 2),
                ];
            }

            // Возвращаем результат
            self::api([
                'order' => $order,
                'items' => $itemsWithDetails,
                'created_at' => $orderFirstDate,
                'factually_total_cost' => round($factuallyTotalCost, 2),
                'factually_reserv_total_cost' => round($factuallyReservTotalCost, 2),
            ]);

        } catch (\Throwable $e) {
            self::api(
                ['error' => $e->getMessage()],
                500,
                'error'
            );
        }
    }



    public function updatedServices()
    {
        try {
            // 🔹 1. Проверяем наличие авторизации
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new \Exception('Authorization failed: Authorization header is missing.');
            }

            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $jwt = explode(" ", $authHeader)[1] ?? null;

            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }

            // 🔹 2. Валидация JWT
            try {
                JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));
            } catch (\Exception $e) {
                throw new \Exception('Invalid token: ' . $e->getMessage());
            }

            // 🔹 3. Получаем данные из запроса
            $ItemPermID = Input::json('ItemPermID');
            $selectedService = Input::json('selectedService');
            $unitPrice = Input::json('unitPrice');
            $actualPrice = Input::json('actualPrice') ?? null; // Фактическая цена (может быть null)

            // 🔹 4. Проверяем, переданы ли все обязательные параметры
            if (empty($ItemPermID)) {
                throw new \Exception('Error: ItemPermID is required.');
            }
            if (empty($selectedService)) {
                throw new \Exception('Error: Selected service is required.');
            }
            if (!is_numeric($unitPrice) || $unitPrice < 0) {
                throw new \Exception('Error: Invalid unit price.');
            }
            if ($actualPrice !== null && (!is_numeric($actualPrice) || $actualPrice < 0)) {
                throw new \Exception('Error: Invalid actual price.');
            }

            // 🔹 5. Получаем изделие
            $orderItemModel = new OrderItemModel();
            $item = $orderItemModel->table()
                ->where('ItemPermID', $ItemPermID)
                ->orderBy('Version', 'desc')
                ->first();

            if (!$item) {
                throw new \Exception('Error: Item not found.');
            }

            // 🔹 6. Получаем текущие материалы и услуги
            $itemMaterialsAndServices = json_decode($item->MaterialsAndServices, true) ?? [];

            if (!isset($itemMaterialsAndServices['services'])) {
                $itemMaterialsAndServices['services'] = [];
            }

            // 🔹 7. Проверяем, существует ли уже этот сервис
            foreach ($itemMaterialsAndServices['services'] as &$service) {
                if ($service['selectedService'] == $selectedService) {
                    // Обновляем цену, если сервис уже добавлен
                    $service['unitPrice'] = $unitPrice;
                    $service['actualPrice'] = $actualPrice;
                    $serviceExists = true;
                    break;
                }
            }

            if (!isset($serviceExists)) {
                // Добавляем новый сервис
                $itemMaterialsAndServices['services'][] = [
                    'selectedService' => $selectedService,
                    'unitPrice' => $unitPrice,
                    'actualPrice' => $actualPrice,
                ];
            }

            // 🔹 8. Обновляем запись в БД
            $updatedData = [
                'MaterialsAndServices' => json_encode($itemMaterialsAndServices, JSON_UNESCAPED_UNICODE),
            ];

            // 🔹 9. Используем транзакцию для безопасности
            DB::beginTransaction();
            $orderItemModel->table()
                ->where('ItemPermID', $ItemPermID)
                ->update($updatedData);
            DB::commit();

            // ✅ 10. Возвращаем успешный ответ
            self::setData(
                result: [
                    'message' => 'Service updated successfully.',
                    'updated_services' => $itemMaterialsAndServices['services'],
                ],
                status: 'success'
            );

        } catch (\Exception $e) {
            DB::rollBack(); // Откатываем транзакцию в случае ошибки

            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }


    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/orders/get-task-edit')]
    #[Authorize(guard: 'jwt', permission: ['admin'])] // поправь роли при необходимости
    #[Validate([
        'uuid'     => ['required' => true, 'type' => 'uuid'],
        'language' => ['required' => true, 'equals' => 2],
    ])]
    public static function getOrderEdit(ValidatedRequest $request): void
    {
        try {
            $request->check();

            $uuid     = $request->input('uuid');
            $language = $request->input('language');

            $order = OrderModel::where('OrderUUID', $uuid)
                ->join('UserAccount', 'Orders.CreatedBy', '=', 'UserAccount.UserID')
                ->join('Partner', 'Orders.PartnerID', '=', 'Partner.PartnerID')
                ->orderByDesc('Orders.Version')
                ->select([
                    'Orders.*',
                    'UserAccount.Username as UserName',
                    'Partner.LegalName as PartnerName',
                    'Partner.ShortName as PartnerShortName',
                ])
                ->first();

            $orderFirstDate = OrderModel::where('OrderUUID', $uuid)
                ->where('Version', 1)
                ->select('CreatedAt')
                ->first();

            if (!$order) {
                throw new \Exception('Order not found.');
            }

            $orderItems = OrderItemModel::where('OrderUUID', $uuid)
                ->where('Version', $order->Version)
                ->orderByDesc('CreatedAt')
                ->get();

            $itemsWithDetails = [];

            foreach ($orderItems as $item) {
                $materialsAndServices = json_decode($item->MaterialsAndServices, true) ?: [];
                $materials = $materialsAndServices['materials'] ?? [];
                $services  = $materialsAndServices['services'] ?? [];

                $detailedMaterials = [];

                foreach ($materials as $material) {
                    $materialID = $material['selectedMaterial'];

                    $materialName = DB::table('ProductTranslations')
                        ->where('ProductID', $materialID)
                        ->where('LanguageCode', $language)
                        ->value('Name') ?? 'Unknown';

                    $totalAvailable = DB::table('StockTransactions')
                        ->join('Warehouse', 'StockTransactions.WarehouseID', '=', 'Warehouse.WarehouseID')
                        ->where('StockTransactions.ProductID', $materialID)
                        ->sum(DB::raw("
                        CASE
                          WHEN StockTransactionType = 1 THEN Quantity
                          WHEN StockTransactionType IN (2, 3) THEN Quantity
                          ELSE 0
                        END
                    "));

                    $remainingQuantity = $material['quantity'];
                    $takenFromStock = [];

                    $sortedStock = DB::table('StockTransactions')
                        ->join('Warehouse', 'StockTransactions.WarehouseID', '=', 'Warehouse.WarehouseID')
                        ->where('StockTransactions.ProductID', $materialID)
                        ->where('StockTransactions.StockTransactionType', 1)
                        ->select(['StockTransactions.Quantity', 'StockTransactions.Price'])
                        ->orderBy('StockTransactions.Price', 'asc')
                        ->get();

                    foreach ($sortedStock as $stock) {
                        if ($remainingQuantity <= 0) break;

                        $availableQuantity = $stock->Quantity;
                        $takenQuantity = min($remainingQuantity, $availableQuantity);

                        $takenFromStock[] = [
                            'price'         => $stock->Price,
                            'takenQuantity' => $takenQuantity,
                        ];

                        $remainingQuantity -= $takenQuantity;
                    }

                    $debt = max(0, $material['quantity'] - $totalAvailable);

                    $detailedMaterials[] = [
                        'id'                => $materialID,
                        'name'              => $materialName,
                        'quantity'          => $material['quantity'],
                        'quantityUnit'      => $material['quantity'] / $item->Quantity,
                        'unitPrice'         => $material['unitPrice'] ?? 0,
                        'totalAvailable'    => $totalAvailable,
                        'takenFromStock'    => $takenFromStock,
                        'remainingQuantity' => $debt,
                    ];
                }

                $detailedServices = [];
                foreach ($services as $service) {
                    $serviceID = $service['selectedService'] ?? '';

                    $serviceName = DB::table('ProductTranslations')
                        ->where('ProductID', $serviceID)
                        ->where('LanguageCode', $language)
                        ->value('Name') ?? 'Unknown';

                    $detailedServices[] = [
                        'id'        => $serviceID,
                        'name'      => $serviceName,
                        'unitPrice' => $service['unitPrice'] ?? 0,
                    ];
                }

                $itemsWithDetails[] = [
                    'item'      => $item,
                    'materials' => $detailedMaterials,
                    'services'  => $detailedServices,
                ];
            }

            self::api([
                'order'           => $order,
                'items'           => $itemsWithDetails,
                'created_at'      => $orderFirstDate->CreatedAt ?? null,
            ]);
        } catch (\Throwable $e) {
            self::api(['error' => $e->getMessage()], 500, 'error');
        }
    }








    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/orders/get-item-materials')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    #[Validate([
        'ItemPermID' => ['required' => true, 'type' => 'uuid'],
        'itemUUID'   => ['required' => false, 'type' => 'uuid'],
        'language' => ['required' => true, 'equals' => 2],
    ])]
    public static function getItemMaterials(ValidatedRequest $request): void
    {
        try {
            $request->check();
            $ItemPermID = $request->input('ItemPermID');
            $ItemUUID = $request->input('itemUUID');
            $language = $request->input('language');

            $item = OrderItemModel::where('ItemPermID', $ItemPermID)
                ->orderByDesc('CreatedAt')
                ->firstOrFail();

            $materialsAndServices = json_decode($item->MaterialsAndServices, true);
            $materials = $materialsAndServices['materials'] ?? [];
            $services  = $materialsAndServices['services'] ?? [];

            $stockTransactionsModel = new StockTransactionsModel();

            $detailedMaterials = [];
            foreach ($materials as $material) {
                $materialID = $material['selectedMaterial'];
                $materialName = DB::table('ProductTranslations')
                    ->where('ProductID', $materialID)
                    ->where('LanguageCode', $language)
                    ->value('Name') ?? 'Unknown';

                $stockTransactions = DB::table('StockTransactions')
                    ->where('ProductID', $materialID)
                    ->where('InOutID', $ItemPermID)
                    ->whereIn('StockTransactionType', [2, 3])
                    ->select('StockTransactionType', 'Quantity', 'Price')
                    ->get();

                $factuallyTaken = $reservedQuantity = $factuallyCost = 0.0;

                foreach ($stockTransactions as $stock) {
                    $quantity = abs($stock->Quantity);
                    if ($stock->StockTransactionType == 2) $factuallyTaken += $quantity;
                    if ($stock->StockTransactionType == 3) $reservedQuantity += $quantity;
                    $factuallyCost += $quantity * $stock->Price;
                }

                $detailedMaterials[] = [
                    'id'               => $materialID,
                    'name'             => $materialName,
                    'quantity'         => $material['quantity'],
                    'factuallyTaken'   => round($factuallyTaken, 2),
                    'reservedQuantity' => round($reservedQuantity, 2),
                    'factuallyCost'    => round($factuallyCost, 2),
                    'unitPrice'        => $material['unitPrice'],
                ];
            }

            $stt = $stockTransactionsModel->table()
                ->where('InOutID', $ItemPermID)
                ->whereIn('StockTransactionType', [2, 3])
                ->where('IsContractor', 0)
                ->select('StockTransactionType', 'Quantity', 'Price', 'StockID', 'ProductID')
                ->get();

            $existingMaterialIDs = array_column($detailedMaterials, 'id');

            $olderMaterials = OrderItemModel::where('ItemPermID', $ItemPermID)
                ->orderBy('CreatedAt')
                ->first()?->MaterialsAndServices ?? '[]';
            $olderMaterials = json_decode($olderMaterials, true)['materials'] ?? [];

            $groupedSTT = [];
            foreach ($stt as $transaction) {
                $groupedSTT[$transaction->ProductID][] = $transaction;
            }

            foreach ($groupedSTT as $productID => $transactions) {
                if (in_array($productID, $existingMaterialIDs)) continue;

                $materialName = DB::table('ProductTranslations')
                    ->where('ProductID', $productID)
                    ->where('LanguageCode', $language)
                    ->value('Name') ?? 'Unknown';

                $olderMaterial = collect($olderMaterials)->firstWhere('selectedMaterial', $productID) ?? ['quantity' => 0, 'unitPrice' => 0];

                $factuallyTaken = $reservedQuantity = $factuallyCost = 0.0;
                foreach ($transactions as $stock) {
                    $quantity = abs($stock->Quantity);
                    if ($stock->StockTransactionType == 2) $factuallyTaken += $quantity;
                    if ($stock->StockTransactionType == 3) $reservedQuantity += $quantity;
                    $factuallyCost += $quantity * $stock->Price;
                }

                $detailedMaterials[] = [
                    'id'               => $productID,
                    'name'             => $materialName,
                    'quantity'         => $olderMaterial['quantity'],
                    'factuallyTaken'   => round($factuallyTaken, 2),
                    'reservedQuantity' => round($reservedQuantity, 2),
                    'factuallyCost'    => round($factuallyCost, 2),
                    'unitPrice'        => $olderMaterial['unitPrice'],
                ];
            }

            // === Услуги ===
            $detailedServices = [];
            foreach ($services as $service) {
                $serviceID = $service['selectedService'];
                $serviceName = DB::table('ProductTranslations')
                    ->where('ProductID', $serviceID)
                    ->where('LanguageCode', $language)
                    ->value('Name') ?? 'Unknown';

                $masters = $stockTransactionsModel->table()
                    ->select(['ProductID', 'Price'])
                    ->where('InOutID', $ItemUUID)
                    ->where('StockTransactionType', 4)
                    ->where('ProductID', $serviceID)
                    ->where('IsContractor', 1)
                    ->get();

                $detailedServices[] = [
                    'id'        => $serviceID,
                    'name'      => $serviceName,
                    'unitPrice' => $service['unitPrice'],
                    'masters'   => $masters,
                ];
            }

            $sttServices = $stockTransactionsModel->table()
                ->where('InOutID', $ItemPermID)
                ->where('StockTransactionType', 4)
                ->where('IsContractor', 1)
                ->select('ProductID', 'Price')
                ->get();

            $existingServiceIDs = array_column($detailedServices, 'id');

            $olderServices = OrderItemModel::where('ItemPermID', $ItemPermID)
                ->orderBy('CreatedAt')
                ->first()?->MaterialsAndServices ?? '[]';
            $olderServices = json_decode($olderServices, true)['services'] ?? [];

            foreach ($sttServices as $service) {
                if (in_array($service->ProductID, $existingServiceIDs)) continue;

                $serviceName = DB::table('ProductTranslations')
                    ->where('ProductID', $service->ProductID)
                    ->where('LanguageCode', $language)
                    ->value('Name') ?? 'Unknown';

                $olderService = collect($olderServices)->firstWhere('selectedService', $service->ProductID) ?? ['unitPrice' => 0];

                $masters = $stockTransactionsModel->table()
                    ->select(['ProductID', 'Price'])
                    ->where('InOutID', $ItemUUID)
                    ->where('StockTransactionType', 4)
                    ->where('ProductID', $service->ProductID)
                    ->where('IsContractor', 1)
                    ->get();

                $detailedServices[] = [
                    'id'        => $service->ProductID,
                    'name'      => $serviceName,
                    'unitPrice' => $olderService['unitPrice'],
                    'masters'   => $masters,
                ];
            }

            self::api([
                'materials' => $detailedMaterials,
                'services'  => $detailedServices,
            ]);
        } catch (\Throwable $e) {
            self::api(['error' => $e->getMessage()], 500, 'error');
        }
    }











    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/orders/get-attachments')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    #[Validate([
        'uuid' => ['required' => true, 'type' => 'uuid'],
    ])]
    public static function getOrderAttachments(ValidatedRequest $request): void
    {
        try {
            $request->check();

            $uuid = $request->input('uuid');

            $order = OrderModel::query()
                ->where('OrderUUID', $uuid)
                ->orderByDesc('Version')
                ->first();

            if (!$order) {
                throw new \Exception("Заказ с UUID {$uuid} не найден.", 404);
            }

            $files = FileModel::query()
                ->where('OrderUUID', $uuid)
                ->whereNull('DeletedAt')
                ->where('Version', $order->Version)
                ->select(['Filename', 'Filepath', 'Size', 'Type', 'CreatedAt'])
                ->get();

            self::api(['files' => $files]);

        } catch (\Throwable $e) {
            Logger::error('getOrderAttachments failed', [
                'uuid' => $request->input('uuid'),
                'error' => $e->getMessage()
            ]);

            self::api(
                ['error' => $e->getMessage()],
                $e->getCode() ?: 500,
                'error'
            );
        }
    }


    /**
     * Загрузка файла по пути, предоставленному в запросе.
     * (Не связано с версиями напрямую, но оставляем как есть.)
     *
     * @return void
     * @throws JsonException
     */
    public function downloadAttachment(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
                $jwt = explode(" ", $authHeader)[1] ?? null;

                if (!$jwt) {
                    throw new \Exception('Token not provided.');
                }

                JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                $filePath = Input::json('filepath');

                if (!file_exists($filePath)) {
                    throw new \Exception('File not found.');
                }

                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filePath));
                readfile($filePath);
                exit;
            } else {
                self::setData(
                    result: ['error' => 'Authorization failed'],
                    statusCode: 401,
                    status: 'error'
                );
            }
        } catch (\Exception $e) {
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }

    /**
     * Создание нового заказа с версией 1.
     * Все изделия и файлы также получают версию 1.
     *
     * @return void
     * @throws JsonException
     */
    public function createOrder(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
                $jwt = explode(" ", $authHeader)[1] ?? null;

                if (!$jwt) {
                    throw new \Exception('Token not provided.');
                }

                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                // Извлекаем данные из POST
                $orderData = [
                    'estimatedCost' => $_POST['orderData']['estimatedCost'] ?? null,
                    'generalDeadlineDate' => $_POST['orderData']['generalDeadlineDate'] ?? null,
                ];

                $taskData = [
                    'taskName' => $_POST['taskData']['taskName'] ?? null,
                    'editorData' => $_POST['taskData']['editorData'] ?? null,
                ];

                $partnerID = $_POST['partnerID'] ?? null;
                if ($partnerID === 'null') {
                    $partnerID = null;
                }

                $executorCompanyID = $_POST['executorCompanyID'] ?? null;
                if ($executorCompanyID === 'null') {
                    $executorCompanyID = null;
                }

                $products = $_POST['products'] ?? [];

                // Начинаем транзакцию
                DB::beginTransaction();

                $newUUID = (string) Str::uuid();

                // Создаем заказ с версией 1
                $order = OrderModel::create([
                    'OrderUUID' => $newUUID,
                    'Version' => 1,
                    'PartnerID' => $partnerID,
                    'OrderName' => $taskData['taskName'],
                    'Description' => $taskData['editorData'] ?? null,
                    'EstimatedCost' => $orderData['estimatedCost'],
                    'GeneralDeadlineDate' => $orderData['generalDeadlineDate'],
                    'OrderStatus' => 1,
                    'CorrespondentID' => $executorCompanyID,
                    'CreatedAt' => Carbon::now(),
                    'CreatedBy' => $decode->UserID ?? null,
                ]);

                // Создаем изделия с версией 1
                foreach ($products as $productData) {
                    $materials = $productData['selectedConstructionMaterials'] ?? [];
                    $services = $productData['selectedServices'] ?? [];
                    $quantity = max((int) ($productData['quantity'] ?? 1), 1);

                    $scaledMaterials = array_map(function ($material) use ($quantity) {
                        return [
                            'selectedMaterial' => $material['selectedMaterial'] ?? '',
                            'quantity' => ($material['quantity'] ?? 0),
                            'unitPrice' => $material['unitPrice'] ?? 0,
                        ];
                    }, $materials);



                    $materialsAndServices = [
                        'materials' => $scaledMaterials,
                        'services' => $services,
                    ];

                    OrderItemModel::create([
                        'OrderUUID' => $newUUID,
                        'Version' => 1,
                        'ProductName' => $productData['productName'],
                        'Description' => $productData['description'] ?? null,
                        'Quantity' => $quantity,
                        'ManualPrice' => $productData['estimatedCost'],
                        'deadlineDate' => $productData['deadlineDate'],
                        'WorkStatus' => 1,
                        'MaterialsAndServices' => json_encode($materialsAndServices, JSON_UNESCAPED_UNICODE),
                        'CreatedAt' => Carbon::now(),
                        'ItemPermID' => (string) Str::uuid(), // Присваиваем уникальный ItemPermID
                    ]);
                }


                // Обработка файлов (версия 1)
                if ($this->hasFiles()) {
                    foreach ($this->getFiles() as $file) {
                        $uniqueFileName = Str::uuid() . '_' . $file['name'];
                        $directory = Path::base('Storage') . 'Orders' . DIRECTORY_SEPARATOR . $newUUID;
                        if (!is_dir($directory)) {
                            mkdir($directory, 0755, true);
                        }
                        $filePath = $directory . '/' . $uniqueFileName;
                        move_uploaded_file($file['tmp_name'], $filePath);

                        FileModel::create([
                            'OrderUUID' => $newUUID,
                            'Filename' => $file['name'],
                            'Filepath' => $filePath,
                            'Size' => $file['size'],
                            'Type' => $file['type'] ?? null,
                            'CreatedAt' => Carbon::now(),
                            'Version' => 1,
                        ]);
                    }
                }

                DB::commit();

                self::setData(
                    result: ['order_uuid' => $order->OrderUUID],
                    statusCode: 201,
                    status: 'success'
                );
            } else {
                self::setData(
                    result: ['error' => 'Authorization failed'],
                    statusCode: 401,
                    status: 'error'
                );
            }
        } catch (\Exception $e) {
            DB::rollBack();
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }

    /**
     * Проверка наличия загруженных файлов
     */
    private function hasFiles(): bool
    {
        return isset($_FILES['attachedFiles']) && !empty($_FILES['attachedFiles']['name'][0]);
    }

    /**
     * Получаем массив файлов из запроса
     */
    private function getFiles(): array
    {
        $files = [];
        if ($this->hasFiles()) {
            foreach ($_FILES['attachedFiles']['name'] as $index => $name) {
                $files[] = [
                    'name' => $name,
                    'type' => $_FILES['attachedFiles']['type'][$index],
                    'tmp_name' => $_FILES['attachedFiles']['tmp_name'][$index],
                    'error' => $_FILES['attachedFiles']['error'][$index],
                    'size' => $_FILES['attachedFiles']['size'][$index],
                ];
            }
        }
        return $files;
    }
    /**
     * Получение списка заказов (последняя версия) с данными партнёра.
     *
     * Метод: POST
     * URL: /api/v1/orders/list
     * Требуется авторизация (JWT, роль admin).
     *
     * Входные параметры (в JSON):
     * - searchQuery (string|null): фильтр по названию или описанию заказа
     * - startDate (string|null): фильтр по дате создания от (YYYY-MM-DD)
     * - endDate (string|null): фильтр по дате создания до (YYYY-MM-DD)
     * - page (int): номер страницы (по умолчанию 1)
     * - perPage (int): количество на странице (по умолчанию 15)
     *
     * @param ValidatedRequest $request
     * @return void
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/orders/list')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    #[Validate([
        'searchQuery' => ['required' => false, 'type' => 'string'],
        'filters.startDate'   => ['required' => true, 'type' => 'date'],
        'filters.endDate'     => ['required' => true, 'type' => 'date'],
        'page'        => ['required' => false, 'type' => 'int', 'min' => 1],
        'perPage'     => ['required' => false, 'type' => 'int', 'min' => 1],
    ])]
    public static function getOrderList(ValidatedRequest $request): void
    {
        try {
            $request->check();

            $filters = $request->input('filters');
            $page = $request->input('page');
            $perPage = $request->input('perPage');

            $query = OrderModel::query()
                ->select(
                    'orders.*',
                    'Partner.LegalName',
                    'Partner.ShortName',
                    'Partner.TAXID',
                    'Partner.Comments',
                    'Partner.CompanyType',
                    'Partner.Status as PartnerStatus'
                )
                ->joinSub(
                    OrderModel::query()
                        ->selectRaw('OrderUUID, MAX(Version) as max_version')
                        ->groupBy('OrderUUID'),
                    'latest_versions',
                    fn($join) => $join->on('orders.OrderUUID', '=', 'latest_versions.OrderUUID')
                        ->whereColumn('orders.Version', '=', 'latest_versions.max_version')
                )
                ->join('Partner', 'orders.PartnerID', '=', 'Partner.PartnerID');

            // Применение фильтров
            if (!empty($filters['startDate'])) {
                $query->where('orders.CreatedAt', '>=', $filters['startDate']);
            }

            if (!empty($filters['endDate'])) {
                $query->where('orders.CreatedAt', '<=', $filters['endDate']);
            }

            if (!empty($filters['searchQuery'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('orders.OrderName', 'LIKE', '%' . $filters['searchQuery'] . '%')
                        ->orWhere('orders.Description', 'LIKE', '%' . $filters['searchQuery'] . '%');
                });
            }

            // Подсчёт общего количества и выборка с пагинацией
            $total = $query->count();

            $orders = $query
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            // Подсчёт оплаты
            $finTransactionModel = new FinTransactionsModel();

            foreach ($orders as $order) {
                $transactions = $finTransactionModel->table()
                    ->where('TransactionID', $order->OrderUUID)
                    ->where('Status', 1)
                    ->get();

                $paidAmount = $transactions->sum('Amount');
                $order->paidAmount = $paidAmount;

                $order->paymentStatus = $transactions->isEmpty()
                    ? 0
                    : ($paidAmount < (float)$order->EstimatedCost ? 1 : 2);
            }

            self::api([
                'orders' => $orders,
                'pagination' => [
                    'currentPage' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                ],
            ]);
        } catch (\Throwable $e) {
            self::api([
                'error' => $e->getMessage(),
            ], 500, 'error');
        }
    }


    /**
     * Удаление заказа (не связанное с версионностью, просто удаляем все записи)
     */
    public function deleteOrder(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
                $jwt = explode(" ", $authHeader)[1] ?? null;

                if (!$jwt) {
                    throw new \Exception('Token not provided.');
                }

                JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                $uuid = Input::json('uuid');

                if (empty($uuid)) {
                    throw new \Exception('Order UUID is required.');
                }

                $order = OrderModel::where('OrderUUID', $uuid)->first();

                if (!$order) {
                    throw new \Exception('Order not found.');
                }

                DB::beginTransaction();
                // Удаляем все версии изделий
                OrderItemModel::where('OrderUUID', $uuid)->delete();
                // Удаляем все версии файлов
                FileModel::where('OrderUUID', $uuid)->delete();
                // Удаляем все версии заказа
                OrderModel::where('OrderUUID', $uuid)->delete();
                DB::commit();

                self::setData(
                    result: ['message' => 'Order deleted successfully.'],
                    status: 'success'
                );
            } else {
                self::setData(
                    result: ['error' => 'Authorization failed'],
                    statusCode: 401,
                    status: 'error'
                );
            }
        } catch (\Exception $e) {
            DB::rollBack();
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }


    /**
     * Обновление заказа с созданием новой версии.
     * Создаёт новые записи заказа, изделий и файлов с увеличенной версией.
     *
     * @return void
     * @throws JsonException
     */
    public function updatedOrder(): void
    {
        try {
            // Проверка авторизации
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new \Exception('Authorization failed: No Authorization header found.');
            }

            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $jwt = null;
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $jwt = $matches[1];
            }

            if (!$jwt) {
                throw new \Exception('Authorization failed: Token not provided.');
            }

            // Декодирование JWT
            $decoded = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // Получение данных из запроса
            $uuid = Input::post('orderUUID');
            $updatedOrderData = Input::post('orderData', []);
            $updatedTaskData = Input::post('taskData', []);
            $partnerID = Input::post('partnerID', null);
            $executorCompanyID = Input::post('executorCompanyID', null);
            $filesToAdd = $this->getFilesFromRequest(); // Метод для получения загруженных файлов
            $filesToRemove = Input::post('filesToRemove', []); // Массив путей файлов для удаления

            // Проверка наличия обязательного поля
            if (empty($uuid)) {
                throw new \Exception('Order UUID not provided.');
            }

            // Начало транзакции
            DB::beginTransaction();

            // Блокировка текущего заказа для предотвращения одновременных обновлений
            $currentOrder = OrderModel::where('OrderUUID', $uuid)
                ->orderBy('Version', 'desc')
                ->lockForUpdate()
                ->first();

            if (!$currentOrder) {
                throw new \Exception('Order not found.');
            }

            $currentVersion = $currentOrder->Version;
            $newVersion = $currentVersion + 1;

            // Проверка, существует ли уже новая версия (для предотвращения дубликатов)
            $existingNewVersion = OrderModel::where('OrderUUID', $uuid)
                ->where('Version', $newVersion)
                ->first();

            if ($existingNewVersion) {
                throw new \Exception('New version already exists.');
            }

            // Дублирование заказа с новой версией
            $newOrder = $currentOrder->replicate();
            $newOrder->Version = $newVersion;

            // Обновление полей заказа согласно входным данным
            if (!empty($updatedOrderData)) {
                $newOrder->EstimatedCost = isset($updatedOrderData['estimatedCost']) ? floatval($updatedOrderData['estimatedCost']) : $currentOrder->EstimatedCost;
                $newOrder->GeneralDeadlineDate = isset($updatedOrderData['generalDeadlineDate']) ? $updatedOrderData['generalDeadlineDate'] : $currentOrder->GeneralDeadlineDate;
                $newOrder->PartnerID = $partnerID ?? $currentOrder->PartnerID;
            }

            // Обновление описания и названия заказа
            if (!empty($updatedTaskData)) {
                $newOrder->OrderName = isset($updatedTaskData['taskName']) ? $updatedTaskData['taskName'] : $currentOrder->OrderName;
                $newOrder->Description = isset($updatedTaskData['editorData']) ? $updatedTaskData['editorData'] : $currentOrder->Description;
            }

            // Обновление времени создания
            $newOrder->CreatedAt = Carbon::now();
            $newOrder->save();



            $products = Input::post('products', []);
            foreach ($products as $productData) {

                $materials = $productData['selectedConstructionMaterials'] ?? [];
                $services = $productData['selectedServices'] ?? [];
                $quantity = max((int) ($productData['quantity'] ?? 1), 1);

                $scaledMaterials = array_map(function ($material) use ($quantity) {
                    return [
                        'selectedMaterial' => $material['selectedMaterial'] ?? '',
                        'quantity' => ($material['quantity'] ?? 0),
                        'unitPrice' => $material['unitPrice'] ?? 0,
                    ];
                }, $materials);

                // Умножаем стоимость услуг на количество изделий
                $scaledServices = array_map(function ($service) use ($quantity) {
                    return [
                        'selectedService' => $service['selectedService'] ?? '',
                        'unitPrice' => ($service['unitPrice'] ?? 0),
                    ];
                }, $services);

                $materialsAndServices = [
                    'materials' => $scaledMaterials,
                    'services' => $scaledServices,
                ];

                // Проверка на существование идентичного изделия в новой версии
                $existingItem = OrderItemModel::where('ItemPermID', $productData['ItemPermID'])->first();

                if ($existingItem) {
                    OrderItemModel::create([
                        'OrderUUID' => $uuid,
                        'Version' => $newVersion,
                        'ProductName' => $productData['productName'],
                        'Description' => $productData['description'] ?? null,
                        'Quantity' => $quantity,
                        'ManualPrice' => $productData['estimatedCost'],
                        'deadlineDate' => $productData['deadlineDate'],
                        'WorkStatus' => 1, // Значение по умолчанию, как обсуждалось ранее
                        'MaterialsAndServices' => json_encode($materialsAndServices, JSON_UNESCAPED_UNICODE),
                        'CreatedAt' => Carbon::now(),
                        'ItemPermID' => $existingItem->ItemPermID, // Присваиваем уникальный ItemPermID
                    ]);

                    // Если нужно удалять транзакции после редактирования.
                   // StockTransactionsModel::where('InOutID', $existingItem->ItemPermID)->delete();
                } else {
                    OrderItemModel::create([
                        'OrderUUID' => $uuid,
                        'Version' => $newVersion,
                        'ProductName' => $productData['productName'],
                        'Description' => $productData['description'] ?? null,
                        'Quantity' => $quantity,
                        'ManualPrice' => $productData['estimatedCost'],
                        'deadlineDate' => $productData['deadlineDate'],
                        'WorkStatus' => 1, // Значение по умолчанию, как обсуждалось ранее
                        'MaterialsAndServices' => json_encode($materialsAndServices, JSON_UNESCAPED_UNICODE),
                        'CreatedAt' => Carbon::now(),
                        'ItemPermID' => $productData['ItemPermID'], // Присваиваем уникальный ItemPermI
                    ]);
                    StockTransactionsModel::where('InOutID', $productData['ItemPermID'])->delete();
                }
            }

            // Получение файлов текущей версии заказа
            $currentFiles = FileModel::where('OrderUUID', $uuid)
                ->where('Version', $currentVersion)
                ->get();

            // Пометка текущих файлов как удаленных
            FileModel::where('OrderUUID', $uuid)
                ->where('Version', $currentVersion)
                ->update(['DeletedAt' => Carbon::now()]);

            // Копирование файлов на сервере и создание новых записей файлов с новой версией
            foreach ($currentFiles as $file) {
                $originalPath = $file->Filepath;
                $fileExtension = pathinfo($originalPath, PATHINFO_EXTENSION);
                $uniqueFileName = Str::uuid() . '.' . $fileExtension;
                $newDirectory = Path::base('Storage') . DIRECTORY_SEPARATOR . 'Orders' . DIRECTORY_SEPARATOR . $uuid . DIRECTORY_SEPARATOR . 'v' . $newVersion;

                if (!is_dir($newDirectory)) {
                    if (!mkdir($newDirectory, 0755, true)) {
                        throw new \Exception('Failed to create directory: ' . $newDirectory);
                    }
                }

                $newFilePath = $newDirectory . DIRECTORY_SEPARATOR . $uniqueFileName;

                // Копирование файла
                if (!copy($originalPath, $newFilePath)) {
                    throw new \Exception('Failed to copy file: ' . $file->Filename);
                }

                // Создание новой записи файла
                $newFile = $file->replicate();
                $newFile->FileID = (string) Str::uuid(); // Присвоение нового уникального FileID
                $newFile->Version = $newVersion;
                $newFile->Filepath = $newFilePath;
                $newFile->CreatedAt = Carbon::now();
                $newFile->save();
            }

            // Обработка удаления файлов, если необходимо
            if (!empty($filesToRemove)) {
                foreach ($filesToRemove as $filePath) {
                    // Удаление файла из файловой системы
                    if (file_exists($filePath)) {
                        if (!unlink($filePath)) {
                            throw new \Exception('Failed to delete file: ' . $filePath);
                        }
                    }

                    // Пометка файла как удаленного в базе данных
                    FileModel::where('Filepath', $filePath)
                        ->where('OrderUUID', $uuid)
                        ->where('Version', $currentVersion)
                        ->update(['DeletedAt' => Carbon::now()]);
                }
            }

            // Обработка добавления новых файлов
            if (!empty($filesToAdd)) {
                foreach ($filesToAdd as $file) {
                    $uniqueFileName = Str::uuid() . '_' . basename($file['name']);
                    $directory = Path::base('Storage') . DIRECTORY_SEPARATOR . 'Orders' . DIRECTORY_SEPARATOR . $uuid . DIRECTORY_SEPARATOR . 'v' . $newVersion;

                    if (!is_dir($directory)) {
                        if (!mkdir($directory, 0755, true)) {
                            throw new \Exception('Failed to create directory for new files: ' . $directory);
                        }
                    }

                    $filePath = $directory . DIRECTORY_SEPARATOR . $uniqueFileName;
                    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                        throw new \Exception('Failed to move uploaded file: ' . $file['name']);
                    }

                    // Проверка на существование файла с таким же именем
                    $existingFile = FileModel::where('OrderUUID', $uuid)
                        ->where('Version', $newVersion)
                        ->where('Filename', $file['name'])
                        ->first();

                    if (!$existingFile) {
                        // Создание записи файла
                        FileModel::create([
                            'FileID' => (string) Str::uuid(), // Присвоение нового уникального FileID
                            'OrderUUID' => $uuid,
                            'Filename' => $file['name'],
                            'Filepath' => $filePath,
                            'Size' => $file['size'],
                            'Type' => $file['type'] ?? null,
                            'CreatedAt' => Carbon::now(),
                            'Version' => $newVersion,
                        ]);
                    }
                }
            }

            // Завершение транзакции
            DB::commit();

            // Очистка буфера вывода перед отправкой заголовков
            if (ob_get_length()) {
                ob_clean();
            }

            self::setData(
                result: [
                    'message' => 'Order updated successfully.',
                    'order_uuid' => $uuid,
                    'version' => $newVersion
                ],
                statusCode: 200,
                status: 'success'
            );
        } catch (\Exception $e) {
            // Откат транзакции в случае ошибки
            DB::rollBack();

            // Очистка буфера вывода, чтобы избежать отправки частичных данных
            if (ob_get_length()) {
                ob_clean();
            }

            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }

    /**
     * Получение загруженных файлов из запроса.
     *
     * @return array
     */
    private function getFilesFromRequest(): array
    {
        $files = [];
        if (isset($_FILES['filesToAdd'])) {
            // Проверка, является ли filesToAdd массивом
            if (is_array($_FILES['filesToAdd']['name'])) {
                foreach ($_FILES['filesToAdd']['name'] as $index => $name) {
                    if ($_FILES['filesToAdd']['error'][$index] === UPLOAD_ERR_OK) {
                        $files[] = [
                            'name' => $_FILES['filesToAdd']['name'][$index],
                            'type' => $_FILES['filesToAdd']['type'][$index],
                            'tmp_name' => $_FILES['filesToAdd']['tmp_name'][$index],
                            'size' => $_FILES['filesToAdd']['size'][$index],
                        ];
                    }
                }
            } else {
                // Однофайловый ввод
                if ($_FILES['filesToAdd']['error'] === UPLOAD_ERR_OK) {
                    $files[] = [
                        'name' => $_FILES['filesToAdd']['name'],
                        'type' => $_FILES['filesToAdd']['type'],
                        'tmp_name' => $_FILES['filesToAdd']['tmp_name'],
                        'size' => $_FILES['filesToAdd']['size'],
                    ];
                }
            }
        }
        return $files;
    }















































































































































    /**
     * Обновление статусов заказа и изделий с созданием новой версии.
     * Также копируем файлы на новую версию и резервируем/списываем материалы.
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function updateOrderAndItemsStatus(): void
    {
        $response = [
            'message' => 'Order and items status updated successfully.'
        ];
        $statusCode = 200;
        $status = 'success';

        DB::beginTransaction();
        try {
            // 1. Данные из запроса
            $orderUUID      = Input::json('orderUUID');
            $newOrderStatus = Input::json('orderStatus');


            // Проверки
            if (
                empty($orderUUID) ||
                $newOrderStatus === null
            ) {
                throw new \Exception('Required data is missing.');
            }


            // 2. Находим последнюю версию заказа
            $currentOrder = OrderModel::where('OrderUUID', $orderUUID)
                ->orderBy('Version', 'desc')
                ->first();

            if (!$currentOrder) {
                throw new \Exception('Order not found.');
            }

            $finTransactionModel = new FinTransactionsModel();

            $taskFinTransaction = $finTransactionModel->table()
                ->where('TransactionID', '=', $currentOrder->OrderUUID)
                ->where('Status', '=', 1)
                ->get();

            $totalAmount = $taskFinTransaction->sum('Amount');

            $paymentStatus = 0; // 0 - неоплачен, 1 - полностью оплачен, 2 - частично оплачен

            if ((float) $totalAmount >= (float) $currentOrder->EstimatedCost) {
                $paymentStatus = 1; // Полностью оплачен
            } elseif ((float) $totalAmount > 0) {
                $paymentStatus = 2; // Частично оплачен
            }

            // 3. Проверяем логику статусов оплаты
            if ($paymentStatus === 0) {
                throw new \Exception('Невозможно установить этот статус для неоплаченного заказа.');
            }

            // Если заказ частично оплачен, разрешаем только определённые статусы (например, 1, 2, 3)
            $allowedStatusesForPartialPayment = [1, 2];



            if ($paymentStatus === 2 && !in_array($newOrderStatus, $allowedStatusesForPartialPayment)) {
                throw new \Exception('Частично оплаченный заказ может быть переведен только в статус: ' . implode(', ', $allowedStatusesForPartialPayment));
            }



            $oldVersion = $currentOrder->Version;
            $newVersion = $oldVersion + 1;

            // 4. Создаём новую запись заказа (новая версия)
            $newOrder = $currentOrder->replicate();
            $newOrder->Version       = $newVersion;
            $newOrder->OrderStatus   = $newOrderStatus;
            $newOrder->CreatedAt     = Carbon::now();
            $newOrder->save();


            // 6. Загружаем все изделия старой версии
            $oldItems = OrderItemModel::where('OrderUUID', $orderUUID)
                ->where('Version', $oldVersion)
                ->get();

            // Создаём карту "ItemPermID -> WorkStatus" из входных данных,
            // но только если заказ НЕ финальный. Если финальный — у всех будет "3" (или ваш код).
            $statusMap = [];


            // 7. Готовим склады для резервации/списания (активные склады компании)
            $warehouses = WarehouseModel::whereNull('deleted_at')
                ->where('Status', 1)
                ->get();
            if ($warehouses->isEmpty()) {
                throw new \Exception('No active warehouses found for the given CorrespondentID.');
            }

            // 8. Копируем каждое изделие **одним циклом** в новую версию
            foreach ($oldItems as $oldItem) {
                $itemPermID = $oldItem->ItemPermID;

                // 8.1. Проверяем, не было ли уже создано изделие в новой версии с тем же ItemPermID
                $duplicateExists = OrderItemModel::where('OrderUUID', $orderUUID)
                    ->where('Version', $newVersion)
                    ->where('ItemPermID', $itemPermID)
                    ->exists();
                if ($duplicateExists) {
                    // Пропускаем, чтобы не плодить дубликаты
                    continue;
                }

                // 8.3. Реплицируем
                $newItem = $oldItem->replicate();
                $newItem->Version    = $newVersion;
                $newItem->CreatedAt  = Carbon::now();
                $newItem->save();
            }

            // 9. Копируем файлы одной итерацией
            $oldFiles = FileModel::where('OrderUUID', $orderUUID)
                ->where('Version', $oldVersion)
                ->get();
            foreach ($oldFiles as $oldFile) {
                // Если опасаетесь дубликатов по файлам, можно аналогично сделать check
                // Но предположим, что копируем один раз
                $newFile = $oldFile->replicate();
                $newFile->Version   = $newVersion;
                $newFile->CreatedAt = Carbon::now();
                $newFile->save();
            }

            // 10. Подтверждаем транзакцию
            DB::commit();
        } catch (\Exception $e) {
            // Откат
            DB::rollBack();

            $response   = ['error' => $e->getMessage()];
            $statusCode = 500;
            $status     = 'error';
        }

        // Возвращаем ответ
        self::setData(
            result: $response,
            statusCode: $statusCode,
            status: $status
        );
    }


    /**
     * Обновляет статус изделия в заказе и соответствующие транзакции склада
     *
     * Обновляет WorkStatus изделия по ItemPermID и меняет тип транзакций склада.
     * Проверяет статус оплаты заказа — если не оплачен, запрещает обновление.
     *
     * Требует:
     * - orderUUID (uuid заказа)
     * - ItemPermID (int — идентификатор изделия)
     * - newStatus (int — новый статус работы)
     *
     * @param ValidatedRequest $request
     * @return void
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/orders/update-item-status')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    #[Validate([
        'orderUUID'   => ['required' => true, 'type' => 'uuid'],
        'ItemPermID'  => ['required' => true, 'type' => 'uuid'],
        'newStatus'   => ['required' => true, 'type' => 'string'],
    ])]
    public static function updateItemsStatus(ValidatedRequest $request): void
    {
        $response = ['message' => 'Order and items status updated successfully.'];
        $statusCode = 200;
        $status = 'success';

        DB::beginTransaction();
        try {
            // Получаем и валидируем входные данные
            $request->check();

            $orderUUID     = $request->input('orderUUID');
            $ItemPermID    = $request->input('ItemPermID');
            $newOrderStatus = $request->input('newStatus');

            // Получаем последнюю версию заказа по UUID
            $currentOrder = OrderModel::query()
                ->where('OrderUUID', $orderUUID)
                ->orderByDesc('Version')
                ->first();

            if (!$currentOrder) {
                throw new \Exception('Order not found');
            }

            // Получаем подтверждённые финансовые транзакции по заказу
            $finTransactionModel = new FinTransactionsModel();
            $taskFinTransaction = $finTransactionModel->table()
                ->where('TransactionID', '=', $currentOrder->OrderUUID)
                ->where('Status', '=', 1)
                ->get();

            // Подсчитываем сумму всех транзакций
            $totalAmount = $taskFinTransaction->sum('Amount');
            $paymentStatus = ($totalAmount > 0) ? 1 : 0;

            // Бизнес-логика: если нет оплаты, запрещаем изменение
            if ($paymentStatus !== 1) {
                throw new \Exception('Order is not paid. Status change is not allowed.');
            }

            // Обновляем статус изделия во всех версиях
            OrderItemModel::query()
                ->where('ItemPermID', $ItemPermID)
                ->update(['WorkStatus' => $newOrderStatus]);

            // Обновляем тип транзакций по статусу
            $stockType = in_array($newOrderStatus, [3, 4]) ? 2 : 3;

            StockTransactionsModel::query()
                ->where('InOutID', $ItemPermID)
                ->update(['StockTransactionType' => $stockType]);

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            $response = ['error' => $e->getMessage()];
            $statusCode = 500;
            $status = 'error';
        }

        self::api(
            $response,
            $statusCode,
            $status
        );
    }


    /**
     * Проверяет доступность материала для списания только на складах текущей компании.
     *
     * @param array $material Массив с данными о материале.
     * @param Collection $warehouses Список складов текущей компании.
     * @param float $quantity Количество, необходимое для списания.
     * @param string $productName Название изделия.
     * @throws \Exception Если материала недостаточно на складе.
     */
    private function validateMaterialAvailability(array $material, $warehouses, string $productName): void
    {
        $materialID = $material['selectedMaterial'];
        $requiredQuantity = $material['quantity'];

        // Получаем доступное количество материала только на складах текущей компании
        $totalAvailable = DB::table('StockTransactions')
            ->join('Warehouse', 'StockTransactions.WarehouseID', '=', 'Warehouse.WarehouseID')
            ->whereIn('Warehouse.WarehouseID', $warehouses->pluck('WarehouseID'))
            ->where('StockTransactions.ProductID', $materialID)
            ->where('Warehouse.OwnerID', $warehouses->first()->OwnerID)
            ->sum(DB::raw('CASE
            WHEN StockTransactionType = 1 THEN Quantity
            WHEN StockTransactionType IN (2, 3) THEN Quantity
            ELSE 0 END'));


        if ($totalAvailable < $requiredQuantity) {
            throw new \Exception("Недостаточно материала '{$materialID}' для изделия '{$productName}'. 
                              Требуется: {$requiredQuantity}, доступно: {$totalAvailable}");
        }
    }


    /**
     * Удаляет существующие транзакции резервации и списания для изделия на всех складах компании.
     *
     * @param string $orderItemID Идентификатор изделия.
     * @param $warehouses
     * @return void
     */
    private function deleteExistingStockTransactions(string $orderItemID, $warehouses): void
    {
        foreach ($warehouses as $warehouse) {
            StockTransactionsModel::where('InOutID', $orderItemID)
                ->where('WarehouseID', $warehouse->WarehouseID)
                ->whereIn('StockTransactionType', [2, 3]) // 2 для списания, 3 для резервации
                ->delete();
        }
    }

    /**
     * Проверяем, требует ли статус резервирования (пример).
     *
     * @param int $status Статус изделия.
     * @return bool Возвращает true, если статус требует резервирования, иначе false.
     */
    private function isStatusRequiringMaterialReservation(int $status): bool
    {
        // Допустим, статус 2 = "В производстве"
        return in_array($status, [2]);
    }

    /**
     * Проверяем, финальный ли статус (пример).
     *
     * @param int $status Статус изделия.
     * @return bool Возвращает true, если статус финальный, иначе false.
     */
    private function isStatusFinal(int $status): bool
    {
        // Допустим, статус 3 = "Завершено"
        return in_array($status, [3]);
    }

    /**
     * Резервируем материал.
     */
    private function reserveMaterial(array $material, $warehouses, string $itemPermID, float $itemQuantity): void
    {
        $materialID = $material['selectedMaterial'];
        $requiredQuantity = (float)$material['quantity'];

        // Проходим по всем складам и стараемся зарезервировать нужное количество
        foreach ($warehouses as $warehouse) {
            $availableQuantity = $this->getAvailableStock($materialID, $warehouse->WarehouseID);
            if ($availableQuantity <= 0) {
                continue;
            }

            $quantityToReserve = min($requiredQuantity, $availableQuantity);
            if ($quantityToReserve <= 0) {
                continue;
            }

            // Записываем транзакцию
            StockTransactionsModel::create([
                'StockTransactionDate' => Carbon::now(),
                'StockTransactionType' => 3, // 3 = Резервация
                'ProductID'            => $materialID,
                'WarehouseID'          => $warehouse->WarehouseID,
                'InOutID'              => $itemPermID,
                'Quantity'             => -abs($quantityToReserve),
                'Weight'               => null,
                'Price'                => ((float)$material['unitPrice']) * $itemQuantity,
            ]);

            $requiredQuantity -= $quantityToReserve;
            if ($requiredQuantity <= 0) {
                break;
            }
        }

        // Если после всех складов всё ещё > 0, можно сгенерировать Exception или лог
        // throw new \Exception('Недостаточно материала для резервации ...');
    }

    /**
     * Списываем материал.
     */
    private function writeOffMaterial(
        array $material,
              $warehouses,
        string $itemPermID,
        float $itemQuantity,
        string $productName
    ): void {
        $materialID       = $material['selectedMaterial'];
        $requiredQuantity = (float)$material['quantity'];

        foreach ($warehouses as $warehouse) {
            $availableQuantity = $this->getAvailableStock($materialID, $warehouse->WarehouseID);
            if ($availableQuantity <= 0) {
                continue;
            }
            $quantityToWriteOff = min($requiredQuantity, $availableQuantity);
            if ($quantityToWriteOff <= 0) {
                continue;
            }

            StockTransactionsModel::create([
                'StockTransactionDate' => Carbon::now(),
                'StockTransactionType' => 2, // 2 = списание
                'ProductID'            => $materialID,
                'WarehouseID'          => $warehouse->WarehouseID,
                'InOutID'              => $itemPermID,
                'Quantity'             => -abs($quantityToWriteOff),
                'Weight'               => null,
                'Price'                => ((float)$material['unitPrice']) * $itemQuantity,
            ]);

            $requiredQuantity -= $quantityToWriteOff;
            if ($requiredQuantity <= 0) {
                break;
            }
        }

        // Если после всех складов ещё осталось, бросаем ошибку или логируем
        if ($requiredQuantity > 0) {
            $materialName = (new ProductModel())->table()
                ->where('ProductID', $materialID)
                ->value('ProductName');

            throw new \Exception(sprintf(
                'Недостаточно материала (%s) на складах для списания для изделия "%s". Нужно ещё: %s',
                $materialName ?? 'Неизвестный материал',
                $productName,
                $requiredQuantity
            ));
        }
    }


    /**
     * Получает доступное количество материала на складе.
     *
     * @param string $productID Идентификатор материала.
     * @param string $warehouseID Идентификатор склада.
     * @return float Доступное количество.
     */
    private function getAvailableStock(string $productID, string $warehouseID): float
    {
        // Суммируем все входящие транзакции (StockTransactionType = 1 - приход)
        $totalIn = StockTransactionsModel::where('ProductID', $productID)
            ->where('WarehouseID', $warehouseID)
            ->where('StockTransactionType', 1) // 1 для прихода
            ->sum('Quantity');

        // Суммируем все исходящие транзакции (StockTransactionType = 2 - списание, 3 - резервация)
        $totalOut = StockTransactionsModel::where('ProductID', $productID)
            ->where('WarehouseID', $warehouseID)
            ->whereIn('StockTransactionType', [2, 3]) // 2 для списания, 3 для резервации
            ->sum('Quantity');

        // Возвращаем доступное количество
        return $totalIn - $totalOut;
    }

    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/orders/getMaterialDebt')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    public function getMaterialDebt(): void
    {
        try {
            // 1) Получаем заказы с последней версией
            $orders = DB::table('Orders as o')
                ->joinSub(
                    DB::table('Orders')
                        ->select('OrderUUID', DB::raw('MAX(Version) as LatestVersion'))
                        ->groupBy('OrderUUID'),
                    'LatestOrders',
                    function ($join) {
                        $join->on('o.OrderUUID', '=', 'LatestOrders.OrderUUID')
                            ->whereColumn('o.Version', '=', 'LatestOrders.LatestVersion');
                    }
                )
                ->select([
                    'o.OrderUUID',
                    'o.OrderName',
                    'o.Version as LatestVersion',
                ])
                ->whereIn('o.OrderStatus', [1, 2, 3])
                ->get();

            // 2) Язык
            $language = Input::json('language') ?? 'ru';

            $materialDebt = [];

            // 3) Перебираем заказы
            foreach ($orders as $order) {
                $items = DB::table('OrderItems')
                    ->select(['MaterialsAndServices', 'ProductName', 'OrderUUID', 'Quantity', 'WorkStatus'])
                    ->where('OrderUUID', $order->OrderUUID)
                    ->where('Version', $order->LatestVersion)
                    ->where('WorkStatus', '!=', '3')
                    ->get();

                foreach ($items as $item) {
                    $materials = json_decode($item->MaterialsAndServices, true)['materials'] ?? [];
                    $detailedMaterials = [];

                    foreach ($materials as $material) {
                        $requiredQuantity = $material['quantity'];
                        $materialID = $material['selectedMaterial'];

                        $materialName = DB::table('ProductTranslations')
                            ->where('ProductID', $materialID)
                            ->where('LanguageCode', $language)
                            ->value('Name') ?? 'Unknown';

                        $sumAvailable = DB::table('StockTransactions as st')
                            ->where('st.ProductID', $materialID)
                            ->sum(DB::raw('CASE WHEN st.StockTransactionType != 3 THEN st.Quantity ELSE 0 END'));

                        $sumReserved = DB::table('StockTransactions as st')
                            ->where('st.ProductID', $materialID)
                            ->where('st.InOutID', $order->OrderUUID)
                            ->sum(DB::raw('CASE WHEN st.StockTransactionType = 3 THEN st.Quantity ELSE 0 END'));

                        $availableQuantity = $sumAvailable - $sumReserved;
                        $debt = max(0, $requiredQuantity - $availableQuantity);

                        if ($debt > 0) {
                            $detailedMaterials[] = [
                                'MaterialID'        => $materialID,
                                'MaterialName'      => $materialName,
                                'RequiredQuantity'  => $requiredQuantity,
                                'AvailableQuantity' => $availableQuantity,
                                'Debt'              => $debt,
                            ];
                        }
                    }

                    if (!empty($detailedMaterials)) {
                        $materialDebt[] = [
                            'OrderUUID'   => $order->OrderUUID,
                            'OrderName'   => $order->OrderName,
                            'ProductName' => $item->ProductName,
                            'Materials'   => $detailedMaterials,
                        ];
                    }
                }
            }

            self::api(['materialDebt' => $materialDebt]);

        } catch (\Exception $e) {
            self::api(['error' => $e->getMessage()], 500, 'error');
        }
    }



    public function deletedMaterialsItem(): void
    {
        try {
            // Проверяем авторизацию
            if (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                throw new \Exception('Authorization failed');
            }

            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $jwt = explode(" ", $authHeader)[1] ?? null;

            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }

            // Получаем входные данные
            $materialID = Input::json('materialID');

            $ItemPermID = Input::json('ItemPermID');

            // Создаем модель заказа
            $orderItemModel = new OrderItemModel();

            // Получаем заказ
            $selectItem = $orderItemModel->table()
                ->where('ItemPermID', '=', $ItemPermID)
                ->first();

            if (!$selectItem) {
                throw new \Exception("Item with ID {$ItemPermID} not found.");
            }

            // Преобразуем JSON-строку MaterialsAndServices в массив
            $materialsAndServices = json_decode($selectItem->MaterialsAndServices, true);

            if (!isset($materialsAndServices['materials'])) {
                throw new \Exception("Materials data is missing or invalid.");
            }

            // Удаляем материал по его ID
            $filteredMaterials = array_filter($materialsAndServices['materials'], function ($material) use ($materialID) {
                return $material['selectedMaterial'] !== $materialID;
            });

            // Обновляем массив MaterialsAndServices
            $materialsAndServices['materials'] = array_values($filteredMaterials); // Переиндексация массива

            // Преобразуем массив обратно в JSON
            $updatedMaterialsAndServices = json_encode($materialsAndServices, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $transactionModel = new StockTransactionsModel();

            $transactionModel->table()
                ->where('InOutID', '=', $ItemPermID)
                ->where('ProductID', '=', $materialID)
                ->delete();

            // Обновляем запись в базе данных
            $orderItemModel->table()
                ->where('ItemPermID', '=', $ItemPermID)
                ->update(['MaterialsAndServices' => $updatedMaterialsAndServices]);

            self::setData([
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }


    public function deletedServiceItem(): void
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

            // Получаем входные данные
            $serviceID = Input::json('serviceID');
            $ItemPermID = Input::json('ItemPermID');
            $itemUUID = Input::json('itemUUID'); // Если нужно, чтобы условие по InOutID базировалось на itemUUID
            if (!$serviceID) {
                throw new \Exception('ServiceID is required.');
            }
            if (!$ItemPermID) {
                throw new \Exception('ItemPermID is required.');
            }
            if (!$itemUUID) {
                throw new \Exception('itemUUID is required.');
            }

            // Получаем заказ по ItemPermID
            $orderItemModel = new OrderItemModel();
            $orderItem = $orderItemModel->table()
                ->where('ItemPermID', '=', $ItemPermID)
                ->first();
            if (!$orderItem) {
                throw new \Exception("Item with ID {$ItemPermID} not found.");
            }


            // Проверяем, есть ли транзакции по услуге
            $transactionModel = new StockTransactionsModel();
            $stockTransactions = $transactionModel->table()
                ->where('InOutID', '=', $itemUUID)
                ->where('ProductID', '=', $serviceID)
                ->where('IsContractor', '=', 1)
                ->get();

            if (!$stockTransactions->isEmpty()) {
                self::setData(
                    result: ["message" => "1"],
                    status: 'error'
                );
            }


            // Обновляем JSON-поле MaterialsAndServices: удаляем услугу с заданным ID из раздела "services"
            $materialsAndServices = json_decode($orderItem->MaterialsAndServices, true);
            if (!isset($materialsAndServices['services'])) {
                throw new \Exception("Services data is missing or invalid.");
            }
            $filteredServices = array_filter($materialsAndServices['services'], function ($service) use ($serviceID) {
                return $service['selectedService'] !== $serviceID;
            });
            $materialsAndServices['services'] = array_values($filteredServices);
            $updatedJSON = json_encode($materialsAndServices, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $orderItemModel->table()
                ->where('ItemPermID', '=', $ItemPermID)
                ->update(['MaterialsAndServices' => $updatedJSON]);

            self::setData(
                result: ["message" => "Service and related transactions deleted successfully"],
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




    public function addMaterialsItem(): void
    {
        try {
            // Проверяем авторизацию
            if (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                throw new \Exception('Authorization failed');
            }

            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $jwt = explode(" ", $authHeader)[1] ?? null;

            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }

            // Получаем входные данные
            $orderItemUUID = Input::json('itemUUID');
            $newMaterial = Input::json('material');

            if (!$orderItemUUID || !$newMaterial) {
                throw new \Exception("Неверные данные для добавления материала.");
            }

            // Создаем модель заказа
            $orderItemModel = new OrderItemModel();

            // Получаем текущую запись
            $selectItem = $orderItemModel->table()
                ->where('OrderItemID', '=', $orderItemUUID)
                ->first();

            if (!$selectItem) {
                throw new \Exception("Элемент заказа не найден.");
            }

            // Декодируем JSON
            $materialsAndServices = json_decode($selectItem->MaterialsAndServices, true);

            // Проверяем существование массива materials
            if (!isset($materialsAndServices['materials'])) {
                $materialsAndServices['materials'] = [];
            }

            // Добавляем новый материал
            $materialsAndServices['materials'][] = [
                'selectedMaterial' => $newMaterial['id'],
                'quantity' => $newMaterial['quantity'],
                'unitPrice' => $newMaterial['unitPrice'],
            ];

            // Обновляем запись в базе данных
            $orderItemModel->table()
                ->where('OrderItemID', '=', $orderItemUUID)
                ->update(['MaterialsAndServices' => json_encode($materialsAndServices, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]);

            self::setData([
                'status' => 'success',
                'message' => "Материал добавлен.",
                'updatedMaterialsAndServices' => $materialsAndServices,
            ]);
        } catch (\Exception $e) {
            self::setData(
                result: ['error' => $e->getMessage()],
                statusCode: 500,
                status: 'error'
            );
        }
    }

    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/orders/getActiveTasks')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    public function getActiveTasks(): void
    {
        try {
            $orderModel = new OrderModel();

            // 🔹 Получаем задачи
            $tasks = $orderModel->table()
                ->select([
                    'o.OrderUUID',
                    'o.OrderName',
                    'o.EstimatedCost',
                    'o.CreatedAt',
                    'o.GeneralDeadlineDate',
                    'o.OrderStatus',
                    'o.PartnerID',
                    'o.Version'
                ])
                ->whereIn('o.OrderStatus', [1, 2, 3, 4, 5]) // Фильтруем только нужные статусы
                ->whereRaw('o.Version = (SELECT MAX(Version) FROM orders WHERE OrderUUID = o.OrderUUID)')
                ->orderBy('o.CreatedAt', 'desc') // Сортируем по дате создания
                ->from('orders as o')
                ->get();


            // 🔹 Разбиваем задачи по статусам
            $tasksGrouped = [
                'new' => [],
                'in_progress' => [],
                'ready' => []
            ];

            foreach ($tasks as $task) {
                if ($task->OrderStatus == 1) {
                    $tasksGrouped['new'][] = $task;
                } elseif ($task->OrderStatus == 2) {
                    $tasksGrouped['in_progress'][] = $task;
                } else {
                    $tasksGrouped['ready'][] = $task;
                }
            }

            // ✅ Возвращаем данные
            self::api($tasksGrouped);
        } catch (\Exception $e) {
            self::api(['message' => $e->getMessage()], 500, 'error');
        }
    }

}
