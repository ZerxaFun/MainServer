<?php

namespace Modules\API\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Capsule\Manager as Capsule;

class OrderItemModel extends Model
{
    protected $table = 'OrderItems';

    protected $fillable = [
        'OrderUUID',
        'ProductName',
        'Description',
        'Quantity',
        'ManualPrice',
        'Version',
        'WorkStatus',
        'deadlineDate',
        'MaterialsAndServices',
        'CreatedAt',
        'ItemPermID',
    ];

    protected $primaryKey = 'OrderItemID';
    public $incrementing = false; // UUID не является автоинкрементом
    protected $keyType = 'string'; // Указываем, что primary key — строка

    public $timestamps = false; // Вручную управляем полем CreatedAt

    /**
     * Связь с Order.
     */
    public function order()
    {
        return $this->belongsTo(OrderModel::class, 'OrderID', 'OrderID');
    }

    /**
     * Выбор раздела базы данных Builder
     * @return Builder
     */
    public function table(): Builder
    {
        return Capsule::table($this->table);
    }
}
