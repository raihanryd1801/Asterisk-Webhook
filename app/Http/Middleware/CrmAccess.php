<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CrmAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Admin (tabel users), SPV (sesi supervisor), dan Agent (sesi agent, Opsi B:
        // numpang sesi SPV) boleh masuk. Pembatasan aksi sensitif (QR/blast)
        // ditangani di controller, bukan di sini.
        if (!auth()->check() && !session()->has('supervisor_extension') && !session()->has('agent_extension')) {
            return redirect('/agent/login')->with('error', 'Silakan login terlebih dahulu.');
        }

        return $next($request);
    }
}