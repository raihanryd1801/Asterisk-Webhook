<?php

namespace App\Http\Middleware;

use App\Models\FeatureFlag;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PremiumAccess
{
    /**
     * Superadmin selalu lolos. User lain hanya lolos jika flag modulnya ON.
     * Jika terkunci tampilkan halaman gembok Premium Feature.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && ($user->role ?? null) === 'superadmin') {
            return $next($request);
        }

        $flagKey = $this->resolveFlagKey($request);

        if ($flagKey && FeatureFlag::isEnabled($flagKey)) {
            return $next($request);
        }

        $feature = $this->resolveFeatureName($request);

        // Request non-GET (form submit via fetch) atau yang minta JSON selalu dibalas JSON
        // agar tidak crash "Unexpected token '<'" saat di-parse response.json().
        $wantsJson = $request->expectsJson() || $request->wantsJson() || $request->ajax() || !$request->isMethod('GET');

        if ($wantsJson) {
            return response()->json([
                'status' => 'error',
                'message' => "{$feature} adalah Premium Feature. Hubungi admin jika ingin menggunakannya.",
            ], 403);
        }

        // Status 200 disengaja: Turbo Drive hanya me-render respons 2xx dengan benar.
        // Respons 403 via klik sidebar tampil polos tanpa CSS (baru normal setelah refresh).
        // Isi halaman ini hanya notice gembok, tidak ada data sensitif.
        return response()->view('premium.locked', compact('feature'), 200);
    }

    protected function resolveFlagKey(Request $request): ?string
    {
        $routeName = (string) $request->route()?->getName();

        // Fallback ke path URL untuk route yang tidak bernama
        $path = '/' . ltrim($request->path(), '/');

        return match (true) {
            // Buckets ikut flag CRM (bukan Collection)
            str_starts_with($routeName, 'crm.collection.buckets') || str_contains($path, 'crm/collection/buckets') => 'crm',
            // Debt Collectors ikut flag Collection
            str_starts_with($routeName, 'crm.collectors') || str_contains($path, 'crm/collectors') => 'collection',
            str_starts_with($routeName, 'crm.collection') || str_contains($path, 'crm/collection') => 'collection',
            str_starts_with($routeName, 'crm.dialer') || str_contains($path, 'crm/dialer') => 'dialer',
            str_starts_with($routeName, 'crm.') || str_contains($path, 'crm/customers') || str_contains($path, 'crm/dashboard') || str_contains($path, 'crm/whatsapp') => 'crm',
            default => null,
        };
    }

    protected function resolveFeatureName(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();
        $path = '/' . ltrim($request->path(), '/');

        return match (true) {
            str_starts_with($routeName, 'crm.collection.buckets') || str_contains($path, 'crm/collection/buckets') => 'Buckets',
            str_starts_with($routeName, 'crm.collectors') || str_contains($path, 'crm/collectors') => 'Debt Collectors',
            str_starts_with($routeName, 'crm.collection') || str_contains($path, 'crm/collection') => 'Collection',
            str_starts_with($routeName, 'crm.dialer') || str_contains($path, 'crm/dialer') => 'Auto-Dialer',
            str_starts_with($routeName, 'crm.customers') || str_contains($path, 'crm/customers') => 'Customers',
            str_starts_with($routeName, 'crm.whatsapp') || str_contains($path, 'crm/whatsapp') => 'WhatsApp Saya',
            str_starts_with($routeName, 'crm.') || str_contains($path, 'crm/dashboard') => 'CRM',
            default => 'Fitur ini',
        };
    }
}
