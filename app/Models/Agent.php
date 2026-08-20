<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;

    // Tambahkan baris ini untuk mengizinkan kolom-kolom tersebut diisi secara massal
    protected $fillable = [
        'name',
        'extension',
        'secret',
        'workgroup',
        'status',
    ];
}