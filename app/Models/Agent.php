<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'extension', 
        'secret', 
        'role', 
        'group_id', // Catatan: Jika 'group_id' ini dulunya dipakai untuk SPV tunggal dan sudah tidak dipakai, nanti bisa dihapus.
        'status',
        'context'
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    // Tambahkan relasi Many-to-Many ke Supervisor 
    public function supervisors()
    {
        return $this->belongsToMany(Agent::class, 'agent_supervisor', 'agent_id', 'supervisor_id');
    }
    public function agents()
    {
        return $this->belongsToMany(Agent::class, 'agent_supervisor', 'supervisor_id', 'agent_id');
    }
    // Pesan yang dikirim oleh agen/supervisor ini
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    // Pesan yang diterima oleh agen/supervisor ini
    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }
}