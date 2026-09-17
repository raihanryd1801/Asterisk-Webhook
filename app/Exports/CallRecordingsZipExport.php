<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use App\Models\Agent;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use GuzzleHttp\Client;          
use GuzzleHttp\Promise\Utils;

class CallRecordingsZipExport
{
    protected $filters;
    protected $filename;

    // Batas wajar: tiap file = 1 request HTTP ke FreePBX. Tanpa cap, jutaan
    // baris = jutaan request = worker gantung berjam-jam.
    public const MAX_FILES = 2000;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
        $this->filename = 'recordings-' . date('Y-m-d_H-i-s') . '.zip';
    }

    public function getFilename()
    {
        return $this->filename;
    }

    protected function statusKey(): string
    {
        return 'zip_status_' . $this->filename;
    }

    public function generate()
    {
        // 1. Ambil query filter seperti biasa.
        // NOTE: tanpa ORDER BY calldate — urutan file di dalam ZIP tidak penting,
        // dan sort di jutaan baris bikin chunk menggantung. orderBy id (primary
        // key) disyaratkan chunk() dan selalu cepat.
        $query = DB::table('cdr_live')
            ->select('calldate', 'src', 'dst', 'recordingfile')
            ->whereNotNull('recordingfile')
            ->where('recordingfile', '!=', '')
            ->orderBy('id');

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

        if (!empty($this->filters['agent_extension'])) {
            $ext = $this->filters['agent_extension'];
            $query->where(function($q) use ($ext) {
                $q->where('src', $ext)->orWhere('dst', $ext);
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

        // Rem otomatis 30 hari bila tanpa filter tanggal (samakan perilaku export Excel)
        if (empty($this->filters['start_date']) && empty($this->filters['end_date'])) {
            $query->where('calldate', '>=', date('Y-m-d 00:00:00', strtotime('-30 days')));
        }

        $total = (clone $query)->count();
        $truncated = $total > self::MAX_FILES;
        if ($truncated) {
            $query->limit(self::MAX_FILES);
        }
        $target = min($total, self::MAX_FILES);

        \Illuminate\Support\Facades\Cache::put(
            $this->statusKey(),
            ['ready' => false, 'done' => 0, 'total' => $target, 'truncated' => $truncated],
            now()->addMinutes(30)
        );

        // 2. Siapkan file ZIP lokal di server web sementara
        $exportDir = storage_path('app/public/exports');
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $exportPath = $exportDir . '/' . $this->filename;

        $zip = new ZipArchive();
        if ($zip->open($exportPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }

       // 🚀 Siapkan Client HTTP Guzzle (Turbo Downloader)
        $client = new Client([
            'timeout' => 10, // Max 10 detik nunggu per file
            'connect_timeout' => 3, // Max 3 detik buka koneksi (server mati langsung skip)
            'verify' => false,
            'http_errors' => false
        ]);

        $statusKey = $this->statusKey();
        $doneFiles = 0;

        // Gunakan Chunk 100 (Artinya 100 file didownload serentak dalam 1 kloter)
        $query->chunk(100, function ($rows) use ($zip, $client, $statusKey, &$doneFiles, $target) {
            $promises = [];
            $fileMapping = [];

            foreach ($rows as $index => $row) {
                if (empty($row->recordingfile)) continue;

                $filename = basename($row->recordingfile);
                preg_match('/-(\d{4})(\d{2})(\d{2})-/', $filename, $matches);
                
                if (count($matches) == 4) {
                    $year = $matches[1];
                    $month = $matches[2];
                    $day = $matches[3];
                    $publicAudioUrl = "http://172.16.1.24/monitor/{$year}/{$month}/{$day}/{$filename}";
                } else {
                    $publicAudioUrl = "http://172.16.1.24/monitor/{$filename}";
                }

                $safeDate = str_replace([':', ' '], '_', $row->calldate);
                $zipName = $safeDate . '_' . $filename;

                // 🚀 JURUS 1: Masukkan ke antrean janji (Promise) untuk ditarik ASYNC (Serentak)
                $promises[$index] = $client->getAsync($publicAudioUrl);
                $fileMapping[$index] = $zipName;
            }

            // 🚀 TEMBAK SEMUA REQUEST BERSAMAAN! (Network Multi-Threading)
            $responses = Utils::settle($promises)->wait();

            // Masukkan hasil yang sukses ke dalam ZIP
            foreach ($responses as $index => $response) {
                // Pastikan status HTTP 200 (File ada)
                if ($response['state'] === 'fulfilled' && $response['value']->getStatusCode() === 200) {
                    
                    $fileContent = $response['value']->getBody()->getContents();
                    $zipName = $fileMapping[$index];
                    
                    $zip->addFromString($zipName, $fileContent);

                    // 🚀 JURUS 2: Matikan kompresi CPU! (Hanya bungkus, jangan dipadatkan)
                    // Menghemat waktu pembuatan ZIP hingga 90%
                    $zip->setCompressionName($zipName, ZipArchive::CM_STORE);
                }
            }

            $doneFiles += count($rows);
            \Illuminate\Support\Facades\Cache::put(
                $statusKey,
                ['ready' => false, 'done' => min($doneFiles, $target), 'total' => $target],
                now()->addMinutes(30)
            );
        });

        $zip->close();
        return true;
}
}