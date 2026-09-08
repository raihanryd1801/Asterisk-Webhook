<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'company',
        'status',
        'notes',
        'assigned_agent_id',
        'created_by',
        'last_contacted_at',
        'total_amount',
        'paid_amount',
        'discount_amount',
        'payment_status',
        'payment_notes',
        'last_payment_date',
        'payment_proof',
    ];

    protected $casts = [
        'last_contacted_at' => 'datetime',
        'last_payment_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    public function assignedAgent()
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('company', 'like', "%{$search}%");
        });
    }

    public function scopeStatus($query, $status)
    {
        return $query->when($status, fn($q) => $q->where('status', $status));
    }

    public function scopeAssignedTo($query, $agentId)
    {
        return $query->when($agentId, fn($q) => $q->where('assigned_agent_id', $agentId));
    }

    public function scopePaymentStatus($query, $status)
    {
        return $query->when($status, fn($q) => $q->where('payment_status', $status));
    }

    public function getRemainingAmountAttribute()
    {
        return max(0, $this->total_amount - $this->paid_amount - $this->discount_amount);
    }

    public function getPaymentProgressAttribute()
    {
        if ($this->total_amount <= 0) return 100;
        return min(100, round((($this->paid_amount + $this->discount_amount) / $this->total_amount) * 100, 1));
    }
}