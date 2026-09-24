<?php

namespace App\Console\Commands;

use App\Models\CollectorPosition;
use Illuminate\Console\Command;

/** Hapus jejak GPS lama agar tabel tidak membengkak (default > 14 hari). */
class PruneCollectorPositions extends Command
{
    protected $signature = 'collectors:prune-positions {--days=14 : Umur maksimal jejak (hari)}';

    protected $description = 'Hapus posisi collector lebih tua dari N hari';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $n = CollectorPosition::where('recorded_at', '<', now()->subDays($days))->delete();
        $this->info("Dihapus {$n} titik lebih tua dari {$days} hari.");

        return self::SUCCESS;
    }
}
