<?php

namespace Modules\API\Helpers;

use Modules\API\Model\OrderModel;
use Modules\API\Model\FinTransactionsModel;

class PartnerInfo
{
    /**
     * Получить баланс партнёра.
     *
     * @param string $partnerId ID партнёра.
     * @return array Ассоциативный массив с суммой задолженности, оплаченной суммы и балансом.
     */
    public static function getBalance(string $partnerId): array
    {
        // Получаем заказы партнёра с уникальными OrderUUID по максимальной Version
        $orders = OrderModel::query()
            ->select('orders.*')
            ->joinSub(
                OrderModel::query()
                    ->selectRaw('OrderUUID, MAX(Version) as max_version')
                    ->groupBy('OrderUUID'),
                'latest_versions',
                function ($join) {
                    $join->on('orders.OrderUUID', '=', 'latest_versions.OrderUUID')
                        ->whereColumn('orders.Version', '=', 'latest_versions.max_version');
                }
            )
            ->where('PartnerID', '=', $partnerId)
            ->get();

        // Суммируем стоимость всех заказов (долг)
        $totalDebt = $orders->sum(function ($order) {
            return (float) $order->EstimatedCost;
        });

        // Суммируем оплаченные суммы с учетом валют
        $totalPaid = 0.0;
        $finTransactionModel = new FinTransactionsModel();

        foreach ($orders as $order) {
            $transactions = $finTransactionModel->table()
                ->where('TransactionID', '=', $order->OrderUUID)
                ->where('Status', '=', 1) // Только подтвержденные
                ->get();

            foreach ($transactions as $t) {
                if ((int)$t->IsCurrency === 1) {
                    $amountInGel = (float)$t->ForeignAmount * (float)$t->ExchangeRate;
                    $totalPaid += $amountInGel;
                } else {
                    $totalPaid += (float)$t->Amount;
                }
            }
        }

        // Вычисляем остаток: сколько ещё должен партнёр
        $balance = $totalPaid - $totalDebt;

        return [
            'totalDebt' => round($totalDebt, 2),
            'totalPaid' => round($totalPaid, 2),
            'balance'   => round($balance, 2),
        ];
    }

}
