<?php

namespace Modules\API\Model;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class MastersModel extends Model
{
    protected $table = 'Masters';

    protected $fillable = [
        'type', 'name', 'by_user', 'deleted', 'tax_id', 'comment', 'phone', 'account'
    ];


    protected $primaryKey = 'MasterID';
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