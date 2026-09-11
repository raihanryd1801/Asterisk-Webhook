<?php

namespace App\Jobs;

use App\Models\BlastLog;
use App\Models\Customer;
use App\Services\WhatsAppGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessWaBlast implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(
        public int $blastId,
        public string $sessionId,
    ) {}

    public function handle(): void
    {
        $log = BlastLog::find($this->blastId);
        if (!$log || $log->status !== 'queued') {
            return;
        }

        $log->update(['status' => 'sending']);
        $gateway = app(WhatsAppGateway::class);

        $targets = Customer::where('bucket', $log->bucket)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNotNull('phone')
            ->orderBy('id')
            ->get(['id', 'name', 'phone']);

        $sent = 0;
        $failed = 0;

        foreach ($targets as $t) {
            try {
                $result = $gateway->send($this->sessionId, $t->phone, $log->message);
                if ($result['ok']) {
                    $sent++;
                    // Catat ke inbox pengirim agar blast terlihat di thread.
                    // Satukan ke thread kanonis customer bila sudah ada.
                    try {
                        [$threadPhone] = \App\Models\WaMessage::resolveThreadKey(
                            $this->sessionId,
                            \App\Models\WaMessage::normalizePhone($t->phone),
                            's.whatsapp.net',
                            $t->id
                        );
                        $existing = \App\Models\WaMessage::where('session_id', $this->sessionId)
                            ->where('phone', $threadPhone)
                            ->latest('id')
                            ->first(['jid_server', 'name']);
                        \App\Models\WaMessage::create([
                            'session_id' => $this->sessionId,
                            'direction' => 'out',
                            'phone' => $threadPhone,
                            'jid_server' => $existing?->jid_server ?? 's.whatsapp.net',
                            'name' => $existing?->name ?? $t->name,
                            'customer_id' => $t->id,
                            'message' => $log->message,
                            'occurred_at' => now(),
                            'read_at' => now(),
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning("WA blast #{$log->id} gagal catat inbox {$t->phone}: " . $e->getMessage());
                    }
                } else {
                    $failed++;
                    Log::warning("WA blast #{$log->id} gagal ke {$t->phone}: {$result['message']}");
                    // Sesi putus di tengah jalan -> hentikan, tandai sisanya gagal
                    if (str_contains(strtolower($result['message']), 'belum terhubung')) {
                        $failed += $targets->count() - $sent - $failed;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("WA blast #{$log->id} exception ke {$t->phone}: " . $e->getMessage());
            }

            if (($sent + $failed) % 10 === 0) {
                $log->update(['sent' => $sent, 'failed' => $failed]);
            }
        }

        $log->update([
            'sent' => $sent,
            'failed' => $failed,
            'total_target' => $targets->count(),
            'status' => $failed > 0 && $sent === 0 ? 'failed' : 'sent',
        ]);
    }
}
