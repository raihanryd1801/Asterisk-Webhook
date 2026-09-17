<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Membangun ringkasan CDR harian untuk dashboard Overview.
 * Aman diulang (rebuild per tanggal = delete + insert).
 */
class CdrSummarizer
{
    /**
     * Bangun ulang ringkasan untuk 1 tanggal (format Y-m-d).
     * Return jumlah baris ringkasan yang ditulis.
     */
    public function summarizeDate(string $date): int
    {
        DB::table('cdr_daily_summary')->where('date', $date)->delete();

        $inserted = DB::table('cdr_daily_summary')->insertUsing(
            ['date', 'src', 'disposition', 'calls', 'billsec', 'created_at', 'updated_at'],
            DB::table('cdr_live')
                ->selectRaw('DATE(calldate) as date, src, disposition, COUNT(*) as calls, COALESCE(SUM(billsec), 0) as billsec, NOW() as created_at, NOW() as updated_at')
                ->whereDate('calldate', $date)
                ->groupByRaw('DATE(calldate), src, disposition')
        );

        return $inserted;
    }

    /** Bangun ulang N hari terakhir (termasuk hari ini). */
    public function summarizeRecent(int $days = 2): int
    {
        $total = 0;
        for ($i = $days - 1; $i >= 0; $i--) {
            $total += $this->summarizeDate(now()->subDays($i)->toDateString());
        }
        return $total;
    }

    /** Backfill semua tanggal yang ada di cdr_live. */
    public function summarizeAll(): int
    {
        $dates = DB::table('cdr_live')
            ->selectRaw('DISTINCT DATE(calldate) as d')
            ->orderBy('d')
            ->pluck('d');
        $total = 0;
        foreach ($dates as $d) {
            $date = $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : (string) $d;
            $total += $this->summarizeDate($date);
        }
        return $total;
    }
}
