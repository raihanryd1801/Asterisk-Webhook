<?php

namespace App\Services\Asterisk;

use Exception;

class OriginateService
{
    protected $ami;

    public function __construct(AmiClient $ami)
    {
        $this->ami = $ami;
    }

    /**
     * Fitur Click-to-Dial untuk Agent
     * 
     * @param string $agentExt Extension milik agent (misal: "101")
     * @param string $targetNumber Nomor tujuan (misal: "08123456789" atau ext lain "102")
     */
    public function clickToDial($agentExt, $targetNumber)
    {
        try {
            $this->ami->connect();

            // Karena pakai FreePBX terbaru, kita asumsikan menggunakan PJSIP
            $channel = "PJSIP/" . $agentExt;

            $parameters = [
                'Channel'  => $channel,
                'Exten'    => $targetNumber,
                'Context'  => 'from-internal', 
                'Priority' => 1,
                'Timeout'  => 30000,          // Agent punya waktu 30 detik untuk angkat
                // DIUBAH DISINI: Agar CallerID tercatat sebagai agent, bukan nomor luar
                'CallerID' => "agent {$agentExt} <{$agentExt}>", 
                'Async'    => 'true'          // Wajib true agar PHP tidak hang menunggu call selesai
            ];

            // Kirim perintah Originate
            $this->ami->sendAction('Originate', $parameters);
            
            // Baca response (biasanya "Response: Success")
            $response = $this->ami->readResponse();

            $this->ami->disconnect();

            return $response;

        } catch (Exception $e) {
            throw new Exception("Gagal melakukan Originate: " . $e->getMessage());
        }
    }

    /**
     * Originate khusus PDS (predictive/progressive dialer).
     * Sama seperti clickToDial (kaki AGENT ditelepon DULUAN, kaki customer
     * baru jalan setelah agent angkat) sehingga secara struktur tidak mungkin
     * customer tersambung tanpa agent. Bedanya: CallerID bertanda PDS dan
     * membawa variable __PDS_JOB untuk traceability di AMI/CDR.
     */
    public function pdsDial($agentExt, $targetNumber, $jobId = null)
    {
        try {
            $this->ami->connect();

            $parameters = [
                'Channel'  => "PJSIP/" . $agentExt,
                'Exten'    => $targetNumber,
                'Context'  => 'from-internal',
                'Priority' => 1,
                'Timeout'  => 30000,
                'CallerID' => "PDS Job {$jobId} <{$agentExt}>",
                'Async'    => 'true',
            ];

            if ($jobId !== null) {
                $parameters['Variable'] = "__PDS_JOB={$jobId}";
            }

            $this->ami->sendAction('Originate', $parameters);
            $response = $this->ami->readResponse();

            $this->ami->disconnect();

            return $response;
        } catch (Exception $e) {
            throw new Exception("Gagal melakukan PDS Originate: " . $e->getMessage());
        }
    }

    /**
     * Originate PDS MURNI (customer-first / predictive).
     * Kaki CUSTOMER didial duluan lewat outbound route (Local channel),
     * context tujuan = [pds-connect] di extensions_custom.conf. Ketika
     * customer mengangkat, dialplan memanggil AGI pds-bridge.php yang
     * menanyakan ext agent ke Laravel, lalu Dial ke agent tersebut.
     *
     * Reservasi agent TETAP dicatat di dial_queue_items.agent_extension
     * oleh PdsDialService::tick() — AGI memakainya sebagai pilihan utama
     * dan memvalidasi ulang sebelum Dial.
     */
    public function pdsCustomerFirst($targetNumber, $jobId, $itemId)
    {
        try {
            $this->ami->connect();

            $context = config('services.pds.connect_context', 'pds-connect');
            $callerid = config('services.pds.callerid', '');
            $channel = 'Local/' . $targetNumber . '@from-internal';

            $parameters = [
                'Channel'  => $channel,
                'Exten'    => 's',
                'Context'  => $context,
                'Priority' => 1,
                'Timeout'  => 45000,
                'Async'    => 'true',
                'Variable' => "__PDS_JOB={$jobId},__PDS_ITEM={$itemId}",
            ];

            if ($callerid !== '') {
                $parameters['CallerID'] = $callerid;
            }

            $this->ami->sendAction('Originate', $parameters);
            $response = $this->ami->readResponse();

            $this->ami->disconnect();

            return $response;
        } catch (Exception $e) {
            throw new Exception("Gagal melakukan PDS customer-first Originate: " . $e->getMessage());
        }
    }

