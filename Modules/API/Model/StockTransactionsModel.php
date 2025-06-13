<?php

namespace Modules\API\Model;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class StockTransactionsModel extends Model
{
    protected $table = 'StockTransactions';

    protected $fillable = [
        'StockTransactionID',
        'StockTransactionDate',
        'StockTransactionType',
        'ProductID',
        'WarehouseID',
        'InOutID',
        'StockID',
        'Quantity',
        'Weight',
        'Price',
        'IsContractor',
    ];

    protected $primaryKey = 'StockTransactionID';
    public $incrementing = false;
    protected $keyType = 'string';

    // Отключаем автоматическое управление временем
    public $timestamps = false;

    /**
     * Выбор раздела базы данных Builder
     * @return Builder
     */
    public function table(): Builder
    {
        return Capsule::table($this->table);
    }

}