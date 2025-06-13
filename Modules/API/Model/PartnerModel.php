<?php

namespace Modules\API\Model;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class PartnerModel extends Model
{
    protected $table = 'Partner';

    protected $fillable = [
        'LegalName',
        'ShortName',
        'TAXID',
        'Comments',
        'CompanyType',
        'Status',
    ];


    protected $primaryKey = 'PartnerID';
    public $incrementing = false;
    protected $keyType = 'string';

    // Отключаем автоматическое управление временем
    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
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