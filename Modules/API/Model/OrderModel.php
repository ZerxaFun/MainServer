<?php

namespace Modules\API\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Capsule\Manager as Capsule;

class OrderModel extends Model
{
    protected $table = 'Orders';

    protected $fillable = [
        'OrderUUID',
        'Version',
        'PartnerID',
        'OrderName',
        'Description',
        'EstimatedCost',
        'GeneralDeadlineDate',
        'PaymentStatus',
        'ReadinessStatus',
        'OrderStatus',
        'CreatedAt',
        'CreatedBy',
        'CorrespondentID',
    ];

    protected $primaryKey = 'OrderID';
    public $incrementing = false; // UUID не является автоинкрементом
    protected $keyType = 'string'; // Указываем, что primary key — строка

    public $timestamps = false; // Вручную управляем полями CreatedAt и UpdatedAt

    /**
     * Связь с OrderItems.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItemModel::class, 'OrderID', 'OrderID');
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
