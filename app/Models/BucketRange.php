<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class BucketRange extends Model
{
    protected $fillable = ['bucket', 'min_dpd', 'max_dpd', 'risk_level', 'sort'];

    protected $casts = [
        'min_dpd' => 'integer',
        'max_dpd' => 'integer',
    ];

    public static function defaults(): array
    {
        return [
            ['bucket' => 'Current', 'min_dpd' => 0, 'max_dpd' => 0, 'risk_level' => 'low', 'sort' => 1],
            ['bucket' => 'Bucket 1', 'min_dpd' => 1, 'max_dpd' => 30, 'risk_level' => 'low', 'sort' => 2],
            ['bucket' => 'Bucket 2', 'min_dpd' => 31, 'max_dpd' => 60, 'risk_level' => 'medium', 'sort' => 3],
            ['bucket' => 'Bucket 3', 'min_dpd' => 61, 'max_dpd' => 90, 'risk_level' => 'high', 'sort' => 4],
            ['bucket' => 'NPL', 'min_dpd' => 91, 'max_dpd' => null, 'risk_level' => 'critical', 'sort' => 5],
        ];
    }

    public static function allRules(): array
    {
        return Cache::remember('bucket_ranges', 300, function () {
            if (!Schema::hasTable('bucket_ranges')) {
                return static::defaults();
            }
            $rows = static::orderBy('sort')->get()->toArray();
            return $rows !== [] ? $rows : static::defaults();
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget('bucket_ranges');
    }

    /** Resolve DPD -> [bucket, risk_level]. */
    public static function resolve(int $dpd): array
    {
        foreach (static::allRules() as $rule) {
            $min = (int) ($rule['min_dpd'] ?? 0);
            $max = $rule['max_dpd'] === null ? null : (int) $rule['max_dpd'];
            if ($dpd >= $min && ($max === null || $dpd <= $max)) {
                return [$rule['bucket'], $rule['risk_level'] ?? 'low'];
            }
        }
        return ['NPL', 'critical'];
    }

    public static function describe(array $rule): string
    {
        $max = $rule['max_dpd'] === null ? '+' : $rule['max_dpd'];
        if ((int) ($rule['min_dpd'] ?? 0) === 0 && ((int) ($rule['max_dpd'] ?? -1)) === 0) {
            return '0 (belum tempo)';
        }
        return $rule['min_dpd'] . '–' . $max . ' hari';
    }
}
