<?php

namespace Modules\API\Model;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class StockMovementHistoryModel extends Model
{
    protected $table = 'StockMovementHistory';

    protected $fillable = [
        'MovementID',
        'ProductID',
        'WarehouseID',
        'MovementDate',
        'InitiatorID',
        'SourceWarehouseID',
        'DestinationWarehouseID',
        'Notes',
        'CreatedAt',
    ];

    protected $primaryKey = 'MovementID';
    public $incrementing = false;
    protected $keyType = 'string';

    // Отключаем автоматическое управление временем
    public $timestamps = false;

    /**
     * Выбор раздела базы данных Builder
     *
     * @return Builder
     */
    public function table(): Builder
    {
        return Capsule::table($this->table);
    }
}
