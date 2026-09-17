<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'phone', 'office_phone', 'emergency_phone',
        'gender', 'email', 'company', 'status', 'notes',
        'assigned_agent_id', 'created_by', 'last_contacted_at',
        'total_amount', 'paid_amount', 'discount_amount',
        'payment_status', 'payment_notes', 'last_payment_date', 'payment_proof',
        'due_date', 'days_past_due', 'bucket', 'collector_id',
        'risk_level', 'promise_to_pay',
        'handover_status', 'handover_to', 'handover_date', 'handover_notes',
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
        return $this->belongsTo(DebtCollector::class, 'collector_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at')->orderByDesc('id');
    }

    /** Total pembayaran yang tercatat di tabel payments (di luar saldo awal). */
    public function getRecordedPaymentsTotalAttribute()
    {
        return (float) $this->payments()->sum('amount');
    }

    /**
     * Catat 1 transaksi pembayaran: tambah ke paid_amount + sesuaikan
     * status/last_payment + hitung ulang bucket. Dipakai PaymentController.
     */
    public function applyPayment(float $amount, string $paidAt): void
    {
        $this->paid_amount = (float) $this->paid_amount + $amount;
        $this->last_payment_date = $paidAt . ' ' . now()->format('H:i:s');

        $remaining = max(0, (float) $this->total_amount - (float) $this->paid_amount - (float) $this->discount_amount);
        if ($remaining <= 0 && (float) $this->total_amount > 0) {
            $this->payment_status = (float) $this->discount_amount > 0 ? 'discounted' : 'paid';
        } elseif ((float) $this->paid_amount > 0 || (float) $this->discount_amount > 0) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'unpaid';
        }
        $this->save();

        if ($this->due_date) {
            $this->recalculateBucket();
        }
    }

    /** Batalkan 1 transaksi: kurangi paid_amount lalu sesuaikan status. */
    public function reversePayment(float $amount): void
    {
        $this->paid_amount = max(0, (float) $this->paid_amount - $amount);

        $remaining = max(0, (float) $this->total_amount - (float) $this->paid_amount - (float) $this->discount_amount);
        if ($remaining <= 0 && (float) $this->total_amount > 0) {
            $this->payment_status = (float) $this->discount_amount > 0 ? 'discounted' : 'paid';
        } elseif ((float) $this->paid_amount > 0 || (float) $this->discount_amount > 0) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'unpaid';
        }
        $this->save();
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
              ->orWhere('office_phone', 'like', "%{$search}%")
              ->orWhere('emergency_phone', 'like', "%{$search}%")
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

    public function scopeHandoverStatus($query, $status)
    {
        return $query->when($status, fn($q) => $q->where('handover_status', $status));
    }

    public function scopeBadDebt($query)
    {
        // Kandidat busuk: PTP rolling (termasuk legacy broken) ATAU NPL belum lunas ATAU DPD sangat tua
        return $query->where(function ($q) {
            $q->whereJsonContains('promise_to_pay->status', 'rolling')
              ->orWhereJsonContains('promise_to_pay->status', 'broken')
              ->orWhere(function ($sq) {
                  $sq->where('bucket', 'NPL')->whereIn('payment_status', ['unpaid', 'partial']);
              })
              ->orWhere('days_past_due', '>=', 120);
        })->whereIn('payment_status', ['unpaid', 'partial']);
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
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        return array_merge([
            'amount' => 0,
            'date' => null,
            'note' => '',
            'status' => 'new', // new, kept, rolling (legacy: pending=>new, broken=>rolling)
            'created_at' => null,
            'extend_count' => 0,
            'previous_date' => null,
            'extended_at' => null,
        ], (array) $value);
    }

    public function setPromiseToPay($amount, $date, $note = '')
    {
        $this->promise_to_pay = [
            'amount' => $amount,
            'date' => $date,
            'note' => $note,
            'status' => 'new',
            'created_at' => now()->toISOString(),
            'extend_count' => 0,
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

    /** Janji gagal / perlu dijadwal ulang (pengganti status broken lama). */
    public function markPromiseRolling()
    {
        $ptp = $this->getRawOriginal('promise_to_pay');
        if ($ptp) {
            $ptp = is_string($ptp) ? json_decode($ptp, true) : (is_object($ptp) ? (array) $ptp : $ptp);
            $ptp['status'] = 'rolling';
            $ptp['rolled_at'] = now()->toISOString();
            $this->promise_to_pay = $ptp;
            $this->save();
        }
    }

    /**
     * Request extend 1x: perpanjang tanggal janji, status kembali new.
     * Maksimal 1x per PTP (extend_count >= 1 ditolak).
     */
    public function requestPromiseExtend($date, $note = '')
    {
        $ptp = $this->getRawOriginal('promise_to_pay');
        $ptp = $ptp
            ? (is_string($ptp) ? json_decode($ptp, true) : (is_object($ptp) ? (array) $ptp : $ptp))
            : [];
        if (($ptp['extend_count'] ?? 0) >= 1) {
            throw new \RuntimeException('PTP ini sudah pernah di-extend 1x. Buat PTP baru bila perlu.');
        }
        $ptp['previous_date'] = $ptp['date'] ?? null;
        $ptp['date'] = $date;
        if ($note !== '') {
            $ptp['note'] = $note;
        }
        $ptp['status'] = 'new';
        $ptp['extend_count'] = ($ptp['extend_count'] ?? 0) + 1;
        $ptp['extended_at'] = now()->toISOString();
        $this->promise_to_pay = $ptp;
        $this->save();
    }

    public function hasActivePromise()
    {
        $ptp = $this->promise_to_pay;
        if (is_object($ptp)) $ptp = (array) $ptp;
        return $ptp && in_array($ptp['status'], ['new', 'pending'], true) && $ptp['date'] >= now()->toDateString();
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
        $dpd = $today->diffInDays($dueDate, false); // negative = overdue

        $this->days_past_due = max(0, -$dpd);

        // Rentang bucket bisa diatur dari menu Buckets (tabel bucket_ranges)
        [$bucket, $risk] = BucketRange::resolve($this->days_past_due);
        $this->bucket = $bucket;
        $this->risk_level = $risk;

        $this->save();
        return $this->bucket;
    }
}