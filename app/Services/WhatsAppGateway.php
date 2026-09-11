<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client ke sidecar wa-gateway (Baileys multi-session).
 * Session id: "user:{id}" untuk admin/superadmin, "spv:{extension}" untuk supervisor.
 */
class WhatsAppGateway
{
    protected string $base;
    protected string $token;

    public function __construct()
    {
        $this->base = rtrim(config('services.wa_gateway.url', env('WA_GATEWAY_URL', 'http://127.0.0.1:3001')), '/');
        $this->token = (string) env('WA_GATEWAY_TOKEN', '');
    }

    public static function sessionIdForCurrentUser(): ?string
    {
        if (auth()->check()) {
            return 'user:' . auth()->id();
        }
        $ext = session('supervisor_extension');
        if ($ext) {
            return 'spv:' . $ext;
        }
        return null;
    }

    protected function client()
    {
        return Http::baseUrl($this->base)
            ->withHeaders(['X-Gateway-Token' => $this->token])
            ->timeout(15);
    }

    public function status(string $sessionId): ?array
    {
        try {
            $res = $this->client()->get("/sessions/{$sessionId}");
            if (!$res->ok()) {
                return null;
            }
            return $res->json('session');
        } catch (\Throwable $e) {
            Log::warning('WA gateway status gagal: ' . $e->getMessage());
            return null;
        }
    }

    public function ensure(string $sessionId): ?array
    {
        try {
            $res = $this->client()->timeout(30)->post('/sessions', ['id' => $sessionId]);
            if (!$res->ok()) {
                return null;
            }
            return $res->json('session');
        } catch (\Throwable $e) {
            Log::warning('WA gateway ensure gagal: ' . $e->getMessage());
            return null;
        }
    }

    public function destroy(string $sessionId): bool
    {
        try {
            return $this->client()->delete("/sessions/{$sessionId}")->ok();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function send(string $sessionId, string $to, string $message, string $server = 's.whatsapp.net'): array
    {
        try {
            $res = $this->client()->timeout(120)->post("/sessions/{$sessionId}/send", [
                'to' => $to,
                'message' => $message,
                'server' => $server === 'lid' ? 'lid' : 's.whatsapp.net',
            ]);
            $data = $res->json() ?? [];
            return [
                'ok' => $res->ok() && ($data['ok'] ?? false),
                'message' => $data['message'] ?? ('HTTP ' . $res->status()),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function reachable(): bool
    {
        try {
            return Http::baseUrl($this->base)->timeout(5)->get('/health')->json('ok') === true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
