<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pagar anti-bola-salju untuk endpoint web yang berat:
 * - Batasi tiap SELECT maksimal 30 detik di level MySQL. Query yang kebablasan
 *   dibunuh server (Error 3024), bukan menumpuk sampai CPU 192%.
 * - Direset ke 0 (tanpa batas, default) setelah respons terkirim karena koneksi
 *   DB dipakai ulang antar request (Octane persistent connection).
 */
class DbQueryTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            DB::statement('SET SESSION MAX_EXECUTION_TIME=30000');
        } catch (\Throwable $e) {
            // Driver non-MySQL / tanpa hak: lanjut tanpa pagar
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            DB::statement('SET SESSION MAX_EXECUTION_TIME=0');
        } catch (\Throwable $e) {
        }
    }
}
