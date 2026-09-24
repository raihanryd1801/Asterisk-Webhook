<?php

namespace App\Http\Controllers\Api;

use App\Events\CollectorPositionUpdated;
use App\Http\Controllers\Controller;
use App\Models\CollectorPosition;
use Illuminate\Http\Request;

/**
 * Endpoint HP collector lapangan (auth: middleware collector.token).
 * POST /api/v1/collector/position — kirim tiap 30-60 detik / tiap pindah.
 */
class CollectorTrackingController extends Controller
{
    /**
     * Dedup: abaikan bila < 30 dtk DAN geser < 15 m dari titik terakhir.
     * Ambang sengaja longgar agar gerakan jalan kaki (±1,4 m/dtk) tetap
     * terekam tiap beberapa kiriman — jejak jadi halus, bukan patah-patah.
     * (Pengirim disarankan kirim tiap 5-10 dtk / tiap geser ±10 m.)
     */
    protected const DEDUP_SECONDS = 30;
    protected const DEDUP_METERS = 15;

    public function store(Request $request)
    {
        /** @var \App\Models\DebtCollector $collector */
        $collector = $request->attributes->get('collector');

        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0|max:100000',
            'recorded_at' => 'nullable|date',
        ]);

        $lat = (float) $request->latitude;
        $lon = (float) $request->longitude;
        // recorded_at dari HP umumnya ISO UTC (akhiran Z). WAJIB dikonversi
        // ke timezone aplikasi — kalau tidak, jam tersimpan 7 jam mundur
        // ( UTC dibaca mentah sebagai WIB ) dan collector selalu offline.
        // Pola sama dipakai di WaController@inbound.
        $at = $request->recorded_at
            ? \Carbon\Carbon::parse($request->recorded_at)->setTimezone(config('app.timezone'))
            : now();
        // Tolak timestamp ngawur (jam HP salah / replay attack): di luar ±1 hari.
        if ($at->lt(now()->subDay()) || $at->gt(now()->addMinutes(10))) {
            return response()->json(['status' => 'error', 'message' => 'Waktu posisi tidak wajar. Periksa jam HP.'], 422);
        }

        // Hemat baterai + baris DB: posisi nyaris sama & baru = tidak disimpan.
        $last = $collector->lastPosition();
        if ($last && $last->recorded_at->diffInSeconds($at) < self::DEDUP_SECONDS
            && $last->distanceTo($lat, $lon) < self::DEDUP_METERS) {
            return response()->json(['status' => 'success', 'deduped' => true]);
        }

        $pos = CollectorPosition::create([
            'collector_id' => $collector->id,
            'latitude' => $lat,
            'longitude' => $lon,
            'accuracy' => $request->accuracy !== null ? (int) $request->accuracy : null,
            'recorded_at' => $at,
        ]);

        // Posisi masuk = tracking (kembali) jalan.
        if (!$collector->is_tracking) {
            $collector->update(['is_tracking' => true]);
        }

        // Live ke peta supervisor (ShouldBroadcastNow = tanpa antre queue).
        try {
            broadcast(new CollectorPositionUpdated($pos))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['status' => 'success', 'id' => $pos->id]);
    }

    /** Profil ringkas collector pemilik token (untuk tes koneksi HP). */
    public function me(Request $request)
    {
        $collector = $request->attributes->get('collector');

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $collector->id,
                'name' => $collector->name,
                'type' => $collector->type,
                'area' => $collector->area,
            ],
        ]);
    }

    /**
     * HP menekan "Hentikan Tracking" -> offline SEKEJAP (tanpa tunggu timeout
     * 5 menit). Idempoten: dipanggil berkali-kali tetap aman.
     */
    public function stop(Request $request)
    {
        /** @var \App\Models\DebtCollector $collector */
        $collector = $request->attributes->get('collector');
        $collector->update(['is_tracking' => false]);

        try {
            broadcast(new \App\Events\CollectorTrackingStopped($collector))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['status' => 'success']);
    }
}
