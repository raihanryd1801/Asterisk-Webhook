<?php

namespace App\Services\Asterisk;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\DialJob;
use App\Models\DialQueueItem;
use App\Models\PdsRotation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PdsDialService
{
    protected OriginateService $originate;

    // Item dialing lebih tua dari ini dianggap nyangkut -> failed
    protected int $stuckMinutes = 10;

    public function __construct(OriginateService $originate)
    {
        $this->originate = $originate;
    }

    /**
     * Bangun antrian dari bucket aging report sesuai kuota per bucket.
     * Hanya debtor yang masih bisa ditagih: ada nomor, belum lunas,
     * belum diserahkan ke pihak ketiga, belum masuk job ini.
     */
    public function buildQueue(DialJob $job): array
    {
        $config = $job->buckets_config ?: [];
        $inserted = 0;
        $perBucket = [];
        $skipped = ['paid' => 0, 'handed_over' => 0, 'no_phone' => 0, 'duplicate' => 0];

        $existingCustomerIds = $job->items()->whereNotNull('customer_id')->pluck('customer_id')->toArray();

        foreach ($config as $bucket => $limit) {
            $limit = max(0, (int) $limit);
            if ($limit <= 0) {
                continue;
            }

            // Hitung alasan skip untuk transparansi (biar jelas kenapa tidak semua masuk)
            $base = Customer::where('bucket', $bucket);
            $skipped['paid'] += (clone $base)->whereNotIn('payment_status', ['unpaid', 'partial'])->count();
            $skipped['handed_over'] += (clone $base)->where('handover_status', 'handed_over')->count();
            $skipped['no_phone'] += (clone $base)->where(function ($q) {
                $q->whereNull('phone')->orWhere('phone', '');
            })->count();

            $customers = Customer::where('bucket', $bucket)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->where(function ($q) {
                    $q->whereNull('handover_status')
                      ->orWhere('handover_status', '!=', 'handed_over');
                })
                ->when(!empty($existingCustomerIds), function ($q) use (&$skipped, $existingCustomerIds) {
                    $dup = (clone $q)->whereIn('id', $existingCustomerIds)->count();
                    $skipped['duplicate'] += $dup;
                    $q->whereNotIn('id', $existingCustomerIds);
                })
                ->orderByDesc('days_past_due')
                ->orderByDesc('total_amount')
                ->limit($limit)
                ->get(['id', 'phone', 'bucket']);

            $perBucket[$bucket] = $customers->count();

            foreach ($customers as $customer) {
                DialQueueItem::create([
                    'job_id' => $job->id,
                    'customer_id' => $customer->id,
                    'phone' => $customer->phone,
                    'bucket' => $customer->bucket,
                    'status' => 'queued',
                ]);
                $inserted++;
                $existingCustomerIds[] = $customer->id;
            }
        }

        return ['inserted' => $inserted, 'per_bucket' => $perBucket, 'skipped' => $skipped];
    }

    /**
     * Agent standby = JOIN rotasi + status online + MicroSIP terdaftar + tidak sedang call.
     */
    public function idleRotationAgents(): array
    {
        $members = PdsRotation::with('agent')->get();
        $idle = [];

        foreach ($members as $member) {
            $agent = $member->agent;
            if (!$agent || $agent->status !== 'online') {
                continue;
            }
            if (Cache::get('active_call_' . $agent->extension)) {
                continue; // sedang ringing/connected
            }
            if (!$this->isMicrosipRegistered($agent->extension)) {
                continue;
            }
            $idle[] = $agent;
        }

        return $idle;
    }

    public function rotationCount(): int
    {
        return PdsRotation::count();
    }

    /**
     * Satu tick worker untuk sebuah job running.
     * Return array info: dialed, skipped_reason, completed.
     */
    public function tick(DialJob $job): array
    {
        // 1. Rekonsiliasi item dialing yang sudah selesai di Asterisk
        $this->reconcile($job);

        $idle = $this->idleRotationAgents();

        if (empty($idle)) {
            return [
                'dialed' => 0,
                'completed' => false,
                'skipped_reason' => 'Tidak ada agent standby (join rotation + online + idle). PDS menunggu.',
            ];
        }

        $inProgress = $job->items()->where('status', 'dialing')->count();
        $capacity = count($idle) * max(1, (int) $job->lines_per_agent) - $inProgress;

        if ($capacity <= 0) {
            return ['dialed' => 0, 'completed' => false, 'skipped_reason' => null];
        }

        $items = $job->items()
            ->where('status', 'queued')
            ->where('attempts', '<', max(1, (int) $job->max_attempts))
            ->orderBy('id')
            ->limit($capacity)
            ->get();

        // Tandai item yang sudah kehabisan attempt sebagai skipped
        $job->items()
            ->where('status', 'queued')
            ->where('attempts', '>=', max(1, (int) $job->max_attempts))
            ->update(['status' => 'skipped', 'note' => 'Max attempts tercapai']);

        $dialed = 0;
        $i = 0;

        foreach ($items as $item) {
            $agent = $idle[$i % count($idle)];
            $i++;

            // Cek ulang agent masih idle (bisa berubah dalam satu tick)
            if (Cache::get('active_call_' . $agent->extension)) {
                continue;
            }

            try {
                $this->originate->pdsDial($agent->extension, $item->phone, $job->id);

                $item->update([
                    'status' => 'dialing',
                    'attempts' => $item->attempts + 1,
                    'agent_extension' => $agent->extension,
                    'last_attempt_at' => now(),
                ]);

                // Penanda untuk badge PDS di live monitoring (TTL pengaman 15 menit)
                Cache::put('pds_call_' . $agent->extension, [
                    'job_id' => $job->id,
                    'job_name' => $job->name,
                    'destination' => $item->phone,
                ], now()->addMinutes(15));

                $dialed++;
            } catch (\Exception $e) {
                Log::warning("PDS job {$job->id}: gagal originate ke {$agent->extension} / {$item->phone}: " . $e->getMessage());
                $item->update(['status' => 'failed', 'note' => substr($e->getMessage(), 0, 200)]);
            }
        }

        $remaining = $job->items()->whereIn('status', ['queued', 'dialing'])->count();
        $completed = $remaining === 0;

        if ($completed) {
            $job->update(['status' => 'completed', 'finished_at' => now()]);
        }

        return ['dialed' => $dialed, 'completed' => $completed, 'skipped_reason' => null];
    }

    /**
     * Item dialing yang agent-nya sudah tidak ada di active call = selesai.
     * Item dialing yang terlalu lama = nyangkut -> failed.
     */
    protected function reconcile(DialJob $job): void
    {
        $dialing = $job->items()->where('status', 'dialing')->get();

        foreach ($dialing as $item) {
            $ext = $item->agent_extension;

            // Grace period: event AMI butuh waktu masuk. Jangan vonis selesai
            // sebelum 30 detik agar call yang baru di-originate tidak keburu
            // ditandai done padahal ringing-nya belum ketangkep ami:listen.
            if ($item->last_attempt_at && $item->last_attempt_at->gt(now()->subSeconds(30))) {
                continue;
            }

            if ($ext && !Cache::get('active_call_' . $ext)) {
                $item->update(['status' => 'done']);
                Cache::forget('pds_call_' . $ext);
                Log::info("PDS job {$job->id}: item #{$item->id} ({$item->phone}) done.");
                continue;
            }

            if ($item->last_attempt_at && $item->last_attempt_at->lt(now()->subMinutes($this->stuckMinutes))) {
                $item->update(['status' => 'failed', 'note' => 'Timeout: tidak ada update call']);
                if ($ext) {
                    Cache::forget('pds_call_' . $ext);
                }
                Log::warning("PDS job {$job->id}: item #{$item->id} ({$item->phone}) stuck -> failed.");
            }
        }
    }

    protected function isMicrosipRegistered(string $extension): bool
    {
        try {
            return DB::table('ps_contacts')->where('endpoint', $extension)->exists();
        } catch (\Exception $e) {
            // Tabel beda database / tidak ada -> anggap terdaftar (fallback longgar)
            return true;
        }
    }
}
