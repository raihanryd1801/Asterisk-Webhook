<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cdr extends Model
{
    // 🚀 1. Arahkan koneksi ke database LOKAL (bukan asterisk_cdr)
    protected $connection = 'mysql'; 
    
    // 🚀 2. Arahkan ke tabel LOKAL yang isinya super ringan
    protected $table = 'cdr_live';   

    public $timestamps = false;
    protected $primaryKey = 'uniqueid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
}