    /**
     * Daftarkan agent sebagai member queue PDS (dipanggil saat JOIN rotation).
     * Best-effort: gagal AMI tidak boleh menggagalkan join rotation.
     */
    public function queueAdd($queue, $extension, $memberName = null): bool
    {
        try {
            $this->ami->connect();
            $this->ami->sendAction('QueueAdd', [
                'Queue' => $queue,
                'Interface' => 'PJSIP/' . $extension,
                'MemberName' => $memberName ?: $extension,
                'Paused' => 'false',
            ]);
            $response = $this->ami->readResponse();
            $this->ami->disconnect();
            return str_contains($response, 'Success');
        } catch (Exception $e) {
            return false;
        }
    }

    public function queueRemove($queue, $extension): bool
    {
        try {
            $this->ami->connect();
            $this->ami->sendAction('QueueRemove', [
                'Queue' => $queue,
                'Interface' => 'PJSIP/' . $extension,
            ]);
            $response = $this->ami->readResponse();
            $this->ami->disconnect();
            return str_contains($response, 'Success');
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Snapshot isi queue PDS via AMI QueueStatus.
     * Return ['connected'=>bool, 'calls'=>int, 'holdtime'=>int,
     *          'members'=>[...], 'entries'=>[...]].
     * entries = penelepon yang sedang antre (Position, CallerIDNum, Wait detik).
     */
    public function queueStatus($queue): array
    {
        $result = ['connected' => false, 'calls' => 0, 'holdtime' => 0, 'members' => [], 'entries' => []];
        try {
            $this->ami->connect();
            $this->ami->sendAction('QueueStatus', ['Queue' => $queue]);
            $events = $this->ami->readEventsUntil('QueueStatusComplete', 10);
            $this->ami->disconnect();
        } catch (Exception $e) {
            return $result;
        }

        $result['connected'] = true;
        foreach ($events as $ev) {
            $event = $ev['Event'] ?? '';
            if ($event === 'QueueParams' && ($ev['Queue'] ?? '') === (string) $queue) {
                $result['calls'] = (int) ($ev['Calls'] ?? 0);
                $result['holdtime'] = (int) ($ev['Holdtime'] ?? 0);
            } elseif ($event === 'QueueMember') {
                $result['members'][] = [
                    'interface' => $ev['Interface'] ?? ($ev['Name'] ?? '-'),
                    'name' => $ev['Name'] ?? ($ev['MemberName'] ?? '-'),
                    'paused' => ($ev['Paused'] ?? '0') === '1',
                    'status' => (int) ($ev['Status'] ?? 0),
                    'calls_taken' => (int) ($ev['CallsTaken'] ?? 0),
                    'last_call' => (int) ($ev['LastCall'] ?? 0),
                ];
            } elseif ($event === 'QueueEntry') {
                $result['entries'][] = [
                    'position' => (int) ($ev['Position'] ?? 0),
                    'caller_id' => $ev['CallerIDNum'] ?? ($ev['CallerIDName'] ?? '-'),
                    'caller_name' => $ev['CallerIDName'] ?? '',
                    'wait' => (int) ($ev['Wait'] ?? 0),
                    'channel' => $ev['Channel'] ?? '',
                ];
            }
        }

        usort($result['entries'], fn($a, $b) => $a['position'] <=> $b['position']);

        return $result;
    }

    /**
     * Fitur Supervisor Action (Listen, Whisper, Join)
     * 
     * @param string $supervisorExt Extension milik supervisor (misal: "201")
     * @param string $targetChannel Channel milik agent yang sedang telepon (misal: "PJSIP/101")
     * @param string $mode Mode spy: '' (Listen), 'w' (Whisper), 'B' (Barge/Join)
     */
    public function supervisorAction($supervisorExt, $targetChannel, $mode = '')
    {
        try {
            $this->ami->connect();

            // Tambahkan flag 'q' (quiet) agar supervisor masuk diam-diam tanpa bunyi beep
            // Hasilnya jadi: 'q', 'qw', atau 'qB' tergantung modenya
            $spyOptions = 'q' . $mode; 

            // Kita eksekusi Application ChanSpy langsung dari AMI!
            $parameters = [
                'Channel'     => "PJSIP/" . $supervisorExt, // Telepon SPV
                'Application' => 'ChanSpy',                 // Panggil fitur nyadap Asterisk
                'Data'        => "{$targetChannel},{$spyOptions}", // Format: PJSIP/101,qw
                'CallerID'    => "Spying Agent <$targetChannel>",
                'Async'       => 'true'
            ];

            $this->ami->sendAction('Originate', $parameters);
            $response = $this->ami->readResponse();
            $this->ami->disconnect();

            return $response;

        } catch (Exception $e) {
            throw new Exception("Gagal mengeksekusi Supervisor Action: " . $e->getMessage());
        }
    }
    /**
     * Cek apakah ekstensi PJSIP benar-benar terdaftar (Online/Registered) di Asterisk
     */
    public function isExtensionRegistered($extension)
    {
        try {
            $this->ami->connect();

            $parameters = [
                'Endpoint' => $extension
            ];

            $this->ami->sendAction('PJSIPShowEndpoint', $parameters);
            $response = $this->ami->readResponse();
            
            $this->ami->disconnect();

            // Jika endpoint ditemukan dan memiliki status kontak aktif/Unavailable/Ringing (artinya ada config-nya)
            // Kita bisa cek apakah ada string "Status: Avail" atau informasi kontak aktif di respons AMI
            if (strpos($response, 'Status: Avail') !== false || strpos($response, 'Contact:') !== false) {
                // Pastikan ada kontak aktif (tidak kosong)
                if (strpos($response, 'Avail') !== false) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            return false; // Jika gagal konek AMI, anggap offline untuk keamanan
        }
    }

    /**
     * Cek status online/offline ekstensi PJSIP via AMI
     */
    
    /**
     * Cek status online/offline ekstensi PJSIP via AMI (Metode Paling Akurat)
     */
    /**
     * Cek status online/offline ekstensi PJSIP/SIP via AMI (Metode Paling Akurat)
     */
    public function getExtensionState($extension)
    {
        try {
            $this->ami->connect();

            // 1. Coba tanya status sebagai ekstensi PJSIP
            $parameters = [
                'Variable' => "DEVICE_STATE(PJSIP/{$extension})"
            ];
            $this->ami->sendAction('Getvar', $parameters);
            $response = $this->ami->readResponse();
            
            $state = 'UNKNOWN';

            // Tangkap balasan Value: (misal Value: NOT_INUSE)
            if (preg_match('/Value:\s*([^\r\n]+)/i', $response, $matches)) {
                $state = strtoupper(trim($matches[1]));
            }

            // 2. Jika hasilnya INVALID (artinya bukan PJSIP), kita paksa tanya sebagai SIP biasa
            if ($state === 'INVALID' || $state === 'UNKNOWN') {
                $parametersSip = [
                    'Variable' => "DEVICE_STATE(SIP/{$extension})"
                ];
                $this->ami->sendAction('Getvar', $parametersSip);
                $responseSip = $this->ami->readResponse();
                
                if (preg_match('/Value:\s*([^\r\n]+)/i', $responseSip, $matchesSip)) {
                    $state = strtoupper(trim($matchesSip[1]));
                }
            }

            $this->ami->disconnect();
            return $state;

        } catch (\Exception $e) {
            return 'ERROR'; 
        }
    }
}