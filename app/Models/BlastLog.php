<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlastLog extends Model
{
    protected $fillable = [
        'bucket', 'channel', 'message', 'total_target',
        'sent', 'failed', 'status', 'note', 'created_by', 'sender',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
