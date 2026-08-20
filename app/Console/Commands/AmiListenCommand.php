<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Asterisk\AmiClient;

class AmiListenCommand extends Command
{
    // Command ini akan kita biarkan jalan terus di background nantinya
    protected $signature = 'ami:listen';
    protected $description = 'Daemon untuk mendengarkan event Asterisk secara realtime';

    public function handle(AmiClient $ami)
    {
        $this->info("Memulai daemon AMI Listener...");

        try {
            $ami->connect();
            $this->info("✅ Berhasil login, siap mendengarkan event dari FreePBX...");

            // Infinite loop untuk menangkap aliran data terus-menerus
            while (true) {
                $response = $ami->readResponse();
                
                if (empty(trim($response))) {
                    continue; // Skip kalau kosong (timeout socket)
                }

                // Ubah string balasan AMI menjadi Array agar mudah difilter
                $eventData = $this->parseEvent($response);

                // Kita hanya tertarik mem-filter Event (mengabaikan Response sukses/gagal biasa)
                if (isset($eventData['Event'])) {
                    $this->processEvent($eventData);
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Koneksi terputus: " . $e->getMessage());
        }
    }

    /**
     * Helper untuk mengubah format teks AMI menjadi Associative Array
     */
    private function parseEvent($response)
    {
        $lines = explode("\r\n", trim($response));
        $data = [];
        
        foreach ($lines as $line) {
            if (str_contains($line, ': ')) {
                [$key, $value] = explode(': ', $line, 2);
                $data[trim($key)] = trim($value);
            }
        }
        
        return $data;
    }

    /**
     * Filter dan proses event yang relevan untuk CRM
     */
    private function processEvent($event)
    {
        $watchedEvents = ['Newchannel', 'DialBegin', 'DialEnd', 'Hangup', 'BridgeEnter'];

        if (in_array($event['Event'], $watchedEvents)) {
            $this->line("🔔 Event Masuk: <fg=green>{$event['Event']}</>");
            
            $channel  = $event['Channel'] ?? $event['DestChannel'] ?? 'N/A';
            $callerId = $event['CallerIDNum'] ?? $event['ConnectedLineNum'] ?? 'N/A';
            $target   = $event['Exten'] ?? $event['DestExten'] ?? $event['DialString'] ?? 'N/A';

            $payload = [
                'event'     => $event['Event'],
                'channel'   => $channel,
                'caller_id' => $callerId,
                'target'    => $target,
                'timestamp' => now()->toDateTimeString()
            ];

            // 🚀 Broadcast data event panggilan secara real-time via Reverb
            broadcast(new \App\Events\CallStatusUpdated($payload));

            $this->comment("   Channel   : {$channel}");
            $this->comment("   Caller ID : {$callerId}");
            $this->comment("   Target    : {$target}");
            $this->line("-------------------------------------------------");
        }
    
    }
}