<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cdr extends Model
{
    // Arahkan ke koneksi yang baru kita buat
    protected $connection = 'asterisk_cdr';
    
    // Arahkan ke tabel cdr milik FreePBX
    protected $table = 'cdr';

    // Karena tabel cdr FreePBX tidak pakai created_at/updated_at bawaan Laravel
    public $timestamps = false;

    // Field apa saja yang boleh kita baca
    protected $guarded = [];
}