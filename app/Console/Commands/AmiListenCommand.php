<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Asterisk\AmiClient;
use App\Models\Agent;
use Illuminate\Support\Facades\DB;
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

        // Loop reconnect biasa (bukan rekursi $this->call('ami:listen') seperti
        // sebelumnya) -- supaya kalau koneksi AMI putus-nyambung berkali-kali
        // selama daemon jalan lama, call stack tidak menumpuk terus-terusan.
        while (true) {
            try {
                $ami->connect();
                $this->info("Berhasil terhubung ke AMI! Memantau panggilan secara real-time...");

                $activeCalls = []; // Format: [ extension => ['dest' => ..., 'linkedid' => ..., 'cause' => ...] ]

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
                // lanjut ke iterasi while(true) berikutnya -> coba connect() lagi
            }
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
        $channel   = $event['Channel'] ?? '';
        $linkedId  = $event['Linkedid'] ?? '';

        // 1. DETEKSI STATUS PERANGKAT (ONLINE / OFFLINE)
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

        // 2. TANGKAP CAUSE CODE DARI SEMUA CHANNEL BERDASARKAN LINKEDID (Termasuk Trunk)
        if (!empty($linkedId)) {
            foreach ($activeCalls as $ext => &$callInfo) {
                if ($callInfo['linkedid'] === $linkedId) {

                    $cause = $event['Cause'] ?? null;

                    // PENTING: cause "16 = Normal Clearing" TIDAK BOLEH menimpa cause
                    // gagal yang sudah tertangkap sebelumnya. Ini karena channel milik
                    // agen (mis. PJSIP/105) sering hang up dengan cause 16 di akhir
                    // (misal karena masuk app-blackhole -> Hangup()) MESKIPUN trunk-nya
                    // tadi gagal dengan cause lain (mis. 21 = Forbidden). Kalau cause 16
                    // ini dibiarkan menimpa, hasil akhirnya jadi salah (kelihatan sukses
                    // padahal gagal). Cause hanya boleh "membaik" jadi normal lewat
                    // BridgeEnter (bukti call benar-benar tersambung), lihat poin B.
                    if ($cause !== null && (int)$cause !== 16) {
                        $callInfo['cause']     = $cause;
                        $callInfo['cause_txt'] = $event['Cause-txt'] ?? 'Unknown Error';
                    }

                    // DETEKSI SIAPA YANG MENUTUP DULUAN (agent vs tujuan).
                    // Channel yang PERTAMA KALI mengirim HangupRequest itulah yang
                    // memulai penutupan. Kalau channel-nya sesuai pola ekstensi agen
                    // (mis. PJSIP/105-xxxx), berarti agent yang menutup. Kalau bukan
                    // (channel trunk, mis. SIP/vos10-xxxx), berarti pihak tujuan/trunk.
                    // Catatan: ini murni logic PHP di memory (preg_match), TIDAK ada
                    // query database sama sekali, jadi tidak berpengaruh ke kecepatan insert.
                    if (empty($callInfo['terminated_by'])
                        && in_array($eventName, ['HangupRequest', 'SoftHangupRequest'], true)) {
                        $hangupChannel = $event['Channel'] ?? '';
                        if (preg_match('/(PJSIP|SIP)\/' . preg_quote($ext, '/') . '-/', $hangupChannel)) {
                            $callInfo['terminated_by'] = 'agent';
                        } else {
                            $callInfo['terminated_by'] = 'tujuan';
                        }
                    }
                }
            }
            unset($callInfo);
        }

        // 3. DETEKSI AKTIVITAS TELEPON BERDASARKAN EKSTENSI AGEN
        if (preg_match('/(PJSIP|SIP)\/(\d+)-/', $channel, $matches)) {
            $extension = $matches[2];
            $agent = Agent::where('extension', $extension)->first();

            if ($agent) {
                // A. Deteksi Nomor Tujuan & Simpan Linkedid saat Ringing/Dial
                if ($eventName === 'Newexten' || $eventName === 'OriginateResponse' || $eventName === 'DialBegin') {
                    $exten = $event['Exten'] ?? $event['DestChannel'] ?? '';

                    // PENTING: abaikan ekstensi sistem/dialplan khusus seperti "h" (hangup
                    // handler), "s" (start), "t" (timeout), "i" (invalid), dll. Context-context
                    // ini ikut memicu Newexten pada channel yang sama SETELAH call sebenarnya
                    // selesai, dan kalau tidak difilter akan kebaca sebagai "call baru" ke
                    // tujuan palsu (mis. tujuan: "h"). Nomor tujuan asli selalu berupa digit.
                    $isRealDestination = $exten !== '' && preg_match('/^\+?[0-9]+$/', $exten);

                    if (!empty($linkedId) && $isRealDestination) {
                        // Kalau linkedid sudah ada sebelumnya (retry/failover ke trunk lain),
                        // pertahankan data 'dest' lama, cukup pastikan cause direset.
                        if (!isset($activeCalls[$extension]) || $activeCalls[$extension]['linkedid'] !== $linkedId) {
                            $activeCalls[$extension] = [
                                'dest'          => $exten,
                                'linkedid'      => $linkedId,
                                'uniqueid'      => $event['Uniqueid'] ?? null, // buat update cdr_live langsung, tanpa nebak
                                'cause'         => '16', // Default normal
                                'cause_txt'     => 'Normal Clearing',
                                'answered'      => false, // Belum tersambung/dijawab
                                'terminated_by' => null,  // 'agent' atau 'tujuan'
                            ];

                            $this->line("🔔 Agen Ext {$extension} RINGING ke tujuan: {$exten} (Linkedid: {$linkedId})");

                            // Broadcast supaya live monitoring langsung baca ada call baru + nomor tujuannya
                            broadcast(new AgentCallActivity($agent, $exten, 'ringing'));
                        }
                    }
                }

                // B. Deteksi saat Panggilan Diangkat / TERSAMBUNG
                if ($eventName === 'BridgeEnter') {
                    if (isset($activeCalls[$extension])) {
                        // Call berhasil connect -> pastikan cause bersih (normal)
                        // dan tandai sudah terjawab (penting untuk membedakan
                        // 200 OK vs 487 Request Terminated nantinya)
                        $activeCalls[$extension]['cause']     = '16';
                        $activeCalls[$extension]['cause_txt'] = 'Normal Clearing';
                        $activeCalls[$extension]['answered']  = true;

                        $dest = $activeCalls[$extension]['dest'] ?? '';

                        $this->line("📞 Agen Ext {$extension} CONNECTED ke {$dest}");
                        broadcast(new AgentCallActivity($agent, $dest, 'connected'));
                    }
                }

                // C. Deteksi saat Panggilan DITUTUP (Hangup)
                if ($eventName === 'HangupRequest' || $eventName === 'SoftHangupRequest' || $eventName === 'Hangup') {
                    if (isset($activeCalls[$extension]) && $activeCalls[$extension]['linkedid'] === $linkedId) {
                        $finalCause    = $activeCalls[$extension]['cause'];
                        $finalCauseTxt = $activeCalls[$extension]['cause_txt'];
                        $answered      = $activeCalls[$extension]['answered'] ?? false;

                        // Jika di detik-detik akhir ada cause code langsung dari event hangup ini,
                        // pakai HANYA kalau itu cause yang "bermakna" (bukan 16/normal) ATAU kalau
                        // sebelumnya belum ada cause gagal tersimpan sama sekali. Ini mencegah
                        // hangup normal dari channel agen sendiri (setelah trunk gagal) menimpa
                        // cause asli kegagalannya (mis. 21/Forbidden).
                        $eventCause = $event['Cause'] ?? null;
                        if ($eventCause !== null && ((int)$eventCause !== 16 || (int)$finalCause === 16)) {
                            $finalCause = $eventCause;
                            $finalCauseTxt = $event['Cause-txt'] ?? $finalCauseTxt;
                        }

                        $sipErrorCode = $this->mapCauseToSipCode($finalCause, $finalCauseTxt, $answered);

                        $dest = $activeCalls[$extension]['dest'] ?? '';

                        // Label siapa yang menutup duluan: "Agent" atau nomor tujuannya
                        $terminatedBy = $activeCalls[$extension]['terminated_by'] ?? null;
                        $actorLabel = null;
                        if ($terminatedBy === 'agent') {
                            $actorLabel = 'Agent';
                        } elseif ($terminatedBy === 'tujuan') {
                            $actorLabel = $dest !== '' ? $dest : 'Tujuan';
                        }

                        $sipDisplay = $sipErrorCode;
                        if ($actorLabel) {
                            $sipDisplay .= " (by {$actorLabel})";
                        }

                        $this->line("❌ Agen Ext {$extension} SELESAI ke {$dest} | Final Cause: {$finalCause} ({$finalCauseTxt}) -> SIP: {$sipDisplay}");

                        // Simpan sip_code ke cdr_live TANPA nunggu cdr:sync (yang cuma jalan
                        // tiap 1 menit via cron).
                        //
                        // PENTING: pakai upsert() -- SATU query atomic ke MySQL (INSERT ...
                        // ON DUPLICATE KEY UPDATE), BUKAN exists()-lalu-insert() dua langkah.
                        // Pola dua langkah itu rawan race condition: ada celah waktu antara
                        // "cek row ada apa nggak" dan "insert beneran", dan di celah itu
                        // cdr:sync (cron tiap menit) bisa aja nyelonong duluan nulis row yang
                        // sama (karena cdr.conf mode-nya Simple, CDR resmi dari Asterisk juga
                        // nyaris real-time) -> hasilnya "Duplicate entry" error yang bikin
                        // seluruh daemon ami:listen crash.
                        //
                        // Dengan upsert(), MySQL sendiri yang jamin atomicity: kalau uniqueid
                        // belum ada -> insert row baru. Kalau sudah ada (row dari cdr:sync
                        // dengan calldate ASLI sudah lebih dulu masuk) -> HANYA sip_code yang
                        // di-update (lihat parameter ketiga), calldate/kolom lain yang sudah
                        // benar tidak ikut ketimpa.
                        $uniqueid = $activeCalls[$extension]['uniqueid'] ?? null;

                        $dbTimerStart = microtime(true);

                        try {
                            if (!empty($uniqueid)) {
                                DB::table('cdr_live')->upsert(
                                    [
                                        'uniqueid'      => $uniqueid,
                                        'sip_code'      => $sipErrorCode,
                                        'terminated_by' => $actorLabel, // "Agent" atau nomor tujuan
                                        'src'           => $extension,
                                        'dst'           => $dest,
                                        'calldate'      => now()->toDateTimeString(), // dipakai HANYA kalau insert baru
                                        'disposition'   => $answered ? 'ANSWERED' : 'NO ANSWER',
                                        'duration'      => 0,
                                        'billsec'       => 0,
                                    ],
                                    ['uniqueid'],                    // kolom unique buat deteksi row yang sudah ada
                                    ['sip_code', 'terminated_by']    // kalau row SUDAH ADA, cuma 2 kolom ini yang di-update
                                );
                            } else {
                                // Fallback kalau Uniqueid entah kenapa tidak tertangkap sama sekali
                                DB::table('cdr_live')
                                    ->where('src', $extension)
                                    ->latest('calldate')
                                    ->limit(1)
                                    ->update(['sip_code' => $sipErrorCode]);
                            }
                        } catch (\Exception $dbException) {
                            // Jangan biarkan error DB sesaat (mis. koneksi putus 1 detik)
                            // bikin SELURUH daemon AMI listener ikut mati. Cukup log,
                            // lanjut proses event berikutnya seperti biasa.
                            $this->error("⚠️  Gagal simpan sip_code ke cdr_live (uniqueid={$uniqueid}): " . $dbException->getMessage());
                        }

                        $dbElapsedMs = round((microtime(true) - $dbTimerStart) * 1000, 2);
                        $this->line("⏱️  cdr_live diperbarui dalam {$dbElapsedMs} ms (uniqueid={$uniqueid})");

                        broadcast(new AgentCallActivity($agent, $dest, 'ended'));

                        Cache::forget('active_call_' . $extension);
                        unset($activeCalls[$extension]);
                    }
                }
            }
        }
    }

    /**
     * Mapping Q.850 Cause Code -> SIP Response Code.
     * Disesuaikan dengan tabel resmi hangup_cause2sip() milik Asterisk (chan_sip.c)
     * supaya hasilnya konsisten dengan respons SIP yang sebenarnya diterima dari trunk.
     *
     * @param mixed $cause    Q.850 cause code dari Asterisk
     * @param string $causeTxt Teks cause dari Asterisk (fallback label)
     * @param bool  $answered  True jika call sempat tersambung (BridgeEnter pernah terjadi)
     */
    private function mapCauseToSipCode($cause, $causeTxt, bool $answered = false)
    {
        $cause = (int)$cause;

        // PENTING: cause 16 (Normal Clearing) dan 127 (Interworking/generic) adalah
        // dua nilai "default" yang muncul saat hangup dipicu LOKAL (mis. agent klik
        // tombol hangup atau Hangup() dipanggil langsung di dialplan) — BUKAN dari
        // penolakan jaringan/trunk yang eksplisit (403/486/dst, yang selalu ditangani
        // switch di bawah). Kalau call ini belum pernah sempat tersambung (belum ada
        // BridgeEnter), maka secara SIP yang benar itu bukan "200 OK" atau
        // "500 Server Internal Error", melainkan panggilan yang DIBATALKAN sebelum
        // diangkat = 487 Request Terminated.
        if (!$answered && in_array($cause, [16, 127], true)) {
            return '487 Request Terminated';
        }

        switch ($cause) {
            case 1:  // Unallocated Number
            case 2:  // No Route Transit Network
            case 3:  // No Route Destination
                return '404 Not Found';
            case 16: // Normal Clearing
                return '200 OK';
            case 17: // User Busy
                return '486 Busy Here';
            case 18: // No User Response
                return '408 Request Timeout';
            case 19: // No Answer From User
                return '480 Temporarily Unavailable';
            case 21: // Call Rejected
                return '403 Forbidden';
            case 22: // Number Changed
                return '410 Gone';
            case 28: // Invalid Number Format
                return '484 Address Incomplete';
            case 29: // Facility Rejected
                return '501 Not Implemented';
            case 31: // Normal, Unspecified
                return '480 Temporarily Unavailable';
            case 34: // No Circuit/Channel Available (Congestion)
                return '503 Service Unavailable (Congestion)';
            case 38: // Network Out of Order
            case 42: // Switching Equipment Congestion
            case 44: // Requested Channel Not Available
            case 50: // Facility Not Subscribed
                return '503 Service Unavailable';
            case 52: // Outgoing Call Barred
            case 54: // Incoming Call Barred
            case 57: // Bearer Capability Not Authorized
                return '403 Forbidden';
            case 58: // Bearer Capability Not Available
                return '503 Service Unavailable';
            case 65: // Bearer Capability Not Implemented
                return '488 Not Acceptable Here';
            case 66: // Channel Not Implemented
            case 69: // Facility Not Implemented
                return '501 Not Implemented';
            case 81: // Invalid Call Reference
                return '481 Call Leg Does Not Exist';
            case 88: // Incompatible Destination
                return '503 Service Unavailable';
            case 96: // Mandatory IE Missing
                return '400 Bad Request';
            case 102: // Recovery on Timer Expire
                return '504 Server Time-out';
            case 111: // Protocol Error
                return '500 Server Internal Error';
            case 127: // Interworking
                return '500 Server Internal Error';
            default:
                return "{$causeTxt} ({$cause})";
        }
    }
}