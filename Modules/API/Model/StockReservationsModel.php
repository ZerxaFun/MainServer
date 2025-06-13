<?php

namespace Modules\API\Model;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class StockReservationsModel extends Model
{
    protected $table = 'Company';

    protected $fillable = [
        'ProductID'
        ,'OrderUUID'
        ,'ReservedQuantity'
        ,'ReservationDate'
    ];


    protected $primaryKey = 'ReservationID';
    public $incrementing = false; // UUID не является автоинкрементом
    protected $keyType = 'string'; // Указываем, что primary key — строка

    public $timestamps = false; // Отключаем автоматическое обновление меток времени


    /**
     * Выбор раздела базы данных Builder
     * @return Builder
     */
    public function table(): Builder
    {
        return Capsule::table($this->table);
    }

}