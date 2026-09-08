<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CrmAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Cek jika user BUKAN Admin dan TIDAK memiliki session SPV
        if (!auth()->check() && !session()->has('supervisor_extension')) {
            return redirect('/agent/login')->with('error', 'Silakan login terlebih dahulu.');
        }

        return $next($request);
    }
}