<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Asterisk\AmiClient;

class TestAmiConnection extends Command
{
    protected $signature = 'ami:test';
    protected $description = 'Test koneksi ke server Asterisk/FreePBX via AMI';

    public function handle(AmiClient $ami)
    {
        $this->info("Mencoba koneksi ke AMI di " . env('ASTERISK_AMI_HOST') . "...");

        try {
            $ami->connect();
            $this->info("✅ Berhasil Login ke AMI!");

            // Test kirim action Ping
            $this->line("Mengirim perintah PING...");
            $ami->sendAction('Ping');
            $response = $ami->readResponse();
            
            $this->line("Balasan dari Asterisk:");
            $this->comment(trim($response));

            $ami->disconnect();
            $this->info("Koneksi ditutup.");

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }
}