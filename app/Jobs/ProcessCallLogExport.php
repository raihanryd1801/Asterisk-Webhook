<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Models\Agent;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Storage;

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

    public function handle()
    {
        // 🚀 1. REM OTOMATIS: Jika tanggal kosong, paksa ke 30 Hari Terakhir
        if (empty($this->filters['start_date']) && empty($this->filters['end_date'])) {
            $this->filters['start_date'] = date('Y-m-d', strtotime('-30 days'));
            $this->filters['end_date'] = date('Y-m-d');
        }

        // 🚀 2. UBAH SORTING: Gunakan 'id' alih-alih 'calldate' agar kueri langsung meluncur instan
        $query = DB::table('cdr_live')
                    ->select('calldate', 'src', 'dst', 'disposition', 'billsec', 'recordingfile')
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
            $keyword = $this->filters['search'];
            $query->where(function($q) use ($keyword) {
                $q->where('src', 'like', "%{$keyword}%")->orWhere('dst', 'like', "%{$keyword}%");
            });
        }

        // Pastikan filter tanggal selalu berjalan karena rem otomatis di atas
        if (!empty($this->filters['start_date'])) {
            $query->where('calldate', '>=', $this->filters['start_date'] . ' 00:00:00');
        }
        if (!empty($this->filters['end_date'])) {
            $query->where('calldate', '<=', $this->filters['end_date'] . ' 23:59:59');
        }

      $finalPath = \Illuminate\Support\Facades\Storage::disk('public')->path($this->filePath);
        $tmpPath = $finalPath . '.tmp'; // 🚀 Ini file sementaranya

        $directory = dirname($finalPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        // ❌ JANGAN GUNAKAN $finalPath / $fullPath DI SINI
        // ✅ GUNAKAN $tmpPath
        (new \Rap2hpoutre\FastExcel\FastExcel($query->cursor()))->export($tmpPath, function ($row) {
            $src = ($row->src === $row->dst && strlen($row->src) > 5) ? 'Ext / Agent' : $row->src;
            
            return [
                'Waktu'               => $row->calldate,
                'Asal (SRC)'          => $src,
                'Tujuan (DST)'        => $row->dst,
                'Status'              => $row->disposition,
                'Durasi Bicara (dtk)' => $row->billsec,
                'File Rekaman'        => $row->recordingfile ?? 'Tidak ada',
            ];
        });

        // 3. Ubah nama .tmp menjadi .xlsx hanya ketika proses di atas SUDAH 100% SELESAI
        rename($tmpPath, $finalPath);
    }
}