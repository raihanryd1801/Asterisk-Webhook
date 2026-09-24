<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Sinkronisasi koordinat peta di background (dipicu tombol di UI). */
class GeocodeSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(public int $limit = 20) {}

    public function handle(\App\Services\GeocodeService $geocoder): void
    {
        // Langsung pakai service — jangan Artisan::call agar tahan terhadap
        // Octane/daemon worker yang stale terhadap command baru.
        $geocoder->sync($this->limit);
    }
}
