<?php

namespace App\Http\Controllers\Api;

use App\Events\CollectorTrackingStopped;
use App\Http\Controllers\Controller;
use App\Models\DebtCollector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Login/logout HP collector lapangan.
 * Login: nomor HP + PIN -> token Bearer (token lama otomatis mati).
 * Token dipakai di header Authorization untuk semua endpoint v1/collector.
 */
class CollectorAuthController extends Controller
{
    /** Login: tukarkan nomor HP + PIN menjadi token. Rate-limit ketat (lihat routes). */
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'pin' => 'required|string|max:32',
        ]);

        $digits = preg_replace('/\D/', '', (string) $request->phone);
        $variants = array_values(array_unique(array_filter([
            $digits,
            str_starts_with($digits, '0') ? '62'.substr($digits, 1) : null,
            str_starts_with($digits, '62') ? '0'.substr($digits, 2) : null,
            '+'.$digits,
        ])));

        $collector = DebtCollector::whereIn('phone', $variants)->first();

        if (!$collector || !$collector->is_active || !$collector->pin
            || !Hash::check((string) $request->pin, $collector->pin)) {
            // Pesan generik: jangan bocorkan mana yang salah (anti enumerasi).
            return response()->json(['status' => 'error', 'message' => 'Nomor / PIN salah, atau akun nonaktif.'], 401);
        }

        // Satu token aktif per login baru: token lama (HP hilang dsb.) mati.
        $plain = bin2hex(random_bytes(32));
        $collector->update(['api_token' => hash('sha256', $plain)]);

        return response()->json([
            'status' => 'success',
            'token' => $plain,
            'collector' => [
                'id' => $collector->id,
                'name' => $collector->name,
                'type' => $collector->type,
                'area' => $collector->area,
            ],
        ]);
    }

    /** Logout: matikan token + tandai tracking berhenti. */
    public function logout(Request $request)
    {
        /** @var DebtCollector $collector */
        $collector = $request->attributes->get('collector');
        $collector->update(['api_token' => null, 'is_tracking' => false]);

        try {
            broadcast(new CollectorTrackingStopped($collector))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['status' => 'success']);
    }
}
