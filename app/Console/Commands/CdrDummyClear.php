<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Hapus baris dummy load-test (userfield = DUMMY_LOADTEST) dari cdr_live. */
class CdrDummyClear extends Command
{
    protected $signature = 'cdr:dummy-clear {--force : Tanpa konfirmasi}';

    protected $description = 'Hapus dummy call logs load-test';

    public function handle(): int
    {
        $n = DB::table('cdr_live')->where('userfield', 'DUMMY_LOADTEST')->count();
        if ($n === 0) {
            $this->info('Tidak ada baris dummy.');
            return self::SUCCESS;
        }
        if (!$this->option('force') && !$this->confirm("Hapus {$n} baris dummy?")) {
            return self::SUCCESS;
        }
        DB::table('cdr_live')->where('userfield', 'DUMMY_LOADTEST')->delete();
        $this->info("Terhapus {$n} baris. Jalankan OPTIMIZE TABLE cdr_live bila ingin kembalikan ukuran file.");
        return self::SUCCESS;
    }
}
