<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaRule extends Model
{
    protected $fillable = [
        'bucket', 'max_dpd', 'escalate_risk', 'action_note', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
