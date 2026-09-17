<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Isi tabel cdr_live dengan data dummy untuk load-test web server.
 * Berhenti saat ukuran tabel (data+index) mencapai target.
 * Baris dummy ditandai userfield = DUMMY_LOADTEST (lihat cdr:dummy-clear).
 */
class CdrDummyFill extends Command
{
    protected $signature = 'cdr:dummy-fill
        {--target-mb=1024 : Berhenti saat ukuran cdr_live >= MB ini}
        {--batch=2000 : Baris per sekali insert}
        {--days=30 : Sebar calldate mundur N hari}';

    protected $description = 'Generate dummy call logs hingga ukuran target (load test)';

    public function handle(): int
    {
        $targetMb = max(1, (int) $this->option('target-mb'));
        $batch = max(100, min(10000, (int) $this->option('batch')));
        $days = max(1, (int) $this->option('days'));

        $exts = ['101', '102', '103', '104', '105', '106', '107', '108', '109', '110'];
        $names = ['Budi Santoso', 'Rochman', 'Nauval Ramadhan', 'Alif1', 'Adam', 'Yoseph', 'Patimura', 'Ajis Gagap', "Eto'o", 'Santoso'];
        $disps = ['ANSWERED', 'ANSWERED', 'ANSWERED', 'ANSWERED', 'NO ANSWER', 'NO ANSWER', 'NO ANSWER', 'NO ANSWER', 'NO ANSWER', 'BUSY', 'FAILED'];
        $now = time();
        $total = 0;
        $bar = $this->output->createProgressBar();
        $bar->start();

        // Matikan query log agar hemat memori
        DB::disableQueryLog();

        while (true) {
            $rows = [];
            for ($i = 0; $i < $batch; $i++) {
                $ext = $exts[array_rand($exts)];
                $isHp = mt_rand(1, 100) <= 70;
                $dst = $isHp
                    ? '08' . (string) mt_rand(1111111111, 9999999999)
                    : '021' . (string) mt_rand(1000000, 9999999);
                $disp = $disps[array_rand($disps)];
                $bill = $disp === 'ANSWERED' ? mt_rand(3, 1800) : mt_rand(0, 30);
                $dur = $bill + mt_rand(2, 25);
                $ts = $now - mt_rand(0, $days * 86400);
                $calldate = date('Y-m-d H:i:s', $ts);
                $uniq = 'DUMMY' . uniqid() . $i;
                $aname = $names[array_rand($names)];
                $rec = $disp === 'ANSWERED' && mt_rand(1, 100) <= 80
                    ? sprintf('out-%s-%s-%s-%d-%s.%.3f.wav', $dst, $ext, date('Ymd-His', $ts), $ts, mt_rand(100, 999), mt_rand(100, 999) / 1000)
                    : null;
                $rows[] = [
                    'calldate' => $calldate,
                    'src' => $ext,
                    'dst' => $dst,
                    'dcontext' => 'from-internal',
                    'channel' => "PJSIP/{$ext}-" . substr(md5($uniq), 0, 8),
                    'dstchannel' => $disp === 'ANSWERED' ? 'PJSIP/trunk-' . substr(md5($uniq . 'x'), 0, 8) : null,
                    'lastapp' => 'Dial',
                    'lastdata' => "PJSIP/{$dst}@trunk",
                    'duration' => $dur,
                    'billsec' => $bill,
                    'disposition' => $disp,
                    'amaflags' => '3',
                    'accountcode' => '',
                    'uniqueid' => $uniq,
                    'userfield' => 'DUMMY_LOADTEST',
                    'recordingfile' => $rec,
                    'cnum' => $ext,
                    'cnam' => $aname,
                    'outbound_cnum' => null,
                    'outbound_cnam' => null,
                    'dst_cnam' => null,
                    'terminated_by' => $disp === 'ANSWERED' ? (mt_rand(0, 1) ? 'Agent' : $dst) : null,
                    'notes' => null,
                    'sip_code' => $disp === 'ANSWERED' ? '200 OK' : (['487 Request Terminated', '486 Busy Here', '404 Not Found'][array_rand([0, 1, 2])]),
                ];
            }
            DB::table('cdr_live')->insert($rows);
            $total += $batch;
            $bar->advance($batch);

            if (($total / $batch) % 10 === 0) {
                $mb = $this->tableMb();
                $bar->setMessage(sprintf(' %d rows, %.1f MB', $total, $mb));
                if ($mb >= $targetMb) {
                    break;
                }
            }
        }

        $bar->finish();
        $this->newLine(2);
        $mb = $this->tableMb();
        $rows = DB::table('cdr_live')->count();
        $this->info("Selesai: {$rows} rows, {$mb} MB (target {$targetMb} MB). Hapus dummy via: php artisan cdr:dummy-clear");

        return self::SUCCESS;
    }

    protected function tableMb(): float
    {
        $db = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT (DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024 AS mb FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$db, 'cdr_live']
        );
        return round((float) ($row->mb ?? 0), 2);
    }
}
