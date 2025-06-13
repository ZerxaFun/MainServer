<?php

namespace Modules\API\Controller;

use Carbon\Carbon;
use Core\Routing\Attributes\HttpMethod;
use Core\Services\Attributes\Validate;
use Core\Services\Auth\Attributes\Authorize;
use Core\Services\Http\Input;
use Controller;
use Core\Services\Http\ValidatedRequest;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use Modules\API\Model\CompanyModel;
use Modules\API\Model\FinTransactionsModel;
use Modules\API\Model\FinTransactionViewModel;
use Modules\API\Model\MastersModel;
use Modules\API\Model\OrderItemModel;
use Modules\API\Model\OrderModel;
use Modules\API\Model\PartnerModel;
use Modules\API\Model\PaymentAccountModel;
use Modules\API\Model\ProductModel;
use Modules\API\Model\ProductTranslationsModel;
use Modules\API\Model\StockTransactionsModel;

/**
 * PaymentAccountController
 */
class PaymentAccountController extends Controller
{
    /**
     * Получение всех активных платежных счетов
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function getPaymentAccountsList(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
                JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                $paymentAccountModel = new PaymentAccountModel();
                $finTransactionsModel = new FinTransactionsModel();

                // Получение списка счетов с дополнительной информацией о компании
                $paymentAccounts = $paymentAccountModel->table()
                    ->select('Accounts.AccountID', 'Accounts.Bank', 'Accounts.Currency', 'Accounts.Description', 'Accounts.Status', 'Company.OwnerName', 'Company.OfficialName')
                    ->where('Accounts.deleted_at', '=', null)
                    ->join('Company', 'Accounts.OwnerID', '=', 'Company.OwnerID')
                    ->get();

                // Добавляем фин данные для каждого аккаунта
                foreach ($paymentAccounts as $account) {
                    $accountId = $account->AccountID;

                    // Расчет баланса
                    $balanceData = $finTransactionsModel->table()
                        ->selectRaw('
                        SUM(CASE WHEN FinTransactionType IN (2, 3) THEN Amount ELSE 0 END) -
                        SUM(CASE WHEN FinTransactionType IN (1, 4) THEN Amount ELSE 0 END) AS balance
                    ')
                        ->where('AccountID', '=', $accountId)
                        ->first();

                    // Данные за текущий месяц
                    $monthlyData = $finTransactionsModel->table()
                        ->selectRaw('
                        SUM(CASE WHEN FinTransactionType IN (2, 3) THEN Amount ELSE 0 END) AS monthlyIncome,
                        SUM(CASE WHEN FinTransactionType IN (1, 4) THEN Amount ELSE 0 END) AS monthlyExpense
                    ')
                        ->where('AccountID', '=', $accountId)
                        ->whereRaw('MONTH(FinTransactionDate) = MONTH(GETDATE())')
                        ->whereRaw('YEAR(FinTransactionDate) = YEAR(GETDATE())')
                        ->where('Status', '=', 1)
                        ->first();

                    // Добавляем данные в объект аккаунта
                    $account->balance = $balanceData->balance ?? 0;
                    $account->monthlyIncome = $monthlyData->monthlyIncome ?? 0;
                    $account->monthlyExpense = $monthlyData->monthlyExpense ?? 0;
                }

                self::setData(result: ['paymentAccount' => $paymentAccounts], status: 'success');
            } else {
                self::setData(result: ['paymentAccount' => [], 'error' => 'auth failed'], statusCode: 500, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['paymentAccount' => [], 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }



    /**
     * Получение всех активных платежных счетов по компании
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function getPaymentAccountsListShort(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];

                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));


                $paymentAccountModel = new PaymentAccountModel();

                // Выполняем запрос с объединением и подсчетом количества счетов для каждой компании
                $paymentAccounts = $paymentAccountModel->table()
                    ->select(['Currency', 'Bank', 'Description', 'AccountID'])
                    ->where('OwnerID', '=', Input::json('OwnerID'))
                    ->where('deleted_at', '=', null)
                    ->where('Status', '=', 1)
                    ->get();


                self::setData(result: ['payments' => $paymentAccounts], status: 'success');

            } else {
                self::setData(result: ['payments' => [], 'error' => 'auth failed'], statusCode: 500, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['payments' => [], 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }

    /**
     * Получение всех активных платежных счетов
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function deletedPaymentAccounts(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];

                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));


                $paymentAccountModel = new PaymentAccountModel();

                $paymentAccountModel->table()
                    ->where('AccountID', '=', Input::json('AccountID'))
                    ->update(['deleted_at' => Carbon::now()]);


                self::setData(result: ['message' => 'Платежный аккаунт был успешно удален'], status: 'success');

            } else {
                self::setData(result: ['error' => 'auth failed'], statusCode: 500, status: 'error');
            }
        } catch (\Exception $e) {
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }


    /**
     * Метод создания новых платежных счетов компании
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function createPaymentAccounts(): void
    {
        try {
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];

                $decode = JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

                // Получаем данные из тела запроса
                $data = Input::json();

                $selectCompany = $data['CompanyID'] ?? null;

                if (!$selectCompany) {
                    // Код сообщения: 1 - Отсутствует CompanyID
                    self::setData(result: ['message' => 1], statusCode: 400, status: 'error');
                } else {
                    $companyModel = new CompanyModel();
                    $paymentAccountModel = new PaymentAccountModel();

                    // Проверяем, существует ли компания в базе данных
                    $selectCompanyDB = $companyModel->table()
                        ->where('OwnerID', '=', $selectCompany)
                        ->where('deleted_at', '=', null)
                        ->first();

                    if ($selectCompanyDB === null) {
                        // Код сообщения: 2 - Компания не найдена или удалена
                        self::setData(result: ['message' => 2], statusCode: 404, status: 'error');
                    } else {
                        $paymentAccounts = $data['paymentAccounts'] ?? null;

                        if (!is_array($paymentAccounts) || empty($paymentAccounts)) {
                            // Код сообщения: 3 - Нет данных платежных аккаунтов для сохранения
                            self::setData(result: ['message' => 3], statusCode: 400, status: 'error');
                        } else {
                            foreach ($paymentAccounts as $account) {
                                // Проверяем обязательные поля
                                $bank = $account['Bank'] ?? null;
                                $currency = $account['Currency'] ?? null;
                                $description = $account['Description'] ?? '';
                                $status = isset($account['Status']) ? $account['Status'] : '1';

                                if (!$bank || !$currency) {
                                    // Код сообщения: 4 - Отсутствуют обязательные поля в платежном аккаунте
                                    self::setData(result: ['message' => 4], statusCode: 400, status: 'error');
                                    break; // Прерываем цикл, так как данные некорректны
                                } else {
                                    // Проверка кода валюты (должен состоять из 3 символов)
                                    $currency = strtoupper(trim($currency));
                                    if (strlen($currency) !== 3) {
                                        // Код сообщения: 5 - Некорректный код валюты
                                        self::setData(result: ['message' => 5], statusCode: 400, status: 'error');
                                        break; // Прерываем цикл
                                    } else {
                                        // Подготовка данных для вставки
                                        $accountData = [
                                            'OwnerID'   => $selectCompany,
                                            'Bank'        => trim($bank),
                                            'Currency'    => $currency,
                                            'Description' => trim($description),
                                            'Status'      => $status
                                        ];

                                        // Вставка платежного аккаунта в базу данных
                                        $paymentAccountModel->table()->insert($accountData);
                                    }
                                }
                            }

                            // Если не было ошибок, возвращаем успешный ответ
                            if (!isset($accountData)) {
                                // Если вставка не произошла, значит была ошибка ранее
                                // Сообщение уже было установлено, ничего не делаем
                            } else {
                                // Код сообщения: 6 - Платежные аккаунты успешно сохранены
                                self::setData(result: ['message' => 6], status: 'success');
                            }
                        }
                    }
                }
            } else {
                // Код сообщения: 7 - Не авторизован
                self::setData(result: ['message' => 7], statusCode: 401, status: 'error');
            }
        } catch (\Exception $e) {
            // Код сообщения: 8 - Ошибка при сохранении данных
            self::setData(result: ['message' => 8, 'error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }


    /**
     * Обновление записи
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function updatePaymentAccount(): void
    {
        try {
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                self::setData(result: ['error' => 'auth failed'], statusCode: 401, status: 'error');
            }

            $jwt = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1];
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            $data = Input::json();

            $paymentAccountModel = new PaymentAccountModel();
            $paymentAccountModel->table()
                ->where('AccountID', '=', $data['AccountID'])
                ->update([
                    'Bank' => $data['Bank'],
                    'Currency' => strtoupper($data['Currency']),
                    'Description' => $data['Description'] ?? '',
                    'Status' => $data['Status'],
                ]);

            self::setData(result: ['message' => 'Payment account updated'], status: 'success');
        } catch (\Exception $e) {
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }


    /**
     * Возвращает список активных счетов.
     *
     * @param ValidatedRequest $request
     * @return void
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/payment-account/get-company')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    public static function getPaymentCompany(): void
    {
        try {
            // Получаем активные счета компании
            $paymentAccounts = PaymentAccountModel::query()
                ->select([
                    'AccountID',
                    'Bank',
                    'Currency',
                    'Description',
                    'Status',
                ])
                ->whereNull('deleted_at')
                ->get();

            if ($paymentAccounts->isEmpty()) {
                self::api(
                     ['message' => 'No accounts found'],
                    404,
                    'error'
                );
            }

            self::api(
                ['paymentAccounts' => $paymentAccounts]
            );
        } catch (\Throwable $e) {
            self::api(
                ['error' => $e->getMessage()],
                500,
                'error'
            );
        }
    }






    public function getTransactionHistory(): void
    {
        try {
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                self::setData(result: ['error' => 'auth failed'], statusCode: 401, status: 'error');
            }

            $jwtParts = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            if (count($jwtParts) != 2) {
                self::setData(result: ['error' => 'Invalid Authorization Header'], statusCode: 401, status: 'error');
            }

            $jwt = $jwtParts[1];
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            $data = Input::json();
            $UUID = $data['uuid'] ?? null;
            $startDate = $data['startDate'] ?? null;
            $endDate = $data['endDate'] ?? null;
            $language = $data['language'] ?? 'ru';

            if (!$UUID || !$startDate || !$endDate) {
                self::setData(result: ['error' => 'UUID, StartDate и EndDate должны быть заданы'], statusCode: 400, status: 'error');
            }

            $startDateObj = new \DateTime($startDate);
            $endDateObj = new \DateTime($endDate);
            $endDateObj->modify('first day of next month');

            $finTransaction = new FinTransactionsModel();

            $transactions = $finTransaction->table()
                ->where('AccountID', $UUID)
                ->where('Status', 1)
                ->whereBetween('FinTransactionDate', [$startDate, $endDate . ' 23:59:59'])
                ->orderBy('FinTransactionDate', 'desc')
                ->get();

            $CompanyModel = new CompanyModel();
            $MasterModel = new MastersModel();
            $OrderModel = new OrderModel();
            $StockTransactionModel = new StockTransactionsModel();
            $PartnerModel = new PartnerModel();
            $ProductModel = new ProductModel();
            $OrderItemsModel = new OrderItemModel();
            $ProductTranslationsModel = new ProductTranslationsModel();

            $masters = $MasterModel->table()->get()->keyBy('MasterID');
            $partners = $PartnerModel->table()->get()->keyBy('PartnerID');
            $products = $ProductModel->table()->get()->keyBy('ProductID');
            $productTranslations = $ProductTranslationsModel->table()
                ->where('LanguageCode', $language)
                ->get()->keyBy('ProductID');
            $companies = $CompanyModel->table()->get()->keyBy('OwnerID');
            $orders = $OrderModel->table()->get()->keyBy('OrderUUID');
            $stockTransactions = $StockTransactionModel->table()->get()->keyBy('StockTransactionID');

            $transactionsList = [];

            foreach ($transactions as $transaction) {
                $buyServiceItem = $stockTransactions->firstWhere('StockID', $transaction->TransactionID);

                if ($transaction->FinTransactionType === '1') {
                    if ($transaction->IsContractor === '1') {
                        $master = $masters[$transaction->CorrespondentID] ?? null;
                        $product = $buyServiceItem ? ($products[$buyServiceItem->ProductID] ?? null) : null;
                        $translation = $product && isset($productTranslations[$product->ProductID]) ? $productTranslations[$product->ProductID]->Name : ($product->ProductName ?? 'Неизвестный товар');
                        $SelectOrder = $OrderItemsModel->table()->where('OrderItemID', $buyServiceItem->InOutID)->first() ?? null;
                        $OrderName = $OrderModel->table()->where('OrderUUID', $SelectOrder->OrderUUID)->first() ?? null;

                        $transactionsList[] = [
                            'FinTransactionDate' => $transaction->FinTransactionDate,
                            'IsContractor' => $transaction->IsContractor,
                            'Partner' => [
                                'name' => $master->name ?? 'Неизвестный',
                                'type' => $master->type ?? 'Неизвестно',
                                'MasterID' => $master->MasterID ?? null,
                            ],
                            'item' => [
                                'ProductID' => $product->ProductID ?? null,
                                'ProductName' => $translation,
                            ],
                            'order' => [
                                'OrderUUID' => $SelectOrder->OrderUUID ?? null,
                                'OrderItemName' => $SelectOrder->ProductName ?? '',
                                'OrderName' => $OrderName->OrderName ?? '',
                            ],
                            'Price' => $transaction->Amount,
                            'IsCurrency' => $transaction->IsCurrency,
                            'Currency' => $transaction->IsCurrency ? ($account->Currency ?? 'GEL') : 'GEL',
                            'ForeignAmount' => $transaction->IsCurrency ? $transaction->ForeignAmount : null,
                            'ExchangeRate' => $transaction->IsCurrency ? $transaction->ExchangeRate : null,
                            'Quantity' => $buyServiceItem->Quantity ?? 0,
                            'FinTransactionType' => '1',
                            'message' => '0',
                            'comments'  => $transaction->Comments,
                            'File'  => $transaction->File,
                        ];
                    } else {
                        $buyItem = $stockTransactions[$transaction->TransactionID] ?? null;
                        $partner = $buyItem ? ($partners[$buyItem->InOutID] ?? null) : null;
                        $product = $buyItem ? ($products[$buyItem->ProductID] ?? null) : null;
                        $translation = $product && isset($productTranslations[$product->ProductID]) ? $productTranslations[$product->ProductID]->Name : ($product->ProductName ?? 'Неизвестный товар');

                        $transactionsList[] = [
                            'FinTransactionDate' => $transaction->FinTransactionDate,
                            'IsContractor' => $transaction->IsContractor,
                            'Partner' => [
                                'LegalName' => $partner->LegalName ?? 'Неизвестный партнер',
                                'ShortName' => $partner->ShortName ?? '',
                                'PartnerID' => $partner->PartnerID ?? null,
                            ],
                            'item' => [
                                'ProductID' => $product->ProductID ?? null,
                                'ProductName' => $translation,
                            ],
                            'Price' => $transaction->Amount,
                            'IsCurrency' => $transaction->IsCurrency,
                            'Currency' => $transaction->IsCurrency ? ($account->Currency ?? 'GEL') : 'GEL',
                            'ForeignAmount' => $transaction->IsCurrency ? $transaction->ForeignAmount : null,
                            'ExchangeRate' => $transaction->IsCurrency ? $transaction->ExchangeRate : null,
                            'Quantity' => $buyItem->Quantity ?? 0,
                            'FinTransactionType' => '1',
                            'message' => '1',
                            'comments'  => $transaction->Comments,
                            'File'  => $transaction->File,
                        ];
                    }
                }

                if ($transaction->FinTransactionType === '2') {
                    $order = $orders[$transaction->TransactionID] ?? null;
                    $partner = $order ? ($partners[$order->PartnerID] ?? null) : null;

                    $transactionsList[] = [
                        'FinTransactionDate' => $transaction->FinTransactionDate,
                        'IsContractor' => $transaction->IsContractor,
                        'Partner' => [
                            'LegalName' => $partner->LegalName ?? 'Неизвестный партнер',
                            'ShortName' => $partner->ShortName ?? '',
                            'PartnerID' => $partner->PartnerID ?? null,
                        ],
                        'item' => [
                            'ProductID' => $order->OrderUUID ?? null,
                            'ProductName' => $order->OrderName ?? 'Неизвестный заказ',
                        ],
                        'Price' => $transaction->Amount,
                        'IsCurrency' => $transaction->IsCurrency,
                        'Currency' => $transaction->IsCurrency ? ($account->Currency ?? 'GEL') : 'GEL',
                        'ForeignAmount' => $transaction->IsCurrency ? $transaction->ForeignAmount : null,
                        'ExchangeRate' => $transaction->IsCurrency ? $transaction->ExchangeRate : null,
                        'Quantity' => 0,
                        'FinTransactionType' => '2',
                        'message' => '2',
                        'comments'  => $transaction->Comments,
                        'File'  => $transaction->File,
                    ];
                }

                if ($transaction->FinTransactionType === '3' || $transaction->FinTransactionType === '4') {
                    $company = $companies[$transaction->CorrespondentID] ?? null;

                    $transactionsList[] = [
                        'FinTransactionDate' => $transaction->FinTransactionDate,
                        'IsContractor' => $transaction->IsContractor,
                        'Partner' => [
                            'OwnerName' => $company->OwnerName ?? 'Неизвестный',
                            'OfficialName' => $company->OfficialName ?? 'Неизвестно',
                            'OwnerID' => $transaction->CorrespondentID,
                        ],
                        'Price' => $transaction->Amount,
                        'IsCurrency' => $transaction->IsCurrency,
                        'Currency' => $transaction->IsCurrency ? ($account->Currency ?? 'GEL') : 'GEL',
                        'ForeignAmount' => $transaction->IsCurrency ? $transaction->ForeignAmount : null,
                        'ExchangeRate' => $transaction->IsCurrency ? $transaction->ExchangeRate : null,
                        'FinTransactionType' => (string) $transaction->FinTransactionType,
                        'message' => (string) $transaction->FinTransactionType,
                        'comments'  => $transaction->Comments,
                        'File'  => $transaction->File,
                    ];
                }
            }

            $totalBalance = 0;
            $monthlyBalance = [];

            foreach ($transactions as $transaction) {
                $date = new \DateTime($transaction->FinTransactionDate);
                $month = $date->format('Y-m');
                $realAmount = ($transaction->IsCurrency == 1)
                    ? floatval($transaction->ForeignAmount)
                    : floatval($transaction->Amount);

                switch ($transaction->FinTransactionType) {
                    case 1: // Расход
                        $totalBalance -= $realAmount;
                        $monthlyBalance[$month]['expense'] = ($monthlyBalance[$month]['expense'] ?? 0) + $realAmount;
                        break;
                    case 2: // Доход
                        $totalBalance += $realAmount;
                        $monthlyBalance[$month]['income'] = ($monthlyBalance[$month]['income'] ?? 0) + $realAmount;
                        break;
                    case 3: // Депозит
                        $totalBalance += $realAmount;
                        $monthlyBalance[$month]['deposit'] = ($monthlyBalance[$month]['deposit'] ?? 0) + $realAmount;
                        break;
                    case 4: // Снятие
                        $totalBalance -= $realAmount;
                        $monthlyBalance[$month]['withdrawal'] = ($monthlyBalance[$month]['withdrawal'] ?? 0) + $realAmount;
                        break;
                }
            }

            $period = new \DatePeriod($startDateObj, new \DateInterval('P1M'), $endDateObj);

            $formattedMonthlyBalance = [];
            foreach ($period as $month) {
                $monthStr = $month->format('Y-m');
                $formattedMonthlyBalance[] = [
                    'month' => $monthStr,
                    'income' => number_format($monthlyBalance[$monthStr]['income'] ?? 0, 2, '.', ''),
                    'expense' => number_format($monthlyBalance[$monthStr]['expense'] ?? 0, 2, '.', ''),
                    'deposit' => number_format($monthlyBalance[$monthStr]['deposit'] ?? 0, 2, '.', ''),
                    'withdrawal' => number_format($monthlyBalance[$monthStr]['withdrawal'] ?? 0, 2, '.', ''),
                    'profit' => number_format(
                        ($monthlyBalance[$monthStr]['income'] ?? 0) - ($monthlyBalance[$monthStr]['expense'] ?? 0),
                        2,
                        '.',
                        ''
                    ),
                ];
            }

            $account = (new PaymentAccountModel())->table()->select(['Currency'])->where('AccountID', $UUID)->first();

            self::setData(result: [
                'transaction' => $transactionsList,
                'bank'          => $account,
                'monthlySummary' => $formattedMonthlyBalance,
                'total' => [
                    'income' => number_format(array_sum(array_column($monthlyBalance, 'income')), 2, '.', ''),
                    'expense' => number_format(array_sum(array_column($monthlyBalance, 'expense')), 2, '.', ''),
                    'deposit' => number_format(array_sum(array_column($monthlyBalance, 'deposit')), 2, '.', ''),
                    'withdrawal' => number_format(array_sum(array_column($monthlyBalance, 'withdrawal')), 2, '.', ''),
                    'transactionCount' => count($transactions),
                    'balance' => number_format($totalBalance, 2, '.', ''),
                ],
            ], status: 'success');
        } catch (\Exception $e) {
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }




    /**
     * Метод для добавления средств на баланс учредителем
     *
     * @return void
     * @throws JsonException
     */
    #[NoReturn] #[HttpMethod(['get'], '/')]
    public function addFundsToBalance(): void
    {
        try {
            // Проверка авторизации
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                self::setData(result: ['error' => 'auth failed'], statusCode: 401, status: 'error');
            }

            // Извлечение JWT токена
            $jwtParts = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            if (count($jwtParts) != 2) {
                self::setData(result: ['error' => 'Invalid Authorization Header'], statusCode: 401, status: 'error');
            }
            $jwt = $jwtParts[1];
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // Получение данных из запроса
            $data = Input::json();

            $accountId = $data['account'] ?? null;
            $amount = $data['amount'] ?? null;

            if (!$accountId || !$amount || $amount <= 0) {
                self::setData(result: ['error' => 'Invalid input: AccountID and positive Amount are required'], statusCode: 400, status: 'error');
            }

            // Проверка существования счета и получение CorrespondentID
            $accountModel = new PaymentAccountModel();
            $account = $accountModel->table()->where('AccountID', $accountId)->first();

            if (!$account) {
                self::setData(result: ['error' => 'Account not found'], statusCode: 404, status: 'error');
            }

            $correspondentId = $account->OwnerID ?? null; // ID компании владельца счета

            if (!$correspondentId) {
                self::setData(result: ['error' => 'CorrespondentID not found for the provided AccountID'], statusCode: 400, status: 'error');
            }

            // Создание новой транзакции
            $finTransactionsModel = new FinTransactionsModel();

            $isCurrency = 0;
            $foreignAmount = null;
            $exchangeRate = null;
            $gelAmount = $amount;

// Получаем валюту счёта
            $currency = strtoupper($account->Currency ?? 'GEL');

            if ($currency !== 'GEL') {
                $rate = $this->getCurrencyRate($currency);
                if (!$rate) {
                    self::setData(result: ['error' => "Не удалось получить курс для валюты: $currency"], statusCode: 500, status: 'error');
                }

                $isCurrency = 1;
                $foreignAmount = $amount;
                $exchangeRate = $rate;
                $gelAmount = round($foreignAmount * $exchangeRate, 2);
            }

            $transactionData = [
                'FinTransactionDate' => Carbon::now(),
                'FinTransactionType' => 3,
                'AccountID' => $accountId,
                'CorrespondentID' => $correspondentId,
                'TransactionID' => null,
                'Amount' => $gelAmount,
                'ForeignAmount' => $foreignAmount,
                'ExchangeRate' => $exchangeRate,
                'IsCurrency' => $isCurrency,
                'Status' => 1,
            ];


            $finTransactionsModel->table()->insert($transactionData);

            // Формирование ответа
            self::setData(result: ['message' => 'Funds added successfully'], status: 'success');
        } catch (\Exception $e) {
            // Обработка ошибок
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }

