<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdsRotation extends Model
{
    use HasFactory;

    protected $fillable = ['agent_id', 'extension', 'joined_at'];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
}
