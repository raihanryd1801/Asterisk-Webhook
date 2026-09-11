<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FeatureFlag extends Model
{
    public const MODULES = [
        'crm' => 'CRM (Dashboard + Customers)',
        'collection' => 'Collection Banking',
        'dialer' => 'Auto-Dialer (PDS)',
    ];

    protected $fillable = ['key', 'label', 'is_enabled', 'updated_by'];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function isEnabled(string $key): bool
    {
        return (bool) Cache::remember("feature_flag_{$key}", 60, function () use ($key) {
            return static::where('key', $key)->value('is_enabled') ?? false;
        });
    }

    public static function setEnabled(string $key, bool $enabled, $userId = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'label' => static::MODULES[$key] ?? $key,
                'is_enabled' => $enabled,
                'updated_by' => $userId,
            ]
        );
        Cache::forget("feature_flag_{$key}");
    }

    public static function states(): array
    {
        $rows = static::whereIn('key', array_keys(static::MODULES))->pluck('is_enabled', 'key')->toArray();
        $out = [];
        foreach (static::MODULES as $key => $label) {
            $out[$key] = (bool) ($rows[$key] ?? false);
        }
        return $out;
    }
}
