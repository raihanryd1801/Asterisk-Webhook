<?php

namespace App\Console\Commands;

use App\Services\CdrSummarizer;
use Illuminate\Console\Command;

/** Bangun ringkasan CDR harian (dipanggil scheduler tiap 5 menit). */
class CdrSummarize extends Command
{
    protected $signature = 'cdr:summarize {--days=2 : Jumlah hari terakhir yang dibangun ulang} {--all : Backfill semua tanggal}';

    protected $description = 'Bangun ringkasan CDR harian untuk dashboard';

    public function handle(CdrSummarizer $summarizer): int
    {
        if ($this->option('all')) {
            $n = $summarizer->summarizeAll();
        } else {
            $n = $summarizer->summarizeRecent(max(1, (int) $this->option('days')));
        }
        $this->info("Ringkasan ditulis: {$n} baris.");
        return self::SUCCESS;
    }
}
