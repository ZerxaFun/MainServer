<?php

namespace Modules\API\Model;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class ProductModel extends Model
{
    protected $table = 'Products';

    protected $fillable = [
        'ProductID',
        'Unit',
        'ProductType',
        'Status',
        'deleted_at',
    ];


    protected $primaryKey = 'ProductID';
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