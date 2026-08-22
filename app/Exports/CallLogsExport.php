<?php

namespace App\Exports;

use App\Models\Cdr;
use App\Models\Agent;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class CallLogsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Menentukan Query Data dengan Return Type Hint yang Cocok untuk PHP 8 / Laravel 11+
     */
    public function query(): Builder
    {
        $query = Cdr::query()->orderBy('calldate', 'desc');

        // 1. Filter Hak Akses (Supervisor vs Admin)
        if (session()->has('supervisor_extension')) {
            $spvExt = session('supervisor_extension');
            $spv = Agent::where('extension', $spvExt)->first();

            if ($spv) {
                $managedExtensions = Agent::where('supervisor_id', $spv->id)
                                          ->orWhere('id', $spv->id)
                                          ->pluck('extension')
                                          ->toArray();

                $query->where(function($q) use ($managedExtensions) {
                    $q->whereIn('src', $managedExtensions)
                      ->orWhereIn('dst', $managedExtensions);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // 2. Filter berdasarkan Agent tertentu (Dropdown)
        if ($this->request->filled('agent_extension')) {
            $ext = $this->request->agent_extension;
            $query->where(function($q) use ($ext) {
                $q->where('src', $ext)->orWhere('dst', $ext);
            });
        }

        // 3. Filter berdasarkan Pencarian Nomor / Ekstensi
        if ($this->request->filled('search')) {
            $keyword = $this->request->search;
            $query->where(function($q) use ($keyword) {
                $q->where('src', 'like', "%{$keyword}%")
                  ->orWhere('dst', 'like', "%{$keyword}%");
            });
        }

        // 4. Filter berdasarkan Rentang Tanggal (Start Date & End Date)
        if ($this->request->filled('start_date')) {
            $query->whereDate('calldate', '>=', $this->request->start_date);
        }
        if ($this->request->filled('end_date')) {
            $query->whereDate('calldate', '<=', $this->request->end_date);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Waktu',
            'Asal (SRC)',
            'Tujuan (DST)',
            'Status',
            'Durasi Bicara (dtk)',
            'File Rekaman'
        ];
    }

    public function map($row): array
    {
        // Koreksi self-call
        $src = ($row->src === $row->dst && strlen($row->src) > 5) ? 'Ext / Agent' : $row->src;

        return [
            $row->calldate,
            $src,
            $row->dst,
            $row->disposition,
            $row->billsec, // Durasi bicara murni yang sinkron dengan audio
            $row->recordingfile ?? 'Tidak ada'
        ];
    }
}