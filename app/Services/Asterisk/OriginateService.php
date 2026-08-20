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
}