<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder; // 🚀 Wajib tambahkan ini
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Cdr extends Model
{
    protected $connection = 'mysql'; 
    protected $table = 'cdr_live';   

    public $timestamps = false;
    protected $primaryKey = 'uniqueid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    // 🚀 Tambahkan Global Scope di sini agar otomatis memfilter seluruh query sistem
    protected static function booted()
    {
        static::addGlobalScope('ignore_system_calls', function (Builder $builder) {
            $builder->where('dst', '!=', 's')
                    ->where('src', 'NOT LIKE', 'PJSIP/%')
                    ->where('src', 'NOT LIKE', 'SIP/%');
        });
    }

    public function getCalldateAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : null;
    }
}