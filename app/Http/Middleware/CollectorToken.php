<?php

namespace App\Http\Middleware;

use App\Models\DebtCollector;
use Closure;
use Illuminate\Http\Request;

/**
 * Otentikasi HP collector via header "Authorization: Bearer <api_token>".
 * Token disimpan ter-hash (sha256) di debt_collectors.api_token.
 * Collector hasil resolve tersedia via $request->attributes->get('collector').
 */
class CollectorToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'Token collector wajib (Authorization: Bearer).'], 401);
        }

        $collector = DebtCollector::where('api_token', hash('sha256', $token))->first();
        if (!$collector || !$collector->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Token tidak valid / collector nonaktif.'], 401);
        }

        $request->attributes->set('collector', $collector);

        return $next($request);
    }
}