    private function getCurrencyRate(string $currencyCode): ?float
    {
        $date = Carbon::now()->format('Y-m-d');
        $url = "https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies/en/json/?date=$date";

        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if (!is_array($data) || empty($data[0]['currencies'])) {
            return null;
        }

        foreach ($data[0]['currencies'] as $item) {
            if (strtoupper($item['code']) === strtoupper($currencyCode)) {
                return floatval($item['rate']);
            }
        }

        return null;
    }
    public function withdrawFunds(): void
    {
        try {
            // Проверка авторизации
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                self::setData(result: ['error' => 'auth failed'], statusCode: 401, status: 'error');
            }

            $jwtParts = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            if (count($jwtParts) != 2) {
                self::setData(result: ['error' => 'Invalid Authorization Header'], statusCode: 401, status: 'error');
            }
            $jwt = $jwtParts[1];
            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // Получение данных из запроса
            $data = Input::json();
            $UUID = $data['uuid'] ?? null;
            $amount = floatval($data['amount'] ?? 0);

            if (!$UUID || $amount <= 0) {
                self::setData(result: ['error' => 'UUID и положительная сумма должны быть указаны'], statusCode: 400, status: 'error');
            }

            // Проверка наличия аккаунта
            $account = (new PaymentAccountModel())->table()->where('AccountID', $UUID)->first();
            if (!$account) {
                self::setData(result: ['error' => 'Аккаунт не найден'], statusCode: 404, status: 'error');
            }

            // Проверка существования счета и получение CorrespondentID
            $correspondentId = $account->OwnerID ?? null; // ID компании владельца счета

            if (!$correspondentId) {
                self::setData(result: ['error' => 'CorrespondentID not found for the provided AccountID'], statusCode: 400, status: 'error');
            }
            // Создание транзакции снятия
            $isCurrency = 0;
            $foreignAmount = null;
            $exchangeRate = null;
            $gelAmount = $amount;

            $currency = strtoupper($account->Currency ?? 'GEL');

            if ($currency !== 'GEL') {
                $rate = $this->getCurrencyRate($currency);
                if (!$rate) {
                    self::setData(result: ['error' => "Не удалось получить курс валюты $currency"], statusCode: 500, status: 'error');
                }

                $isCurrency = 1;
                $foreignAmount = $amount;
                $exchangeRate = $rate;
                $gelAmount = round($foreignAmount * $exchangeRate, 2);
            }

            $transaction = new FinTransactionsModel();
            $transaction->AccountID = $UUID;
            $transaction->Amount = $gelAmount;
            $transaction->ForeignAmount = $foreignAmount;
            $transaction->ExchangeRate = $exchangeRate;
            $transaction->IsCurrency = $isCurrency;
            $transaction->FinTransactionType = 4;
            $transaction->CorrespondentID = $correspondentId;
            $transaction->Status = 1;
            $transaction->FinTransactionDate = (new \DateTime())->format('Y-m-d H:i:s');
            $transaction->save();

            self::setData(result: ['success' => 'Средства успешно сняты'], status: 'success');
        } catch (\Exception $e) {
            self::setData(result: ['error' => $e->getMessage()], statusCode: 500, status: 'error');
        }
    }

}