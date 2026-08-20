<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Asterisk\OriginateService;

class TestSpyCommand extends Command
{
    // Signature: {supervisor_ext} {target_channel} {mode opsional}
    // Mode: kosong (Listen), w (Whisper), B (Barge/Join)
    protected $signature = 'spy:test {supervisor} {target} {mode?}';
    protected $description = 'Test fitur Supervisor (Listen, Whisper, Join)';

    public function handle(OriginateService $originateService)
    {
        $supervisor = $this->argument('supervisor');
        $targetChan = $this->argument('target');
        $mode       = $this->argument('mode') ?? ''; // Default Listen

        // Translasi kode mode agar enak dibaca di terminal
        $modeLabel = 'Listen (Hanya Dengar)';
        if ($mode === 'w') $modeLabel = 'Whisper (Bisik ke Agen)';
        if ($mode === 'B') $modeLabel = 'Barge (Join Percakapan)';

        $this->info("Menghubungi Supervisor ({$supervisor}) untuk melakukan [{$modeLabel}] ke channel {$targetChan}...");

        try {
            $response = $originateService->supervisorAction($supervisor, $targetChan, $mode);
            $this->info("✅ Perintah berhasil dikirim ke Asterisk!");
            $this->comment("Balasan Asterisk: \n" . trim($response));
        } catch (\Exception $e) {
            $this->error("❌ Gagal: " . $e->getMessage());
        }
    }
}