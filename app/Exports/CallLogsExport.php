<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use App\Models\Agent;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;

class CallLogsExport implements FromQuery, WithHeadings, WithMapping, WithCustomChunkSize
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    // 🚀 Tambahkan return type hint di sini
    public function query(): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('cdr_live')
                    ->select('calldate', 'src', 'dst', 'disposition', 'billsec', 'recordingfile')
                    ->orderBy('calldate', 'desc');

        if (!empty($this->filters['supervisor_extension'])) {
            $spv = Agent::where('extension', $this->filters['supervisor_extension'])->first();
            if ($spv) {
                $managed = Agent::where('supervisor_id', $spv->id)->orWhere('id', $spv->id)->pluck('extension')->toArray();
                $query->where(function($q) use ($managed) {
                    $q->whereIn('src', $managed)
                      ->orWhereIn('dst', $managed);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (!empty($this->filters['agent_extension'])) {
            $ext = $this->filters['agent_extension'];
            $query->where(function($q) use ($ext) {
                $q->where('src', $ext)
                  ->orWhere('dst', $ext);
            });
        }

        if (!empty($this->filters['search'])) {
            $keyword = $this->filters['search'];
            $query->where(function($q) use ($keyword) {
                $q->where('src', 'like', "%{$keyword}%")
                  ->orWhere('dst', 'like', "%{$keyword}%");
            });
        }

        if (!empty($this->filters['start_date'])) {
            $query->where('calldate', '>=', $this->filters['start_date'] . ' 00:00:00');
        }
        if (!empty($this->filters['end_date'])) {
            $query->where('calldate', '<=', $this->filters['end_date'] . ' 23:59:59');
        }

        return $query;
    }

    public function chunkSize(): int { return 5000; }

    public function headings(): array
    {
        return ['Waktu', 'Asal (SRC)', 'Tujuan (DST)', 'Status', 'Durasi Bicara (dtk)', 'File Rekaman'];
    }

    public function map($row): array
    {
        $src = ($row->src === $row->dst && strlen($row->src) > 5) ? 'Ext / Agent' : $row->src;
        return [$row->calldate, $src, $row->dst, $row->disposition, $row->billsec, $row->recordingfile ?? 'Tidak ada'];
    }
    
}