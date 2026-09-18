<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;

/**
 * Satu potong pengerjaan ZIP rekaman. Setelah selesai, bila masih ada sisa,
 * job ini me-dispatch dirinya sendiri untuk potongan berikutnya (berantai).
 * Dengan begitu export sebesar apa pun tidak akan mati kena timeout worker:
 * tiap potong hanya ~3000 file (±5-8 menit), progres tersimpan di cache.
 */
class ProcessZipChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1500;
    public int $tries = 2;

    public const SLICE_SIZE = 3000;
    public const CONCURRENCY = 150;

    public function __construct(
        public string $filename,
        public array $filters,
        public int $afterId = 0,
    ) {}

    public function stateKey(): string
    {
        return 'zip_status_' . $this->filename;
    }

    /** Query hitung total (dipakai controller untuk inisialisasi progres). */
    public static function countQuery(array $filters)
    {
        $tmp = new self('count-tmp.zip', $filters, 0);
        $query = DB::table('cdr_live')
            ->select('id')
            ->whereNotNull('recordingfile')
            ->where('recordingfile', '!=', '');
        $tmp->applyFilters($query);
        return $query;
    }

    public function stagingDir(): string
    {
        return storage_path('app/public/exports/parts/' . pathinfo($this->filename, PATHINFO_FILENAME));
    }

    public function handle(): void
    {
        $state = Cache::get($this->stateKey(), []);
        $total = (int) ($state['total'] ?? 0);

        $query = DB::table('cdr_live')
            ->select('id', 'calldate', 'src', 'dst', 'recordingfile')
            ->whereNotNull('recordingfile')
            ->where('recordingfile', '!=', '')
            ->where('id', '>', $this->afterId)
            ->orderBy('id');
        $this->applyFilters($query);

        $rows = $query->limit(self::SLICE_SIZE)->get();
        if ($rows->isEmpty()) {
            $this->assemble();
            return;
        }

        if (!is_dir($this->stagingDir())) {
            mkdir($this->stagingDir(), 0755, true);
        }

        $client = new Client([
            'timeout' => 10,
            'connect_timeout' => 3,
            'verify' => false,
            'http_errors' => false,
        ]);

        $client = new Client([
            'timeout' => 10,
            'connect_timeout' => 3,
            'verify' => false,
            'http_errors' => false,
        ]);

        // PENTING: promise dibuat per ronde (maks 150 bersamaan), BUKAN sekaligus
        // untuk seluruh slice — 3000 koneksi serentak membuat FreePBX tersedak
        // dan semuanya melambat.
        $found = 0;
        $processed = 0;
        $existence = $this->existingFiles($rows);
        foreach ($rows->chunk(self::CONCURRENCY) as $round) {
            $promises = [];
            $paths = [];
            foreach ($round as $row) {
                $filename = basename($row->recordingfile);
                $folder = $this->folderFor($filename);
                $key = ($folder !== '' ? $folder . '/' : '') . $filename;
                // Lewati file yang pasti tidak ada di FreePBX (hasil pencocokan
                // daftar isi folder via SSH) — hemat ratusan ribu request 404.
                if ($existence !== null && !isset($existence[$key])) {
                    continue;
                }
                $url = $folder !== ''
                    ? "http://172.16.1.24/monitor/{$folder}/{$filename}"
                    : "http://172.16.1.24/monitor/{$filename}";
                $dest = $this->stagingDir() . '/' . str_replace([':', ' '], '_', $row->calldate) . '_' . $filename;
                $paths[] = $dest;
                // Unduh langsung ke disk (sink) agar memori tetap datar
                $promises[] = $client->getAsync($url, ['sink' => $dest]);
            }
            if (empty($promises)) {
                continue;
            }
            $responses = Utils::settle($promises)->wait();
            foreach ($responses as $key => $response) {
                $ok = $response['state'] === 'fulfilled'
                    && $response['value']->getStatusCode() === 200
                    && is_file($paths[$key]) && filesize($paths[$key]) > 0;
                if ($ok) {
                    $found++;
                } elseif (isset($paths[$key]) && is_file($paths[$key])) {
                    @unlink($paths[$key]);
                }
            }
            $processed += $round->count();
        }

        $lastId = (int) $rows->last()->id;
        $done = ((int) ($state['done'] ?? 0)) + $rows->count();
        $foundTotal = ((int) ($state['found'] ?? 0)) + $found;

        \Illuminate\Support\Facades\Log::info(sprintf(
            'ZIP %s: potong s/d id %d (+%d file, %d ok)',
            $this->filename, $lastId, $rows->count(), $found
        ));

        $hasMore = $rows->count() >= self::SLICE_SIZE;
        if ($hasMore) {
            Cache::put($this->stateKey(), array_merge($state, [
                'ready' => false,
                'done' => $done,
                'found' => $foundTotal,
                'last_id' => $lastId,
            ]), now()->addHours(12));
            // Lanjut potongan berikutnya (antre di belakang job lain)
            self::dispatch($this->filename, $this->filters, $lastId);
        } else {
            Cache::put($this->stateKey(), array_merge($state, [
                'ready' => false,
                'done' => $done,
                'found' => $foundTotal,
                'last_id' => $lastId,
                'assembling' => true,
            ]), now()->addHours(12));
            $this->assemble();
        }
    }

    /**
     * Cocokkan kedua sisi: daftar isi folder tanggal di FreePBX (via SSH, sekali
     * per folder + di-cache) vs recordingfile di DB. Return map "folder/file"
     * yang ADA, atau null bila SSH gagal (fallback: coba unduh semua via HTTP).
     */
    protected function existingFiles($rows): ?array
    {
        $folders = [];
        foreach ($rows as $row) {
            $folders[$this->folderFor(basename($row->recordingfile))] = true;
        }

        $host = (string) config('services.freepbx.host', '');
        $user = (string) config('services.freepbx.user', 'root');
        $pass = (string) config('services.freepbx.pass', '');
        $base = rtrim((string) config('services.freepbx.monitor_path', '/var/spool/asterisk/monitor'), '/');
        if ($host === '' || $pass === '') {
            return null;
        }

        try {
            $ssh = new \phpseclib3\Net\SSH2($host, 22, 10);
            if (!$ssh->login($user, $pass)) {
                return null;
            }
            $exist = [];
            foreach (array_keys($folders) as $folder) {
                $cacheKey = 'zip_ls_' . md5($host . '|' . $base . '|' . $folder);
                $files = \Illuminate\Support\Facades\Cache::remember(
                    $cacheKey,
                    now()->addMinutes(30),
                    function () use ($ssh, $base, $folder) {
                        $path = $folder !== '' ? $base . '/' . $folder : $base;
                        $out = $ssh->exec('ls -1 ' . escapeshellarg($path) . ' 2>/dev/null');
                        if (!is_string($out) || trim($out) === '') {
                            return [];
                        }
                        return array_values(array_filter(array_map('trim', explode("\n", $out))));
                    }
                );
                foreach ($files as $f) {
                    $exist[($folder !== '' ? $folder . '/' : '') . $f] = true;
                }
            }
            return $exist;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ZIP match SSH gagal, fallback HTTP semua: ' . $e->getMessage());
            return null;
        }
    }

    protected function folderFor(string $filename): string
    {
        if (preg_match('/-(\d{4})(\d{2})(\d{2})-/', $filename, $m)) {
            return "{$m[1]}/{$m[2]}/{$m[3]}";
        }
        return '';
    }

    protected function applyFilters($query): void
    {
        if (!empty($this->filters['supervisor_extension'])) {
            $spv = \App\Models\Agent::where('extension', $this->filters['supervisor_extension'])->first();
            if ($spv) {
                $managed = \App\Models\Agent::where('supervisor_id', $spv->id)->orWhere('id', $spv->id)->pluck('extension')->toArray();
                $query->where(function ($q) use ($managed) {
                    $q->whereIn('src', $managed)->orWhereIn('dst', $managed);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        if (!empty($this->filters['agent_extension'])) {
            $ext = $this->filters['agent_extension'];
            $query->where(function ($q) use ($ext) {
                $q->where('src', $ext)->orWhere('dst', $ext);
            });
        }
        if (!empty($this->filters['search'])) {
            $keyword = trim((string) $this->filters['search']);
            $digits = preg_replace('/\D/', '', $keyword);
            if ($digits !== '' && strlen($digits) >= 6 && preg_match('/^[\d\s\+\-\(\)]+$/', $keyword)) {
                // Nomor: exact match via index.
                $variants = array_values(array_unique(array_filter([$keyword, $digits])));
                if (str_starts_with($digits, '62')) {
                    $variants[] = '0' . substr($digits, 2);
                } elseif (str_starts_with($digits, '0')) {
                    $variants[] = '62' . substr($digits, 1);
                }
                $query->where(function ($q) use ($variants) {
                    $q->whereIn('src', $variants)->orWhereIn('dst', $variants);
                });
            } else {
                $query->where(function ($q) use ($keyword) {
                    $q->where('src', 'like', "%{$keyword}%")->orWhere('dst', 'like', "%{$keyword}%");
                });
            }
        }
        if (!empty($this->filters['start_date'])) {
            $query->where('calldate', '>=', $this->filters['start_date'] . ' 00:00:00');
        }
        if (!empty($this->filters['end_date'])) {
            $query->where('calldate', '<=', $this->filters['end_date'] . ' 23:59:59');
        }
    }

    /** Gabungkan semua file staging menjadi 1 ZIP final (streaming, hemat memori). */
    protected function assemble(): void
    {
        $exportDir = storage_path('app/public/exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        $finalPath = $exportDir . '/' . $this->filename;

        $zip = new ZipArchive();
        if ($zip->open($finalPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat file ZIP.');
        }
        $files = glob($this->stagingDir() . '/*') ?: [];
        sort($files);
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $zip->addFile($file, basename($file));
            $zip->setCompressionName(basename($file), ZipArchive::CM_STORE);
        }
        $zip->close();

        // Bersihkan staging
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->stagingDir());

        $state = Cache::get($this->stateKey(), []);
        Cache::put($this->stateKey(), array_merge($state, [
            'ready' => true,
            'url' => asset('storage/exports/' . $this->filename),
        ]), now()->addMinutes(30));
    }

    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('ZIP chunk gagal ' . $this->filename . ': ' . $e->getMessage());
        $state = Cache::get($this->stateKey(), []);
        Cache::put($this->stateKey(), array_merge($state, [
            'ready' => false,
            'error' => substr($e->getMessage(), 0, 200),
        ]), now()->addHours(12));
    }
}
