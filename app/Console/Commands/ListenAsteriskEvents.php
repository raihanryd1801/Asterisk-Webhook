<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Asterisk\AmiClient;
use App\Models\Agent;
use App\Events\AgentCallActivity;

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

            // Array pintar untuk menyimpan status spesifik setiap ekstensi agen
            $activeCalls = [];

            $ami->listenToEvents(function ($event) use (&$activeCalls) {
                $eventName = $event['Event'] ?? '';
                $channel = $event['Channel'] ?? '';

                // Tangkap hanya channel yang memiliki format PJSIP/EKSTENSI (misal: PJSIP/101)
                if (preg_match('/PJSIP\/(\d+)-/', $channel, $matches)) {
                    $extension = $matches[1];
                    $agent = Agent::where('extension', $extension)->first();
                    
                    if (!$agent) return;

                    // 1. Deteksi Nomor Tujuan & Trigger RINGING
                    if ($eventName === 'Newexten' || $eventName === 'OriginateResponse') {
                        $exten = $event['Exten'] ?? '';
                        
                        // Pastikan itu nomor HP asli (berupa angka dan panjang lebih dari 3 digit)
                        if (strlen($exten) > 3 && is_numeric($exten)) {
                            $currentState = $activeCalls[$extension]['state'] ?? '';
                            
                            // Cegah pengiriman data berulang kali jika statusnya sudah ringing/connected
                            if ($currentState !== 'ringing' && $currentState !== 'connected') {
                                $activeCalls[$extension] = ['dest' => $exten, 'state' => 'ringing'];
                                
                                $this->line("🔔 Agen Ext {$extension} RINGING ke tujuan: {$exten}");
                                broadcast(new AgentCallActivity($agent, $exten, 'ringing'));
                            }
                        }
                    }

                    // 2. Deteksi saat Panggilan Diangkat / TERSAMBUNG
                    if ($eventName === 'BridgeEnter') {
                        if (isset($activeCalls[$extension])) {
                            $dest = $activeCalls[$extension]['dest'];
                            $activeCalls[$extension]['state'] = 'connected'; // Kunci statusnya
                            
                            $this->line("📞 Agen Ext {$extension} CONNECTED dengan: {$dest}");
                            broadcast(new AgentCallActivity($agent, $dest, 'connected'));
                        }
                    }

                    // 3. Deteksi saat Panggilan DITUTUP
                    if ($eventName === 'HangupRequest' || $eventName === 'SoftHangupRequest' || $eventName === 'Hangup') {
                        if (isset($activeCalls[$extension])) {
                            $this->line("❌ Agen Ext {$extension} PANGGILAN SELESAI (Ended)");
                            broadcast(new AgentCallActivity($agent, null, 'ended'));
                            
                            // Bersihkan memori agen tersebut agar siap menerima panggilan baru
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