<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SyncCdrData extends Command
{
    protected $signature = 'cdr:sync';
    protected $description = 'Tarik data CDR terbaru dari Asterisk dan bersihkan data lama (lebih dari 30 hari)';

    // Key cache buat nyimpen watermark sync, TERPISAH dari cdr_live.
    // PENTING: jangan ambil watermark dari MAX(calldate) di cdr_live lagi,
    // karena sejak ami:listen juga nulis row placeholder ke cdr_live pakai
    // calldate = waktu HANGUP (bukan waktu call mulai), watermark jadi kegeser
    // maju melebihi calldate asli dari asteriskcdrdb.cdr -> row CDR resmi
    // jadi ke-skip permanen oleh query WHERE calldate > $lastSyncDate.
    const CACHE_KEY = 'cdr_sync_last_calldate';

    public function handle()
    {
        $this->info("Memulai sinkronisasi CDR...");

        $lastSyncDate = Cache::get(self::CACHE_KEY, Carbon::now()->subDays(30)->toDateTimeString());

        $this->info("Last Sync Date: " . $lastSyncDate);

        $newCdrs = DB::connection('asterisk_cdr')
                     ->table('cdr')
                     ->select('calldate', 'src', 'dst', 'dcontext', 'channel', 'dstchannel', 'lastapp', 'lastdata', 'duration', 'billsec', 'disposition', 'amaflags', 'accountcode', 'uniqueid', 'userfield', 'recordingfile', 'cnum', 'cnam', 'outbound_cnum', 'outbound_cnam', 'dst_cnam')
                     ->where('calldate', '>', $lastSyncDate)
                     ->orderBy('calldate', 'asc')
                     ->get();

        $this->info("Jumlah data baru ditemukan di Asterisk: " . $newCdrs->count());

        if ($newCdrs->count() > 0) {
            $insertData = json_decode(json_encode($newCdrs), true);

            // PENTING: pakai upsert, BUKAN insertOrIgnore.
            // insertOrIgnore akan MENGABAIKAN row yang uniqueid-nya sudah ada
            // (misal sudah dibikin duluan oleh ami:listen begitu call selesai),
            // padahal row itu baru punya data minimal (uniqueid, src, dst, sip_code)
            // dan belum punya data lengkap dari Asterisk (duration, billsec, dst).
            //
            // Dengan upsert, kolom-kolom resmi dari Asterisk ini akan MELENGKAPI
            // row yang sudah ada, TANPA menyentuh kolom 'sip_code' dan
            // 'terminated_by' sama sekali (makanya keduanya sengaja tidak
            // dimasukkan ke parameter $update di bawah) - jadi nilai yang sudah
            // ditulis ami:listen tetap aman, nggak ketimpa jadi NULL lagi.
            foreach (array_chunk($insertData, 500) as $chunk) {
                DB::connection('mysql')->table('cdr_live')->upsert(
                    $chunk,
                    ['uniqueid'], // kolom unique buat deteksi row yang sudah ada
                    [
                        'calldate', 'src', 'dst', 'dcontext', 'channel', 'dstchannel',
                        'lastapp', 'lastdata', 'duration', 'billsec', 'disposition',
                        'amaflags', 'accountcode', 'userfield', 'recordingfile',
                        'cnum', 'cnam', 'outbound_cnum', 'outbound_cnam', 'dst_cnam',
                        // 'sip_code' dan 'terminated_by' SENGAJA TIDAK ADA DI SINI
                    ]
                );
            }

            // Update watermark HANYA berdasarkan calldate terbesar dari data yang
            // baru saja kita tarik dari Asterisk -- sama sekali tidak menengok cdr_live.
            $maxCalldate = $newCdrs->max('calldate');
            Cache::forever(self::CACHE_KEY, $maxCalldate);

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