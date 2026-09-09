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
            str_starts_with($routeName, 'crm.campaigns') || str_contains($path, 'crm/campaigns') => 'campaign',
            str_starts_with($routeName, 'crm.collection') || str_contains($path, 'crm/collection') => 'collection',
            str_starts_with($routeName, 'crm.') || str_contains($path, 'crm/customers') || str_contains($path, 'crm/dashboard') => 'crm',
            default => null,
        };
    }

    protected function resolveFeatureName(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();
        $path = '/' . ltrim($request->path(), '/');

        return match (true) {
            str_starts_with($routeName, 'crm.campaigns') || str_contains($path, 'crm/campaigns') => 'Campaigns',
            str_starts_with($routeName, 'crm.collection') || str_contains($path, 'crm/collection') => 'Collection',
            str_starts_with($routeName, 'crm.customers') || str_contains($path, 'crm/customers') => 'Customers',
            str_starts_with($routeName, 'crm.') || str_contains($path, 'crm/dashboard') => 'CRM',
            default => 'Fitur ini',
        };
    }
}
