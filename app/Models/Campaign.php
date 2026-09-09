<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'name', 'code', 'description', 'type', 'target_buckets', 'channels',
        'max_attempts_per_day', 'start_time', 'end_time', 'schedule_days',
        'script_template', 'is_active', 'start_date', 'end_date', 'created_by',
    ];

    protected $casts = [
        'target_buckets' => 'array',
        'channels' => 'array',
        'schedule_days' => 'array',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class, 'campaign_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function getActiveChannelsAttribute()
    {
        return $this->channels ?? ['call', 'sms'];
    }

    public function getActiveDaysAttribute()
    {
        return $this->schedule_days ?? [1, 2, 3, 4, 5]; // Mon-Fri
    }

    public function isWithinSchedule($dateTime = null)
    {
        $dateTime = $dateTime ?? now();
        
        if ($this->start_date && $dateTime->lt($this->start_date)) return false;
        if ($this->end_date && $dateTime->gt($this->end_date)) return false;
        
        $dayOfWeek = (int) $dateTime->format('N'); // 1=Mon, 7=Sun
        if (!in_array($dayOfWeek, $this->active_days)) return false;
        
        $currentTime = $dateTime->format('H:i:s');
        return $currentTime >= $this->start_time->format('H:i:s') 
            && $currentTime <= $this->end_time->format('H:i:s');
    }
}