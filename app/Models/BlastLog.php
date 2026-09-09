<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlastLog extends Model
{
    protected $fillable = [
        'campaign_id', 'channel', 'message', 'total_target',
        'sent', 'failed', 'status', 'note', 'created_by',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
