<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Http;

/**
 * Geocode alamat customer via Nominatim OSM (gratis, tanpa API key).
 * Aturan main OSM: maks ~1 request/detik + hasilnya WAJIB di-cache
 * (disimpan di kolom latitude/longitude) — jangan di-loop massal.
 *
 * Dipakai bersama oleh Command (cron) dan Job (tombol UI) agar tidak
 * bergantung pada Artisan::call di dalam queue worker (rentan stale
 * di Octane/daemon worker).
 *
 * Anti-nyasar: nama jalan umum (cth "Jl. Slamet Riyadi") ada di banyak
 * kota — hit Nominatim untuk alamat lengkap WAJIB mengandung petunjuk
 * kota dari alamat, kalau tidak cocok lanjut ke lapis berikutnya
 * (tanpa nomor rumah -> kota saja). Titik yang lolos validasi kota
 * tidak akan pernah beda kota dengan alamatnya.
 */
class GeocodeService
{
    /** Kata generik yang diabaikan saat ekstraksi petunjuk kota. */
    protected const STOP_WORDS = [
        'kota', 'kabupaten', 'kab', 'kecamatan', 'kec', 'kelurahan', 'kel',
        'desa', 'jalan', 'jl', 'no', 'rt', 'rw', 'dusun', 'provinsi', 'kecamatam',
    ];

    /**
     * Sinkronkan maksimal $limit customer yang beralamat tapi belum berkoordinat.
     *
     * @param  array<int>  $onlyIds  Batasi ke ID tertentu (koreksi titik)
     * @return array{processed:int,mapped:int}
     */
    public function sync(int $limit = 20, bool $refresh = false, ?callable $onProgress = null, array $onlyIds = []): array
    {
        $q = Customer::whereNotNull('address')->where('address', '!=', '');
        if ($onlyIds !== []) {
            $q->whereIn('id', $onlyIds);
            $refresh = true; // koreksi titik = paksa geocode ulang
        } elseif (! $refresh) {
            $q->where(function ($qq) {
                $qq->whereNull('latitude')->orWhereNull('longitude');
            });
        }
        $customers = $q->orderBy('id')->limit(max(1, $limit))->get();

        $processed = 0;
        $mapped = 0;
        foreach ($customers as $c) {
            $processed++;
            $hit = $this->geocodeLayered($c->address);
            // Jeda 1,2 detik antar customer (boleh beberapa query per customer,
            // tetap hormati fair-use OSM karena query berurutan).
            usleep(1200000);
            if ($hit) {
                $c->update([
                    'latitude' => $hit['lat'],
                    'longitude' => $hit['lon'],
                    'geocode_label' => $hit['label'],
                ]);
                $mapped++;
                if ($onProgress) {
                    $onProgress($c, [$hit['lat'], $hit['lon']], true);
                }
            } elseif ($onProgress) {
                $onProgress($c, null, false);
            }
        }

        return ['processed' => $processed, 'mapped' => $mapped];
    }

    /**
     * Coba berlapis, return ['lat'=>float,'lon'=>float,'label'=>string] atau null.
     * Lapis jalan (full & tanpa nomor) divalidasi kota; lapis kota diterima langsung.
     */
    public function geocodeLayered(string $address): ?array
    {
        $hit = $this->geocode($address);
        if ($hit && $this->matchesCity($hit['label'], $address)) {
            return $hit;
        }
        // Buang nomor rumah ("No. 136", "No 136") lalu coba lagi
        $noNum = trim((string) preg_replace('/\bNo\.?\s*\d+[A-Za-z-]*/i', '', $address));
        $noNum = trim($noNum, " \t\n\r\0\x0B,");
        if ($noNum !== '' && strcasecmp($noNum, $address) !== 0) {
            usleep(1200000);
            $hit = $this->geocode($noNum);
            if ($hit && $this->matchesCity($hit['label'], $address)) {
                return $hit;
            }
        }
        // Terakhir: kota saja (bagian setelah koma terakhir) — diterima langsung
        // karena query kota di Nominatim andal, dan titik level kota tetap benar kotanya.
        $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
        $city = end($parts);
        if ($city && strcasecmp($city, $address) !== 0 && strcasecmp($city, $noNum) !== 0) {
            usleep(1200000);

            return $this->geocode($city);
        }

        return null;
    }

    /** Return ['lat'=>float,'lon'=>float,'label'=>string] atau null. */
    public function geocode(string $address): ?array
    {
        try {
            $res = Http::withHeaders(['User-Agent' => config('app.name', 'CRM').' geocoder (contact: admin)'])
                ->timeout(15)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'id',
                ]);
            $first = $res->json()[0] ?? null;
            if ($first && isset($first['lat'], $first['lon'])) {
                return [
                    'lat' => (float) $first['lat'],
                    'lon' => (float) $first['lon'],
                    'label' => (string) ($first['display_name'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    /** Reverse-geocode satu titik (untuk audit titik lama). */
    public function reverse(float $lat, float $lon): ?string
    {
        try {
            $res = Http::withHeaders(['User-Agent' => config('app.name', 'CRM').' geocoder (contact: admin)'])
                ->timeout(15)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'format' => 'json',
                ]);
            $label = $res->json()['display_name'] ?? null;

            return $label ? (string) $label : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Apakah label hasil Nominatim sekota dengan alamat?
     * Alamat tanpa koma (tanpa petunjuk kota) selalu dianggap cocok.
     */
    public function matchesCity(string $label, string $address): bool
    {
        $hints = $this->cityHints($address);
        if ($hints === []) {
            return true;
        }
        $lower = mb_strtolower($label);
        foreach ($hints as $hint) {
            if (str_contains($lower, $hint)) {
                return true;
            }
        }

        return false;
    }

    /** Ambil kata kunci kota dari segmen terakhir alamat (cth "Kota Probolinggo" -> ["probolinggo"]). */
    public function cityHints(string $address): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
        if (count($parts) < 2) {
            return [];
        }
        // Dua segmen terakhir (kota + provinsi) — provinsi ikut jadi cadangan.
        $tail = array_slice($parts, -2);
        $hints = [];
        foreach ($tail as $segment) {
            $words = preg_split('/[^a-zA-Z]+/', mb_strtolower($segment)) ?: [];
            foreach ($words as $w) {
                if (mb_strlen($w) >= 4 && ! in_array($w, self::STOP_WORDS, true)) {
                    $hints[] = $w;
                }
            }
        }

        return array_values(array_unique($hints));
    }
}
