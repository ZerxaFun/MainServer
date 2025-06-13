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
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;
use Modules\API\Model\FileModel;
use Illuminate\Database\Capsule\Manager as DB;
use Modules\API\Model\FinTransactionsModel;
use Modules\API\Model\OrderModel;

class PaymentTransactionController extends Controller
{
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/transactions/add')]
    #[Authorize(guard: 'jwt', permission: ['admin', 'accountant'])] // поправь под свои роли
    #[Validate([
        'bankAccount' => ['required' => true, 'type' => 'uuid'],
        'orderID' => ['required' => true, 'type' => 'uuid'],
        'amount' => ['required' => true, 'type' => 'int', 'min' => 0.01],
        'comment' => ['required' => false, 'type' => 'string'],
    ])]
    public static function updatePaymentTransaction(ValidatedRequest $request): void
    {
        try {
            $request->check();

            $accountID = $request->input('bankAccount');
            $orderID = $request->input('orderID');
            $amount = $request->input('amount');
            $comment = $request->input('comment');
            $file = $_FILES['file'] ?? null;

            $orderCorrespondent = OrderModel::query()
                ->select('PartnerID')
                ->where('OrderUUID', $orderID)
                ->first()?->PartnerID;

            if (!$orderCorrespondent) {
                throw new \Exception('Order not found or missing correspondent.');
            }

            DB::beginTransaction();

            $finUUID = Str::uuid();
            $fileUUID = ($file && $file['error'] === UPLOAD_ERR_OK) ? Str::uuid() : null;

            $account = DB::table('Accounts')
                ->select('Currency')
                ->where('AccountID', $accountID)
                ->first();

            if (!$account) {
                throw new \Exception('Bank account not found.');
            }

            $currency = strtoupper($account->Currency);
            $gelAmount = $amount;
            $isCurrency = 0;
            $foreignAmount = null;
            $exchangeRate = null;

            if ($currency !== 'GEL') {
                $rate = self::getCurrencyRate($currency); // Сделай метод static
                if (!$rate) {
                    throw new \Exception("Не удалось получить курс для валюты: $currency");
                }

                $isCurrency = 1;
                $foreignAmount = $amount;
                $exchangeRate = $rate;
                $gelAmount = round($amount * $rate, 2);
            }

            $finTransactionData = [
                'FinTransactionsID' => $finUUID,
                'FinTransactionDate' => Carbon::now(),
                'FinTransactionType' => 2,
                'AccountID' => $accountID,
                'CorrespondentID' => $orderCorrespondent,
                'TransactionID' => $orderID,
                'Amount' => $gelAmount,
                'ForeignAmount' => $foreignAmount,
                'ExchangeRate' => $exchangeRate,
                'IsCurrency' => $isCurrency,
                'Status' => 1,
                'Comments' => $comment,
                'File' => $fileUUID,
            ];

            $transaction = FinTransactionsModel::create($finTransactionData);

            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $filePath = Path::base('Storage') . '/Transactions/' . uniqid() . '_' . $file['name'];

                if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                    throw new \Exception('Failed to save the uploaded file.');
                }

                $fileData = [
                    'FileID' => $fileUUID,
                    'OrderUUID' => $fileUUID, // Ссылка на транзакцию
                    'Filename' => $file['name'],
                    'Filepath' => $filePath,
                    'Size' => $file['size'],
                    'Type' => $file['type'] ?? null,
                    'CreatedAt' => now(),
                    'Version' => 1,
                ];

                FileModel::create($fileData);
                $transaction->update(['File' => $fileUUID]);
            }

            DB::commit();

            self::api([
                'message' => 'Financial transaction and file added successfully',
                'transaction' => $transaction,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            self::api(['error' => $e->getMessage()], 500, 'error');
        }
    }

    private static function getCurrencyRate(string $currencyCode): ?float
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

    /**
     * Получение финансовых транзакций по UUID заказа
     * Возвращает список транзакций, связанных с заказом
     * Требует JWT авторизацию
     */
    #[NoReturn]
    #[HttpMethod(['post'], '/api/v1/transactions/get')]
    #[Authorize(guard: 'jwt', permission: ['admin'])]
    #[Validate([
        'orderUUID' => ['required' => true, 'type' => 'uuid'],
    ])]
    public static function getTransactions(ValidatedRequest $request): void
    {
        try {
            $request->check();
            $orderUUID = $request->input('orderUUID');

            $transactions = DB::table('FinTransactions as ft')
                ->join('Accounts as a', 'ft.AccountID', '=', 'a.AccountID')
                ->where('ft.TransactionID', $orderUUID)
                ->where('ft.Status', 1)
                ->select([
                    'ft.FinTransactionsID as id',
                    'ft.AccountID as BankId',
                    'ft.Amount as amount',                   // сумма в GEL
                    'ft.ForeignAmount as ForeignAmount',     // сумма в валюте
                    'ft.ExchangeRate as ExchangeRate',       // курс
                    'ft.IsCurrency',
                    'a.Bank as bank',
                    'a.Currency as currency',
                    'ft.Comments as comment',
                    'ft.File as file',
                ])
                ->get();

            self::api(
                ['transactions' => $transactions]
            );

        } catch (\Throwable $e) {
            self::api(
                ['error' => $e->getMessage()],
                500,
                'error'
            );
        }
    }


    public function deleteTransaction(): void
    {
        try {
            // Проверка авторизации
            if (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
                throw new \Exception('Authorization failed');
            }

            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $jwt = explode(" ", $authHeader)[1] ?? null;

            if (!$jwt) {
                throw new \Exception('Token not provided.');
            }

            JWT::decode($jwt, new Key($_ENV['s_key'], 'HS256'));

            // Получение идентификатора транзакции
            $transactionId = Input::json('transactionId');

            if (empty($transactionId)) {
                throw new \Exception('Transaction ID is required.');
            }

            // Установка статуса "удалено" (Status = 0)
            $transaction = DB::table('FinTransactions')
                ->where('FinTransactionsID', $transactionId)
                ->first();

            if (!$transaction) {
                throw new \Exception('Transaction not found.');
            }

            DB::table('FinTransactions')
                ->where('FinTransactionsID', $transactionId)
                ->update(['Status' => 0, 'DeletedAt' => Carbon::now()]);

            // Удаление файла, если он существует
            if (!empty($transaction->File)) {
                $filePath = $transaction->File;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                // Обновление информации о файле
                DB::table('Files')
                    ->where('Filepath', $filePath)
                    ->update(['DeletedAt' => Carbon::now()]);
            }

            self::setData(
                result: ['message' => 'Transaction deleted successfully.'],
                statusCode: 200,
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


}