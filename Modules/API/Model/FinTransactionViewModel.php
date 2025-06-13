<?php

namespace Modules\API\Model;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class FinTransactionViewModel extends Model
{
    // Указываем имя таблицы (представления)
    protected $table = 'FinTransactionView';

    // Указываем первичный ключ
    protected $primaryKey = 'FinTransactionsID';

    // Если первичный ключ не автоинкрементный
    public $incrementing = false;

    // Тип первичного ключа
    protected $keyType = 'string';

    // Указываем, что временные метки не используются
    public $timestamps = false;

    // Разрешенные для массового присвоения поля
    protected $fillable = [
        'FinTransactionsID',
        'FinTransactionDate',
        'FinTransactionType',
        'AccountID',
        'CorrespondentID',
        'TransactionID',
        'Amount',
        'Status',
        'StockTransactionID',
        'StockTransactionType',
        'ProductID',
        'WarehouseID',
        'InOutID',
        'OrderUUID',
        'OrderName',
        'PaymentStatus',
        'Source',
    ];

    /**
     * Получение Builder для выполнения дополнительных запросов.
     *
     * @return Builder
     */
    public function table(): Builder
    {
        return Capsule::table($this->table);
    }
}
