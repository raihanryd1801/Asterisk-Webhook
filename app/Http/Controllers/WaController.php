<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppGateway;
use Illuminate\Http\Request;

class WaController extends Controller
{
    protected function sessionLabel(?string $sessionId): string
    {
        if (auth()->check()) {
            return auth()->user()->name . ' (admin)';
        }
        $ext = session('supervisor_extension');
        if ($ext) {
            $spv = \App\Models\Agent::where('extension', $ext)->first();
            return ($spv?->name ?? 'SPV') . " (Ext: {$ext})";
        }
        return $sessionId ?? '-';
    }

    public function index(WhatsAppGateway $gateway)
    {
        $sessionId = WhatsAppGateway::sessionIdForCurrentUser();
        $state = $sessionId ? $gateway->status($sessionId) : null;
        $reachable = $gateway->reachable();

        return view('crm.whatsapp.index', [
            'sessionId' => $sessionId,
            'state' => $state,
            'reachable' => $reachable,
            'ownerLabel' => $this->sessionLabel($sessionId),
        ]);
    }

    public function connect(WhatsAppGateway $gateway)
    {
        $sessionId = WhatsAppGateway::sessionIdForCurrentUser();
        if (!$sessionId) {
            return response()->json(['status' => 'error', 'message' => 'Silakan login dulu.'], 401);
        }

        $state = $gateway->ensure($sessionId);
        if (!$state) {
            return response()->json(['status' => 'error', 'message' => 'Gateway WA tidak dapat dihubungi. Pastikan service wa-gateway jalan.'], 502);
        }

        return response()->json(['status' => 'success', 'session' => $state]);
    }

    public function status(WhatsAppGateway $gateway)
    {
        $sessionId = WhatsAppGateway::sessionIdForCurrentUser();
        if (!$sessionId) {
            return response()->json(['status' => 'error', 'message' => 'Silakan login dulu.'], 401);
        }

        return response()->json([
            'status' => 'success',
            'session' => $gateway->status($sessionId),
        ]);
    }

    public function csrf()
    {
        // Token fresh dari sesi aktif — dipakai tepat sebelum POST agar
        // mustahil basi (meta/cookie bisa basi kalau tab lama / ganti akun).
        return response()->json(['status' => 'success', 'csrf' => csrf_token()]);
    }

    public function disconnect(WhatsAppGateway $gateway)
    {
        $sessionId = WhatsAppGateway::sessionIdForCurrentUser();
        if ($sessionId) {
            $gateway->destroy($sessionId);
        }

        return response()->json(['status' => 'success', 'message' => 'Sesi WhatsApp diputus. Scan ulang untuk menghubungkan nomor lain.']);
    }

    protected function currentSessionId(): ?string
    {
        return WhatsAppGateway::sessionIdForCurrentUser();
    }

    protected function resolveName(?int $customerId, ?string $fallback): ?string
    {
        if ($customerId) {
            $name = \App\Models\Customer::where('id', $customerId)->value('name');
            if ($name) {
                return $name;
            }
        }
        $fallback = trim((string) $fallback);
        return $fallback !== '' ? $fallback : null;
    }

    // ============ INBOX (per sesi milik sendiri) ============

    public function inbox()
    {
        return view('crm.whatsapp.inbox');
    }

