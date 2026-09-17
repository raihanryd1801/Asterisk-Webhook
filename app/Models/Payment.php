<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    public const METHODS = ['transfer', 'cash', 'va', 'qris', 'autodebet', 'lainnya'];

    public const METHOD_LABELS = [
        'transfer' => 'Transfer',
        'cash' => 'Tunai',
        'va' => 'Virtual Account',
        'qris' => 'QRIS',
        'autodebet' => 'Autodebet',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = [
        'customer_id', 'amount', 'paid_at', 'method',
        'notes', 'proof', 'created_by', 'created_by_label',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getProofUrlAttribute()
    {
        return $this->proof ? '/storage/' . ltrim($this->proof, '/') : null;
    }

    public function getMethodLabelAttribute()
    {
        return self::METHOD_LABELS[$this->method] ?? $this->method;
    }
}
