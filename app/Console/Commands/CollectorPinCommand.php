<?php

namespace App\Console\Commands;

use App\Models\DebtCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Set PIN login HP collector. Tanpa argumen PIN = acak 6 digit.
 * PIN tampil sekali di sini — yang tersimpan adalah hash-nya.
 */
class CollectorPinCommand extends Command
{
    protected $signature = 'collectors:pin {id : ID debt collector} {pin? : PIN baru (4-12 digit, default acak)}';

    protected $description = 'Set PIN login HP collector lapangan';

    public function handle(): int
    {
        $collector = DebtCollector::find($this->argument('id'));
        if (!$collector) {
            $this->error('Collector tidak ditemukan.');

            return self::FAILURE;
        }

        $pin = $this->argument('pin') ?? (string) random_int(100000, 999999);
        if (!preg_match('/^\d{4,12}$/', $pin)) {
            $this->error('PIN harus 4-12 digit angka.');

            return self::FAILURE;
        }

        $collector->update(['pin' => Hash::make($pin)]);
        $this->info("Collector: {$collector->name} (HP: {$collector->phone})");
        $this->line("PIN baru: {$pin} (sampaikan ke collector, tidak tersimpan plain)");

        return self::SUCCESS;
    }
}
