<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectorPosition extends Model
{
    protected $fillable = [
        'collector_id', 'latitude', 'longitude', 'accuracy', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function collector()
    {
        return $this->belongsTo(DebtCollector::class, 'collector_id');
    }

    /** Jarak (meter) ke titik lain — Haversine. */
    public function distanceTo(float $lat, float $lon): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat - $this->latitude);
        $dLon = deg2rad($lon - $this->longitude);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($lat)) * sin($dLon / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }
}
