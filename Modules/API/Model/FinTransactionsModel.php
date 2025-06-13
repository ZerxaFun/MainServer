<?php

namespace Modules\API\Model;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class FinTransactionsModel extends Model
{
    protected $table = 'FinTransactions';

    protected $fillable = [
        'FinTransactionDate',
        'FinTransactionType',
        'AccountID',
        'CorrespondentID',
        'TransactionID',
        'Amount',
        'Status',
        'Comments',
        'File',
        'DeletedAt',
        'IsContractor',
        'ForeignAmount',
        'ExchangeRate',
        'IsCurrency',
    ];

    protected $primaryKey = 'FinTransactionsID';
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