<?php

namespace App\Console\Commands;

use App\Models\DebtCollector;
use Illuminate\Console\Command;

/**
 * Buat/putar token HP collector. Token plain HANYA tampil sekali di sini —
 * yang tersimpan di DB adalah hash-nya.
 * Pakai: Authorization: Bearer <token> saat POST posisi.
 */
class CollectorTokenCommand extends Command
{
    protected $signature = 'collectors:token {id : ID debt collector}';

    protected $description = 'Buat token API untuk HP collector lapangan';

    public function handle(): int
    {
        $collector = DebtCollector::find($this->argument('id'));
        if (!$collector) {
            $this->error('Collector tidak ditemukan.');

            return self::FAILURE;
        }
        if (!$collector->is_active) {
            $this->warn('Collector ini nonaktif — token tetap dibuat tapi HP akan ditolak (401).');
        }

        $plain = $collector->rotateApiToken();
        $this->info("Collector: {$collector->name} (ID {$collector->id})");
        $this->line('Token (catat sekarang, tidak bisa dilihat lagi):');
        $this->line($plain);

        return self::SUCCESS;
    }
}
