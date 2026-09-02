<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // 🚀 Perbaikan namespace di sini
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'is_read',
    ];

    // Relasi ke pengirim (bisa Agent atau Supervisor)
    public function sender()
    {
        return $this->belongsTo(Agent::class, 'sender_id');
    }

    // Relasi ke penerima (bisa Agent atau Supervisor)
    public function receiver()
    {
        return $this->belongsTo(Agent::class, 'receiver_id');
    }
}