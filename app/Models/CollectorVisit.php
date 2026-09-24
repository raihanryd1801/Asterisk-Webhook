<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectorVisit extends Model
{
    public const ACTIVE = ['otw', 'sampai'];

    protected $fillable = [
        'collector_id', 'customer_id', 'status', 'result', 'note',
        'started_at', 'arrived_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'arrived_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function collector()
    {
        return $this->belongsTo(DebtCollector::class, 'collector_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE);
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }
}
