<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Cek apakah user sudah login dan memiliki salah satu role yang diizinkan
        if (!$request->user() || !in_array($request->user()->role, $roles)) {
            abort(403, 'Unauthorized action. Anda tidak memiliki hak akses.');
        }

        return $next($request);
    }
}