    public function conversations()
    {
        $sessionId = $this->currentSessionId();
        if (!$sessionId) {
            return response()->json(['status' => 'error', 'message' => 'Silakan login dulu.'], 401);
        }

        $latestIds = \App\Models\WaMessage::where('session_id', $sessionId)
            ->selectRaw('MAX(id) as id')
            ->groupBy('phone');

        $rows = \App\Models\WaMessage::whereIn('id', $latestIds)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        // Tautan customer dicari di SELURUH thread (baris terbaru belum tentu tertaut)
        $linkedByPhone = \App\Models\WaMessage::where('session_id', $sessionId)
            ->whereIn('phone', $rows->pluck('phone')->unique()->values()->toArray())
            ->whereNotNull('customer_id')
            ->selectRaw('phone, MAX(customer_id) as customer_id')
            ->groupBy('phone')
            ->pluck('customer_id', 'phone')
            ->toArray();

        $phonesByCustomer = \App\Models\Customer::whereIn(
            'id', array_values($linkedByPhone)
        )->pluck('phone', 'id')->toArray();

        $unreads = \App\Models\WaMessage::where('session_id', $sessionId)
            ->where('direction', 'in')
            ->whereNull('read_at')
            ->selectRaw('phone, COUNT(*) as total')
            ->groupBy('phone')
            ->pluck('total', 'phone')
            ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $rows->map(function ($m) use ($linkedByPhone, $phonesByCustomer) {
                $cid = $m->customer_id ?? ($linkedByPhone[$m->phone] ?? null);
                return [
                'phone' => $m->phone,
                'name' => $m->name,
                'customer_id' => $cid,
                'customer_phone' => $cid ? ($phonesByCustomer[$cid] ?? null) : null,
                'last_message' => mb_substr($m->message, 0, 80),
                'direction' => $m->direction,
                'at' => $m->occurred_at?->toDateTimeString(),
                'unread' => (int) ($unreads[$m->phone] ?? 0),
                ];
            })->values(),
        ]);
    }

    public function thread(Request $request)
    {
        $sessionId = $this->currentSessionId();
        if (!$sessionId) {
            return response()->json(['status' => 'error', 'message' => 'Silakan login dulu.'], 401);
        }

        $request->validate(['phone' => 'required|string|max:20']);
        $phone = \App\Models\WaMessage::normalizePhone($request->phone);

        \App\Models\WaMessage::where('session_id', $sessionId)
            ->where('phone', $phone)
            ->where('direction', 'in')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = \App\Models\WaMessage::where('session_id', $sessionId)
            ->where('phone', $phone)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'message' => $m->message,
                'media_url' => $m->media_url,
                'media_kind' => $m->media_kind,
                'at' => $m->occurred_at?->format('d M H:i'),
            ]);

        $peer = \App\Models\WaMessage::where('session_id', $sessionId)
            ->where('phone', $phone)
            ->latest('id')
            ->first(['name', 'customer_id']);

        // Cari tautan customer di SELURUH thread (pesan terbaru belum tentu tertaut)
        $customerId = $peer?->customer_id;
        if (!$customerId) {
            $customerId = \App\Models\WaMessage::where('session_id', $sessionId)
                ->where('phone', $phone)
                ->whereNotNull('customer_id')
                ->latest('id')
                ->value('customer_id');
        }

        $customerPhone = null;
        if ($customerId) {
            $customerPhone = \App\Models\Customer::where('id', $customerId)->value('phone');
        }

        return response()->json([
            'status' => 'success',
            'phone' => $phone,
            'name' => $peer?->name,
            'customer_id' => $peer?->customer_id,
            'customer_phone' => $customerPhone,
            'messages' => $messages,
        ]);
    }

    public function reply(Request $request, WhatsAppGateway $gateway)
    {
        $sessionId = $this->currentSessionId();
        if (!$sessionId) {
            return response()->json(['status' => 'error', 'message' => 'Silakan login dulu.'], 401);
        }

        $request->validate([
            'phone' => 'required|string|max:20',
            'message' => 'required|string|max:2000',
        ]);

        $peer = \App\Models\WaMessage::where('session_id', $sessionId)
            ->where(function ($q) use ($request) {
                $q->where('phone', $request->phone)
                  ->orWhere('phone', \App\Models\WaMessage::normalizePhone($request->phone));
            })
            ->latest('id')
            ->first();

        // Balas ke server asal peer (LID dibalas via @lid, nomor via @s.whatsapp.net)
        $server = $peer?->jid_server ?? 's.whatsapp.net';
        $phone = $peer?->phone ?? \App\Models\WaMessage::normalizePhone($request->phone);
        $customerId = $peer?->customer_id ?? \App\Models\WaMessage::findCustomerId($phone);

        // Satukan ke thread kanonis customer bila sudah ada (anti thread ganda)
        [$phone, $server, $customerId] = \App\Models\WaMessage::resolveThreadKey(
            $sessionId, $phone, $server, $customerId
        );

        $result = $gateway->send($sessionId, $phone, $request->message, $server);
        if (!$result['ok']) {
            return response()->json(['status' => 'error', 'message' => 'Gagal kirim: ' . $result['message']], 502);
        }

        $msg = \App\Models\WaMessage::create([
            'session_id' => $sessionId,
            'direction' => 'out',
            'phone' => $phone,
            'jid_server' => $server,
            'name' => $peer?->name,
            'customer_id' => $customerId,
            'message' => $request->message,
            'occurred_at' => now(),
            'read_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Terkirim.', 'id' => $msg->id]);
    }

    public function linkCustomer(Request $request)
    {
        $sessionId = $this->currentSessionId();
        if (!$sessionId) {
            return response()->json(['status' => 'error', 'message' => 'Silakan login dulu.'], 401);
        }

        $request->validate([
            'phone' => 'required|string|max:20',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $name = null;
        if ($request->customer_id) {
            $name = \App\Models\Customer::where('id', $request->customer_id)->value('name');
        }

        \App\Models\WaMessage::where('session_id', $sessionId)
            ->where('phone', $request->phone)
            ->update(['customer_id' => $request->customer_id, 'name' => $name]);

        return response()->json(['status' => 'success', 'message' => 'Percakapan ditautkan.']);
    }

    public function unreadCount()
    {
        $sessionId = $this->currentSessionId();
        if (!$sessionId) {
            return response()->json(['status' => 'success', 'unread' => 0]);
        }

        return response()->json([
            'status' => 'success',
            'unread' => \App\Models\WaMessage::where('session_id', $sessionId)
                ->where('direction', 'in')
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    // ============ WEBHOOK dari sidecar (token gateway, tanpa session login) ============

    public function inbound(Request $request)
    {
        if ((string) $request->header('X-Gateway-Token') !== (string) env('WA_GATEWAY_TOKEN', '')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'session_id' => 'required|string|max:64',
            'text' => 'nullable|string',
            'message' => 'nullable|string',
            'media' => 'nullable|file|max:8192',
            'media_kind' => 'nullable|string|max:20',
        ]);

        $server = $request->input('remote_server') === 'lid' ? 'lid' : 's.whatsapp.net';
        $rawId = preg_replace('/@.*$/', '', (string) $request->input('remote_jid', ''));
        // LID murni disimpan apa adanya (jangan dinormalisasi jadi nomor telepon)
        $phone = $server === 'lid'
            ? preg_replace('/\D/', '', $rawId)
            : \App\Models\WaMessage::normalizePhone($rawId);

        if ($phone === '') {
            return response()->json(['status' => 'error', 'message' => 'Nomor tidak valid'], 422);
        }

        $customerId = $server === 'lid' ? null : \App\Models\WaMessage::findCustomerId($phone);

        $text = trim((string) ($request->input('text') ?? $request->input('message', '')));

        $mediaPath = null;
        $mediaMime = null;
        if ($request->hasFile('media') && $request->file('media')->isValid()) {
            $mediaMime = $request->file('media')->getMimeType() ?: 'application/octet-stream';
            $ext = $request->file('media')->getClientOriginalExtension() ?: 'bin';
            $mediaPath = $request->file('media')->storeAs(
                'wa-media/' . date('Y/m'),
                \Illuminate\Support\Str::uuid() . '.' . $ext,
                'public'
            );
        }

        if ($text === '' && !$mediaPath) {
            return response()->json(['status' => 'error', 'message' => 'Pesan kosong'], 422);
        }
        if ($text === '') {
            $text = \App\Models\WaMessage::mediaFallbackLabel($mediaMime);
        }

        // Satukan ke thread kanonis customer bila sudah ada (anti thread ganda)
        [$phone, $server, $customerId] = \App\Models\WaMessage::resolveThreadKey(
            $request->input('session_id'), $phone, $server, $customerId
        );

        // occurred_at dari sidecar berformat ISO UTC (akhiran Z).
        // Wajib dikonversi ke timezone aplikasi (WIB) sebelum disimpan,
        // kalau tidak jam tampil 7 jam lebih lambat dari pesan keluar.
        $occurred = $request->input('occurred_at')
            ? \Carbon\Carbon::parse($request->input('occurred_at'))->setTimezone(config('app.timezone'))
            : now();

        \App\Models\WaMessage::create([
            'session_id' => $request->input('session_id'),
            'direction' => 'in',
            'phone' => $phone,
            'jid_server' => $server,
            'name' => $this->resolveName($customerId, $request->input('push_name')),
            'customer_id' => $customerId,
            'message' => mb_substr($text, 0, 4000),
            'media_path' => $mediaPath,
            'media_mime' => $mediaMime,
            'external_id' => $request->input('message_id'),
            'occurred_at' => $occurred,
        ]);

        return response()->json(['status' => 'success']);
    }

    public function senderInfo(WhatsAppGateway $gateway)
    {
        // Untuk modal blast: siapa pengirim + apakah siap
        $sessionId = WhatsAppGateway::sessionIdForCurrentUser();
        $state = $sessionId ? $gateway->status($sessionId) : null;

        return response()->json([
            'status' => 'success',
            'owner' => $this->sessionLabel($sessionId),
            'connected' => ($state['status'] ?? null) === 'connected',
            'phone' => $state['phone'] ?? null,
        ]);
    }
}
