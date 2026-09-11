<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DialQueueItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id', 'customer_id', 'phone', 'bucket', 'status',
        'attempts', 'agent_extension', 'last_attempt_at', 'note',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
    ];

    public function job()
    {
        return $this->belongsTo(DialJob::class, 'job_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
