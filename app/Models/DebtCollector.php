<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebtCollector extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'phone', 'type', 'area', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class, 'collector_id');
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
