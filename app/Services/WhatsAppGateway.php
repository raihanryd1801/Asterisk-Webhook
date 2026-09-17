<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client ke sidecar wa-gateway (Baileys multi-session).
 * Session id: "user:{id}" untuk admin/superadmin, "spv:{extension}" untuk supervisor.
 * Agent TIDAK punya sesi sendiri (Opsi B): numpang sesi SPV yang meng-assign dia.
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
        // Opsi B: agent numpang sesi SPV yang meng-assign dia.
        return static::sessionIdForAgentExtension(session('agent_extension'));
    }

    /**
     * Resolve sesi SPV pemilik untuk seorang agent (via pivot agent_supervisor).
     * Return "spv:{extension}" atau null bila agent belum di-assign ke SPV mana pun.
     */
    public static function sessionIdForAgentExtension(?string $agentExtension): ?string
    {
        if (!$agentExtension) {
            return null;
        }
        try {
            $agent = \App\Models\Agent::where('extension', $agentExtension)->first();
            $spv = $agent?->supervisors()->orderBy('agents.id')->first();
            if ($spv) {
                return 'spv:' . $spv->extension;
            }
        } catch (\Throwable $e) {
            // Tabel belum siap / DB down — biarkan caller menangani null.
        }
        return null;
    }

    /** Info pemilik sesi efektif (untuk banner "numpang sesi SPV X"). */
    public static function ownerInfoForCurrentUser(): array
    {
        if (auth()->check()) {
            return ['mode' => 'own', 'session_id' => 'user:' . auth()->id(), 'label' => auth()->user()->name . ' (admin)'];
        }
        if (session('supervisor_extension')) {
            $ext = session('supervisor_extension');
            $spv = \App\Models\Agent::where('extension', $ext)->first();
            return ['mode' => 'own', 'session_id' => 'spv:' . $ext, 'label' => ($spv?->name ?? 'SPV') . " (Ext: {$ext})"];
        }
        if (session('agent_extension')) {
            $ext = session('agent_extension');
            $agent = \App\Models\Agent::where('extension', $ext)->first();
            $spv = $agent?->supervisors()->orderBy('agents.id')->first();
            if ($spv) {
                return [
                    'mode' => 'shared',
                    'session_id' => 'spv:' . $spv->extension,
                    'label' => ($spv->name ?? 'SPV') . " (Ext: {$spv->extension})",
                    'agent_id' => $agent?->id,
                    'agent_name' => $agent?->name,
                    'agent_extension' => $ext,
                ];
            }
            return ['mode' => 'none', 'session_id' => null, 'label' => 'Belum di-assign ke SPV'];
        }
        return ['mode' => 'none', 'session_id' => null, 'label' => '-'];
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

    public function send(string $sessionId, string $to, string $message, string $server = 's.whatsapp.net', ?array $media = null, bool $fast = false): array
    {
        try {
            $payload = [
                'to' => $to,
                'message' => $message,
                'server' => $server === 'lid' ? 'lid' : 's.whatsapp.net',
                'fast' => $fast, // true = balasan interaktif, lewati jeda pacing blast
            ];
            if ($media) {
                $payload['media'] = $media;
            }
            $res = $this->client()->timeout(180)->post("/sessions/{$sessionId}/send", $payload);
            $data = $res->json() ?? [];
            return [
                'ok' => $res->ok() && ($data['ok'] ?? false),
                'message' => $data['message'] ?? ('HTTP ' . $res->status()),
                'id' => $data['id'] ?? null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'id' => null];
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
