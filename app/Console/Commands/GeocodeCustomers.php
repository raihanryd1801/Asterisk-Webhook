<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\GeocodeService;
use Illuminate\Console\Command;

/**
 * Geocode alamat customer via Nominatim OSM (gratis, tanpa API key).
 * Aturan main OSM: maks ~1 request/detik + hasilnya WAJIB di-cache
 * (disimpan di kolom latitude/longitude) — jangan di-loop massal.
 */
class GeocodeCustomers extends Command
{
    protected $signature = 'customers:geocode
        {--limit=100 : Maksimal customer per jalan}
        {--id= : Hanya geocode 1 customer (ID) — untuk koreksi titik}
        {--refresh : Geocode ulang walau sudah ada koordinat}';

    protected $description = 'Isi latitude/longitude customer via Nominatim OSM';

    public function handle(GeocodeService $geocoder): int
    {
        $limit = (int) $this->option('limit');
        $refresh = (bool) $this->option('refresh');

        $q = Customer::whereNotNull('address')->where('address', '!=', '');
        $onlyIds = [];
        if ($this->option('id')) {
            $onlyIds = [(int) $this->option('id')];
            $q->whereIn('id', $onlyIds);
            $refresh = true; // koreksi titik = paksa geocode ulang
        } elseif (! $refresh) {
            $q->where(function ($qq) {
                $qq->whereNull('latitude')->orWhereNull('longitude');
            });
        }
        $total = (clone $q)->count();
        if ($total === 0) {
            $this->info('Tidak ada alamat yang perlu di-geocode.');

            return self::SUCCESS;
        }
        $this->info("Memproses maksimal {$limit} dari {$total} alamat...");
        $result = $geocoder->sync($limit, $refresh, function ($c, $coords, $ok) {
            if ($ok) {
                $this->line("OK {$c->name}: {$coords[0]},{$coords[1]}");
            } else {
                $this->warn("MISS {$c->name}: {$c->address}");
            }
        }, $onlyIds);

        $this->info("Selesai: {$result['mapped']}/{$result['processed']} terpetakan.");

        return self::SUCCESS;
    }
}
