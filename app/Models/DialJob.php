<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DialJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'buckets_config', 'lines_per_agent', 'max_attempts',
        'status', 'note', 'created_by', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'buckets_config' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(DialQueueItem::class, 'job_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function progress(): array
    {
        $counts = $this->items()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $total = array_sum($counts);

        return [
            'total' => $total,
            'queued' => $counts['queued'] ?? 0,
            'dialing' => $counts['dialing'] ?? 0,
            'done' => $counts['done'] ?? 0,
            'failed' => $counts['failed'] ?? 0,
            'skipped' => $counts['skipped'] ?? 0,
        ];
    }
}
