<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncCdrData extends Command
{
    protected $signature = 'cdr:sync';
    protected $description = 'Tarik data CDR terbaru dari Asterisk dan bersihkan data lama (lebih dari 30 hari)';

    public function handle()
    {
        $this->info("Memulai sinkronisasi CDR...");

        $lastRecord = DB::connection('mysql')->table('cdr_live')->orderBy('calldate', 'desc')->first();
        $lastSyncDate = $lastRecord ? $lastRecord->calldate : Carbon::now()->subDays(30)->toDateTimeString();

        // 🚀 TAMBAHKAN INI BUAT CEK DI TERMINAL
        $this->info("Last Sync Date lokal: " . $lastSyncDate);

        $newCdrs = DB::connection('asterisk_cdr')
                     ->table('cdr')
                     ->select('calldate', 'src', 'dst', 'dcontext', 'channel', 'dstchannel', 'lastapp', 'lastdata', 'duration', 'billsec', 'disposition', 'amaflags', 'accountcode', 'uniqueid', 'userfield', 'recordingfile', 'cnum', 'cnam', 'outbound_cnum', 'outbound_cnam', 'dst_cnam')
                     ->where('calldate', '>', $lastSyncDate)
                     ->orderBy('calldate', 'asc')
                     ->get();

        // 🚀 TAMBAHKAN INI JUGA
        $this->info("Jumlah data baru ditemukan di Asterisk: " . $newCdrs->count());

        if ($newCdrs->count() > 0) {
            $insertData = json_decode(json_encode($newCdrs), true);
            foreach (array_chunk($insertData, 500) as $chunk) {
                DB::connection('mysql')->table('cdr_live')->insertOrIgnore($chunk);
            }
            $this->info($newCdrs->count() . " data panggilan baru berhasil disinkronkan.");
        } else {
            $this->info("Tidak ada panggilan baru.");
        }
    
        /*
        // 3. Hapus data yang umurnya sudah lebih dari 30 hari (Arsip Cleanup)
        $deleted = DB::connection('mysql')->table('cdr_live')
                     ->where('calldate', '<', Carbon::now()->subDays(30))
                     ->delete();
                     
        if ($deleted > 0) {
            $this->info("$deleted data lama berhasil dibersihkan dari cdr_live.");
        }
        */
        }   
}