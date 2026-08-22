<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Asterisk\AmiClient;
use App\Models\Agent;
use App\Events\AgentCallActivity;
use App\Events\AgentStatusUpdated;
use Illuminate\Support\Facades\Cache;

class AmiListenCommand extends Command
{
    protected $signature = 'ami:listen';
    protected $description = 'Daemon untuk mendengarkan event Asterisk secara realtime';

    public function handle(AmiClient $ami)
    {
        $this->info("Memulai daemon AMI Listener...");

        try {
            $ami->connect();
            $this->info("Berhasil terhubung ke AMI! Memantau panggilan secara real-time...");

            $activeCalls = [];

            while (true) {
                $response = $ami->readResponse();
                
                if (empty(trim($response))) {
                    continue; 
                }

                $eventData = $this->parseEvent($response);

                if (isset($eventData['Event'])) {
                    $this->processEvent($eventData, $activeCalls);
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Koneksi terputus: " . $e->getMessage());
            sleep(5);
            $this->call('ami:listen');
        }
    }

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

    private function processEvent($event, &$activeCalls)
    {
        $eventName = $event['Event'] ?? '';

        // ==========================================
        // 1. DETEKSI STATUS PERANGKAT (ONLINE / OFFLINE)
        // ==========================================
        if ($eventName === 'DeviceStateChange') {
            $device = $event['Device'] ?? ''; 
            $state  = $event['State'] ?? '';   

            if (preg_match('/(PJSIP|SIP)\/(\d+)/', $device, $matches)) {
                $extension = $matches[2];
                $agent = Agent::where('extension', $extension)->first();

                if ($agent) {
                    $isOnline = !in_array($state, ['UNAVAILABLE', 'INVALID', 'UNKNOWN']);
                    $newStatus = $isOnline ? 'online' : 'offline';

                    if ($agent->status !== $newStatus) {
                        $agent->status = $newStatus;
                        $agent->save();

                        $this->line("🔄 Agen Ext {$extension} berubah status menjadi: <fg=cyan>{$newStatus}</> (State: {$state})");
                        broadcast(new AgentStatusUpdated($agent));
                    }
                }
            }
            return;
        }

        // ==========================================
        // 2. DETEKSI AKTIVITAS TELEPON (PERSIS FORMAT LAMA)
        // ==========================================
        $channel = $event['Channel'] ?? '';

        if (preg_match('/(PJSIP|SIP)\/(\d+)-/', $channel, $matches)) {
            $extension = $matches[2];
            $agent = Agent::where('extension', $extension)->first();
            
            if ($agent) {
                // A. Deteksi Nomor Tujuan & Trigger RINGING[cite: 2]
                if ($eventName === 'Newexten' || $eventName === 'OriginateResponse') {
                    $exten = $event['Exten'] ?? '';
                    
                    if (strlen($exten) > 3 && is_numeric($exten)) {
                        $currentState = $activeCalls[$extension]['state'] ?? '';
                        
                        if ($currentState !== 'ringing' && $currentState !== 'connected') {
                            $activeCalls[$extension] = ['dest' => $exten, 'state' => 'ringing'];
                            
                            $this->line("🔔 Agen Ext {$extension} RINGING ke tujuan: {$exten}");
                            broadcast(new AgentCallActivity($agent, $exten, 'ringing'));

                            Cache::put('active_call_' . $extension, [
                                'is_calling'  => true,
                                'call_status' => 'ringing',
                                'destination' => $exten
                            ], now()->addHours(2));
                        }
                    }
                }

                // B. Deteksi saat Panggilan Diangkat / TERSAMBUNG[cite: 2]
                if ($eventName === 'BridgeEnter') {
                    if (isset($activeCalls[$extension])) {
                        $dest = $activeCalls[$extension]['dest'];
                        $activeCalls[$extension]['state'] = 'connected';
                        
                        $this->line("📞 Agen Ext {$extension} CONNECTED dengan: {$dest}");
                        broadcast(new AgentCallActivity($agent, $dest, 'connected'));

                        Cache::put('active_call_' . $extension, [
                            'is_calling'  => true,
                            'call_status' => 'connected',
                            'destination' => $dest
                        ], now()->addHours(2));
                    }
                }

                // C. Deteksi saat Panggilan DITUTUP[cite: 2]
                if ($eventName === 'HangupRequest' || $eventName === 'SoftHangupRequest' || $eventName === 'Hangup') {
                    if (isset($activeCalls[$extension])) {
                        $this->line("❌ Agen Ext {$extension} PANGGILAN SELESAI (Ended)");
                        broadcast(new AgentCallActivity($agent, null, 'ended'));
                        
                        Cache::forget('active_call_' . $extension);
                        unset($activeCalls[$extension]);
                    }
                }
            }
        }
    }
}