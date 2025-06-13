<?php

namespace Modules\API\Model;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class MasterProductsModel extends Model
{
    protected $table = 'MasterProducts';

    protected $fillable = [
        'MasterID', 'ProductID'
    ];

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