<?php

namespace App\Models;

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

    public function getCalldateAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : null;
    }
}