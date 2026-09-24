<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebtCollector extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'phone', 'type', 'area', 'notes', 'is_active', 'api_token', 'is_tracking', 'pin',
    ];

    protected $hidden = ['api_token', 'pin'];

    protected $appends = ['has_pin'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_tracking' => 'boolean',
        ];
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'collector_id');
    }

    public function positions()
    {
        return $this->hasMany(CollectorPosition::class, 'collector_id');
    }

    /** Posisi terakhir collector (untuk peta live). */
    public function lastPosition(): ?CollectorPosition
    {
        return $this->positions()->latest('recorded_at')->latest('id')->first();
    }

    /** Online bila tracking jalan DAN ada posisi < 5 menit terakhir. */
    public function getIsOnlineAttribute(): bool
    {
        if (!$this->is_tracking) {
            return false;
        }
        $last = $this->lastPosition();

        return $last && $last->recorded_at->gt(now()->subMinutes(5));
    }

    /** Ada PIN login HP? (nilainya sendiri tidak pernah dikirim). */
    public function getHasPinAttribute(): bool
    {
        return !empty($this->pin);
    }

    /** Buat/putar token HP collector. Return token plain (sekali tampil). */
    public function rotateApiToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->update(['api_token' => hash('sha256', $token)]);

        return $token;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeField($query)
    {
        return $query->where('type', 'field');
    }

    public function getTypeLabelAttribute()
    {
        return $this->type === 'field' ? 'Lapangan' : 'Desk (Telepon)';
    }
}
