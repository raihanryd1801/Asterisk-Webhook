<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Models\Agent;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row as SpoutRow;
use OpenSpout\Writer\XLSX\Writer as SpoutXlsxWriter;

class ProcessCallLogExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filters;
    protected $filePath;

    public function __construct(array $filters, string $filePath)
    {
        $this->filters = $filters;
        $this->filePath = $filePath;
    }

    protected function progressKey(): string
    {
        return 'export_progress_' . basename($this->filePath);
    }

    protected function isCsv(): bool
    {
        return strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION)) === 'csv';
    }

    /** Query dasar + semua filter (dipakai jalur xlsx maupun csv). */
    protected function baseQuery()
    {
        $query = DB::table('cdr_live')
                    ->select('calldate', 'src', 'dst', 'disposition', 'billsec', 'recordingfile', 'cnam')
                    ->orderBy('id', 'desc');

        // --- FILTER HAK AKSES ---
        if (!empty($this->filters['supervisor_extension'])) {
            $spv = Agent::where('extension', $this->filters['supervisor_extension'])->first();
            if ($spv) {
                $managed = Agent::where('supervisor_id', $spv->id)->orWhere('id', $spv->id)->pluck('extension')->toArray();
                $query->where(function($q) use ($managed) {
                    $q->whereIn('src', $managed)->orWhereIn('dst', $managed);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // --- FILTER LAINNYA ---
        if (!empty($this->filters['agent_extension'])) {
            $ext = $this->filters['agent_extension'];
            $query->where(function($q) use ($ext) {
                $q->where('src', $ext)->orWhere('dst', $ext);
            });
        }

        if (!empty($this->filters['search'])) {
            $keyword = trim((string) $this->filters['search']);
            $digits = preg_replace('/\D/', '', $keyword);
            if ($digits !== '' && strlen($digits) >= 6 && preg_match('/^[\d\s\+\-\(\)]+$/', $keyword)) {
                // Nomor: exact match via index (jauh lebih cepat dari LIKE).
                $variants = array_values(array_unique(array_filter([$keyword, $digits])));
                if (str_starts_with($digits, '62')) {
                    $variants[] = '0' . substr($digits, 2);
                } elseif (str_starts_with($digits, '0')) {
                    $variants[] = '62' . substr($digits, 1);
                }
                $query->where(function($q) use ($variants) {
                    $q->whereIn('src', $variants)->orWhereIn('dst', $variants);
                });
            } else {
                $query->where(function($q) use ($keyword) {
                    $q->where('src', 'like', "%{$keyword}%")->orWhere('dst', 'like', "%{$keyword}%");
                });
            }
        }

        // Pastikan filter tanggal selalu berjalan karena rem otomatis di atas
        if (!empty($this->filters['start_date'])) {
            $query->where('calldate', '>=', $this->filters['start_date'] . ' 00:00:00');
        }
        if (!empty($this->filters['end_date'])) {
            $query->where('calldate', '<=', $this->filters['end_date'] . ' 23:59:59');
        }

        return $query;
    }

    /**
     * Jalur super-cepat CSV: MySQL menulis file langsung (SELECT INTO OUTFILE),
     * tanpa lewat PHP baris-per-baris. Jutaan baris = hitungan detik.
     */
    protected function exportCsvOutfile($query, int $limit, string $finalPath): void
    {
        $outFile = sys_get_temp_dir() . '/call-history-' . uniqid() . '.csv';

        $data = clone $query;
        $data->columns = []; // buang select bawaan baseQuery (Query\Builder)
        $data->orders = []; // orderBy id ambigu setelah join agents
        $data->orderBy('c.id', 'desc');
        $data->selectRaw(
            "DATE_FORMAT(calldate, '%Y-%m-%d %H:%i:%s') as waktu,
            COALESCE(a_src.name, a_dst.name, NULLIF(cnam, ''), '-') as nama_agent,
            CASE WHEN src = dst AND CHAR_LENGTH(src) > 5 THEN 'Ext / Agent' ELSE src END as asal,
            dst as tujuan, disposition as status, billsec as durasi,
            COALESCE(recordingfile, 'Tidak ada') as rekaman"
        )
        ->from(DB::raw('cdr_live as c'))
        ->leftJoin('agents as a_src', 'a_src.extension', '=', 'c.src')
        ->leftJoin('agents as a_dst', 'a_dst.extension', '=', 'c.dst')
        ->limit($limit);

        // Terapkan kembali where dari builder asal (filter sudah di-clone di atas
        // sebelum selectRaw diganti? tidak — bangun ulang: ambil SQL + binding).
        $sql = $data->toSql();
        $bindings = $data->getBindings();

        $header = "SELECT 'Waktu','Nama Agent','Asal (SRC)','Tujuan (DST)','Status','Durasi Bicara (dtk)','File Rekaman'";
        $fullSql = $header . ' UNION ALL (' . $sql . ') INTO OUTFILE ' . DB::getPdo()->quote($outFile) .
            " CHARACTER SET utf8mb4 FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\\n'";

        DB::select($fullSql, $bindings);

        if (!@rename($outFile, $finalPath)) {
            copy($outFile, $finalPath);
            @unlink($outFile);
        }
    }

    public function handle()
    {
        // 🚀 1. REM OTOMATIS: Jika tanggal kosong, paksa ke 30 Hari Terakhir
        if (empty($this->filters['start_date']) && empty($this->filters['end_date'])) {
            $this->filters['start_date'] = date('Y-m-d', strtotime('-30 days'));
            $this->filters['end_date'] = date('Y-m-d');
        }

        // 🚀 2. Query dasar + filter (sorting id desc = instan)
        $query = $this->baseQuery();

        // Peta extension => nama agent (1x query, dipakai untuk kolom Nama Agent)
        $agentNames = Agent::pluck('name', 'extension')->toArray();

        $total = (clone $query)->count();

        \Illuminate\Support\Facades\Cache::put(
            $this->progressKey(),
            ['done' => 0, 'total' => $total, 'truncated' => false],
            now()->addMinutes(180)
        );

      $finalPath = \Illuminate\Support\Facades\Storage::disk('public')->path($this->filePath);
        $tmpPath = $finalPath . '.tmp'; // 🚀 Ini file sementaranya

        $directory = dirname($finalPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        // Jalur CSV: MySQL menulis file langsung (detik, bukan menit).
        if ($this->isCsv()) {
            $t = microtime(true);
            $this->exportCsvOutfile($query, $total, $tmpPath);
            rename($tmpPath, $finalPath);
            \Illuminate\Support\Facades\Cache::put(
                $this->progressKey(),
                ['done' => $total, 'total' => $total, 'truncated' => false, 'seconds' => round(microtime(true) - $t, 1)],
                now()->addMinutes(30)
            );
            return;
        }

        // ❌ JANGAN GUNAKAN $finalPath / $fullPath DI SINI
        // ✅ GUNAKAN $tmpPath
        // XLSX via OpenSpout streaming writer: hemat memori (tulis per baris ke
        // disk) + jauh lebih cepat dari ORM-per-baris. Otomatis split multi-sheet
        // tiap 1 jt baris karena Excel mentok 1.048.576 baris/sheet.
        $progressKey = $this->progressKey();
        $done = 0;
        $sheetRows = 0;
        $sheetNo = 1;
        $maxPerSheet = 1000000;
        $header = ['Waktu', 'Nama Agent', 'Asal (SRC)', 'Tujuan (DST)', 'Status', 'Durasi Bicara (dtk)', 'File Rekaman'];

        $writer = new SpoutXlsxWriter();
        $writer->openToFile($tmpPath);
        $writer->getCurrentSheet()->setName('Data 1');
        $writer->addRow(SpoutRow::fromValues($header));

        foreach ($query->cursor() as $row) {
            if ($sheetRows >= $maxPerSheet) {
                $sheetNo++;
                $writer->addNewSheetAndMakeItCurrent();
                $writer->getCurrentSheet()->setName('Data ' . $sheetNo);
                $writer->addRow(SpoutRow::fromValues($header));
                $sheetRows = 0;
            }

            $src = ($row->src === $row->dst && strlen($row->src) > 5) ? 'Ext / Agent' : $row->src;

            // Sisi agent: src bila outbound dari ext, dst bila inbound ke ext.
            if (isset($agentNames[$row->src])) {
                $agentName = $agentNames[$row->src];
            } elseif (isset($agentNames[$row->dst])) {
                $agentName = $agentNames[$row->dst];
            } else {
                $cnam = trim((string) ($row->cnam ?? ''));
                $agentName = $cnam !== '' ? $cnam : '-';
            }

            $writer->addRow(SpoutRow::fromValues([
                $row->calldate,
                $agentName,
                $src,
                $row->dst,
                $row->disposition,
                (int) $row->billsec,
                $row->recordingfile ?? 'Tidak ada',
            ]));

            $sheetRows++;
            $done++;
            if ($done % 10000 === 0) {
                $prev = \Illuminate\Support\Facades\Cache::get($progressKey, []);
                $prev['done'] = $done;
                \Illuminate\Support\Facades\Cache::put($progressKey, $prev, now()->addMinutes(180));
            }
        }
        $writer->close();

        // 3. Ubah nama .tmp menjadi .xlsx hanya ketika proses di atas SUDAH 100% SELESAI
        rename($tmpPath, $finalPath);
        \Illuminate\Support\Facades\Cache::forget($this->progressKey());
    }
}