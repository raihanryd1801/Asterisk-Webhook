<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Asterisk\AmiClient;
use App\Models\Agent;
use App\Events\AgentCallActivity;
use Illuminate\Support\Facades\Cache; // 🚀 1. Import Facade Cache di sini

class ListenAsteriskEvents extends Command
{
    protected $signature = 'asterisk:listen';
    protected $description = 'Mendengarkan event real-time dari Asterisk AMI';

    public function handle()
    {
        $this->info("Menghubungkan ke Asterisk AMI...");

        $ami = new AmiClient();
        
        try {
            $ami->connect();
            $this->info("Berhasil terhubung ke AMI! Memantau panggilan secara real-time...");

            $activeCalls = [];

            $ami->listenToEvents(function ($event) use (&$activeCalls) {
                $eventName = $event['Event'] ?? '';
                $channel = $event['Channel'] ?? '';

                if (preg_match('/PJSIP\/(\d+)-/', $channel, $matches)) {
                    $extension = $matches[1];
                    $agent = Agent::where('extension', $extension)->first();
                    
                    if (!$agent) return;

                    // 1. Deteksi Nomor Tujuan & Trigger RINGING
                    if ($eventName === 'Newexten' || $eventName === 'OriginateResponse') {
                        $exten = $event['Exten'] ?? '';
                        
                        if (strlen($exten) > 3 && is_numeric($exten)) {
                            $currentState = $activeCalls[$extension]['state'] ?? '';
                            
                            if ($currentState !== 'ringing' && $currentState !== 'connected') {
                                $activeCalls[$extension] = ['dest' => $exten, 'state' => 'ringing'];
                                
                                $this->line("🔔 Agen Ext {$extension} RINGING ke tujuan: {$exten}");
                                broadcast(new AgentCallActivity($agent, $exten, 'ringing'));

                                // 🚀 2. SIMPAN KE CACHE SAAT RINGING
                                Cache::put('active_call_' . $extension, [
                                    'is_calling'  => true,
                                    'call_status' => 'ringing',
                                    'destination' => $exten
                                ], now()->addHours(2));
                            }
                        }
                    }

                    // 2. Deteksi saat Panggilan Diangkat / TERSAMBUNG
                    if ($eventName === 'BridgeEnter') {
                        if (isset($activeCalls[$extension])) {
                            $dest = $activeCalls[$extension]['dest'];
                            $activeCalls[$extension]['state'] = 'connected';
                            
                            $this->line("📞 Agen Ext {$extension} CONNECTED dengan: {$dest}");
                            broadcast(new AgentCallActivity($agent, $dest, 'connected'));

                            // 🚀 3. UPDATE CACHE JADI CONNECTED (SEDANG BICARA)
                            Cache::put('active_call_' . $extension, [
                                'is_calling'  => true,
                                'call_status' => 'connected',
                                'destination' => $dest
                            ], now()->addHours(2));
                        }
                    }

                    // 3. Deteksi saat Panggilan DITUTUP
                    if ($eventName === 'HangupRequest' || $eventName === 'SoftHangupRequest' || $eventName === 'Hangup') {
                        if (isset($activeCalls[$extension])) {
                            $this->line("❌ Agen Ext {$extension} PANGGILAN SELESAI (Ended)");
                            broadcast(new AgentCallActivity($agent, null, 'ended'));
                            
                            // 🚀 4. HAPUS CACHE KETIGA SELESAI
                            Cache::forget('active_call_' . $extension);

                            unset($activeCalls[$extension]);
                        }
                    }
                }
            });

        } catch (\Exception $e) {
            $this->error("Error AMI: " . $e->getMessage());
            sleep(5);
            $this->call('asterisk:listen');
        }
    }
}