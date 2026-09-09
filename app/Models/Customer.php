<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'phone', 'email', 'company', 'status', 'notes',
        'assigned_agent_id', 'created_by', 'last_contacted_at',
        'total_amount', 'paid_amount', 'discount_amount',
        'payment_status', 'payment_notes', 'last_payment_date', 'payment_proof',
        'due_date', 'days_past_due', 'bucket', 'campaign_id', 'collector_id',
        'risk_level', 'promise_to_pay',
    ];

    protected $casts = [
        'last_contacted_at' => 'datetime',
        'last_payment_date' => 'datetime',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'promise_to_pay' => 'array',
    ];

    public function assignedAgent()
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function collector()
    {
        return $this->belongsTo(Agent::class, 'collector_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
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

    public function scopeCollectedBy($query, $collectorId)
    {
        return $query->when($collectorId, fn($q) => $q->where('collector_id', $collectorId));
    }

    public function scopeInCampaign($query, $campaignId)
    {
        return $query->when($campaignId, fn($q) => $q->where('campaign_id', $campaignId));
    }

    public function scopePaymentStatus($query, $status)
    {
        return $query->when($status, fn($q) => $q->where('payment_status', $status));
    }

    public function scopeBucket($query, $bucket)
    {
        return $query->when($bucket, fn($q) => $q->where('bucket', $bucket));
    }

    public function scopeRiskLevel($query, $level)
    {
        return $query->when($level, fn($q) => $q->where('risk_level', $level));
    }

    public function scopeOverdue($query, $days = 1)
    {
        return $query->where('days_past_due', '>=', $days);
    }

    public function scopeDueSoon($query, $days = 7)
    {
        return $query->where('due_date', '<=', now()->addDays($days))
                     ->where('due_date', '>=', now())
                     ->whereIn('payment_status', ['unpaid', 'partial']);
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

    public function getPromiseToPayAttribute($value)
    {
        if (!$value) return null;
        return array_merge([
            'amount' => 0,
            'date' => null,
            'note' => '',
            'status' => 'pending', // pending, kept, broken
            'created_at' => null,
        ], (array) $value);
    }

    public function setPromiseToPay($amount, $date, $note = '')
    {
        $this->promise_to_pay = [
            'amount' => $amount,
            'date' => $date,
            'note' => $note,
            'status' => 'pending',
            'created_at' => now()->toISOString(),
        ];
        $this->save();
    }

    public function markPromiseKept()
    {
        $ptp = $this->getRawOriginal('promise_to_pay');
        if ($ptp) {
            $ptp = is_string($ptp) ? json_decode($ptp, true) : (is_object($ptp) ? (array) $ptp : $ptp);
            $ptp['status'] = 'kept';
            $ptp['kept_at'] = now()->toISOString();
            $this->promise_to_pay = $ptp;
            $this->save();
        }
    }

    public function markPromiseBroken()
    {
        $ptp = $this->getRawOriginal('promise_to_pay');
        if ($ptp) {
            $ptp = is_string($ptp) ? json_decode($ptp, true) : (is_object($ptp) ? (array) $ptp : $ptp);
            $ptp['status'] = 'broken';
            $ptp['broken_at'] = now()->toISOString();
            $this->promise_to_pay = $ptp;
            $this->save();
        }
    }

    public function hasActivePromise()
    {
        $ptp = $this->promise_to_pay;
        if (is_object($ptp)) $ptp = (array) $ptp;
        return $ptp && $ptp['status'] === 'pending' && $ptp['date'] >= now()->toDateString();
    }

    public function recalculateBucket()
    {
        if (!$this->due_date) {
            $this->bucket = null;
            $this->days_past_due = 0;
            $this->risk_level = 'low';
            $this->save();
            return;
        }

        $dueDate = \Carbon\Carbon::parse($this->due_date);
        $today = now()->startOfDay();
        $dpd = $dueDate->diffInDays($today, false); // negative = overdue

        $this->days_past_due = max(0, -$dpd);

        if ($dpd >= 0) {
            // Belum jatuh tempo
            $this->bucket = 'Current';
            $this->risk_level = 'low';
        } elseif ($dpd >= -30) {
            $this->bucket = 'Bucket 1'; // 1-30 dpd
            $this->risk_level = 'low';
        } elseif ($dpd >= -60) {
            $this->bucket = 'Bucket 2'; // 31-60 dpd
            $this->risk_level = 'medium';
        } elseif ($dpd >= -90) {
            $this->bucket = 'Bucket 3'; // 61-90 dpd
            $this->risk_level = 'high';
        } else {
            $this->bucket = 'NPL'; // 90+ dpd
            $this->risk_level = 'critical';
        }

        $this->save();
        return $this->bucket;
    }
}