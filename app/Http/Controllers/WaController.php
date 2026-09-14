<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppGateway;
use Illuminate\Http\Request;

class WaController extends Controller
{
    protected function sessionLabel(?string $sessionId): string
    {
        $owner = \App\Services\WhatsAppGateway::ownerInfoForCurrentUser();
        return $owner['label'] ?? ($sessionId ?? '-');
    }

    /** Agent yang sedang login (null bila admin/SPV). */
    protected function currentAgent(): ?\App\Models\Agent
    {
        $ext = session('agent_extension');
        if (!$ext) {
            return null;
        }
        return \App\Models\Agent::where('extension', $ext)->first();
    }

    /** Daftar customer_id yang di-assign ke agent ini. Null = tanpa batas (admin/SPV). */
    protected function assignedCustomerIds(): ?array
    {
        $agent = $this->currentAgent();
        if (!$agent) {
            return null;
        }
        return \App\Models\Customer::where('assigned_agent_id', $agent->id)->pluck('id')->toArray();
    }

    /** Batasi query WaMessage ke cakupan agent (hanya thread customer miliknya). */
    protected function applyAgentScope($query)
    {
        $ids = $this->assignedCustomerIds();
        if (is_array($ids)) {
            $query->whereIn('customer_id', $ids);
        }
        return $query;
    }

    /** Aksi sensitif (QR/connect/disconnect) hanya pemilik sesi, bukan agent penumpang. */
    protected function blockSharedAgent()
    {
        if ($this->currentAgent()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi WhatsApp milik SPV Anda. Minta SPV untuk scan QR — Anda tinggal membalas pesan.',
            ], 403);
        }
        return null;
    }

    public function index(WhatsAppGateway $gateway)
    {
        // Agent penumpang tidak perlu halaman QR — arahkan ke inbox bersama.
        if ($this->currentAgent()) {
            return redirect()->route('crm.whatsapp.inbox')
                ->with('error', 'Sesi WhatsApp milik SPV Anda. Anda tinggal membalas pesan customer yang di-assign.');
        }
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
        if ($blocked = $this->blockSharedAgent()) {
            return $blocked;
        }
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
        if ($blocked = $this->blockSharedAgent()) {
            return $blocked;
        }
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

    // ============ INBOX (sesi sendiri, atau sesi SPV bersama untuk agent) ============

    public function inbox()
    {
        $owner = WhatsAppGateway::ownerInfoForCurrentUser();
        return view('crm.whatsapp.inbox', [
            'waMode' => $owner['mode'] ?? 'own',
            'waOwnerLabel' => $owner['label'] ?? '-',
            'isSharedAgent' => ($owner['mode'] ?? '') === 'shared',
        ]);
    }

    public function conversations()
    {
        $sessionId = $this->currentSessionId();
        if (!$sessionId) {
            $agent = $this->currentAgent();
            $msg = $agent
                ? 'Akun Anda belum di-assign ke SPV mana pun. Minta SPV untuk assign dulu.'
                : 'Silakan login dulu.';
            return response()->json(['status' => 'error', 'message' => $msg], 401);
        }

        // Opsi B level sesi: agent melihat SEMUA chat di sesi SPV bersama.
        // (Assign agent->SPV yang menentukan visibilitas, bukan assign
        // customer->agent. Jadi chat SPV dengan debitur mana pun ikut tampil.)
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

    /**
     * Cek apakah thread boleh dibuka/dibalas.
     * Level sesi: siapa pun yang memegang sesi (pemilik maupun agent yang
     * numpang via assign SPV) boleh membuka semua thread di sesi itu.
     * Yang belum bisa dijangkau hanya bila sessionId null (belum assign SPV).
     */
    protected function assertThreadAccess(string $sessionId, string $phone): ?array
    {
        return null;
    }

    public function thread(Request $request)
    {
        $sessionId = $this->currentSessionId();
        if (!$sessionId) {
            return response()->json(['status' => 'error', 'message' => 'Silakan login dulu.'], 401);
        }

        $request->validate(['phone' => 'required|string|max:20']);
        $phone = \App\Models\WaMessage::normalizePhone($request->phone);

        if ($denied = $this->assertThreadAccess($sessionId, $phone)) {
            return response()->json($denied, 403);
        }

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
                'tick' => $m->tick,
                'at' => $m->occurred_at?->format('d M H:i'),
                'replied_by' => $m->replied_by_label,
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
            'message' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|max:10240',
        ]);

        if (trim((string) $request->message) === '' && !$request->hasFile('attachment')) {
            return response()->json(['status' => 'error', 'message' => 'Isi pesan atau lampiran dulu.'], 422);
        }

        // Level sesi: agent boleh membalas thread mana pun di sesi SPV bersama.
        // Audit replied_by diisi setelah customerId ketemu (di bawah).
        $peer = \App\Models\WaMessage::where('session_id', $sessionId)
            ->where(function ($q) use ($request) {
                $q->where('phone', $request->phone)
                  ->orWhere('phone', \App\Models\WaMessage::normalizePhone($request->phone));
            })
            ->latest('id')
            ->first();

        // Untuk routing balasan, utamakan pesan MASUK terakhir: identitas
        // (PN vs LID) yang dipakai lawan bicara itulah yang pasti terkirim.
        // Kalau pesan masuk terakhir via @lid tapi kita balas via nomor biasa,
        // pesannya bisa nyangkut centang satu selamanya.
        $lastInbound = \App\Models\WaMessage::where('session_id', $sessionId)
            ->where('direction', 'in')
            ->where(function ($q) use ($peer, $request) {
                $q->whereIn('phone', array_filter([
                    $peer?->phone,
                    $request->phone,
                    \App\Models\WaMessage::normalizePhone($request->phone),
                ]));
                if ($peer?->customer_id) {
                    $q->orWhere('customer_id', $peer->customer_id);
                }
            })
            ->latest('id')
            ->first(['phone', 'jid_server']);

        // Balas ke alamat terakhir yang terbukti dipakai lawan bicara
        // (LID dibalas via @lid, nomor via @s.whatsapp.net)
        $server = $lastInbound?->jid_server ?? $peer?->jid_server ?? 's.whatsapp.net';
        $phone = $lastInbound?->phone ?? $peer?->phone ?? \App\Models\WaMessage::normalizePhone($request->phone);
        $customerId = $peer?->customer_id ?? \App\Models\WaMessage::findCustomerId($phone);

        // Satukan ke thread kanonis customer bila sudah ada (anti thread ganda)
        [$phone, $server, $customerId] = \App\Models\WaMessage::resolveThreadKey(
            $sessionId, $phone, $server, $customerId
        );

        // Audit pengirim (level sesi, tanpa batasan assign customer).
        $agent = $this->currentAgent();
        $repliedByAgentId = null;
        $repliedByLabel = null;
        if ($agent) {
            $repliedByAgentId = $agent->id;
            $repliedByLabel = $agent->name . " (Ext: {$agent->extension})";
        } else {
            // Audit juga untuk SPV/admin agar thread jelas siapa pengirimnya.
            $owner = WhatsAppGateway::ownerInfoForCurrentUser();
            $repliedByLabel = $owner['label'] ?? null;
        }

        // Lampiran opsional (gambar/dokumen): teruskan base64 ke gateway,
        // simpan salinannya untuk ditampilkan di thread.
        $mediaPayload = null;
        $mediaPath = null;
        $mediaMime = null;
        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            $file = $request->file('attachment');
            $mediaMime = $file->getMimeType() ?: 'application/octet-stream';
            $kind = str_starts_with($mediaMime, 'image/') ? 'image'
                : (str_starts_with($mediaMime, 'video/') ? 'video'
                : (str_starts_with($mediaMime, 'audio/') ? 'audio' : 'document'));
            $mediaPath = $file->storeAs(
                'wa-media/' . date('Y/m'),
                \Illuminate\Support\Str::uuid() . '.' . ($file->getClientOriginalExtension() ?: 'bin'),
                'public'
            );
            $mediaPayload = [
                'data' => base64_encode(file_get_contents($file->getRealPath())),
                'mimetype' => $mediaMime,
                'filename' => $file->getClientOriginalName(),
                'kind' => $kind,
            ];
        }

        $result = $gateway->send($sessionId, $phone, $request->message ?? '', $server, $mediaPayload);
        if (!$result['ok']) {
            // Bersihkan file yang sudah terlanjur disimpan bila kirim gagal
            if ($mediaPath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($mediaPath);
            }
            return response()->json(['status' => 'error', 'message' => 'Gagal kirim: ' . $result['message']], 502);
        }

        $text = trim((string) $request->message);
        if ($text === '' && $mediaPath) {
            $text = \App\Models\WaMessage::mediaFallbackLabel($mediaMime);
        }

        $msg = \App\Models\WaMessage::create([
            'session_id' => $sessionId,
            'direction' => 'out',
            'phone' => $phone,
            'jid_server' => $server,
            'name' => $peer?->name,
            'customer_id' => $customerId,
            'replied_by_agent_id' => $repliedByAgentId,
            'replied_by_label' => $repliedByLabel,
            'message' => mb_substr($text, 0, 4000),
            'media_path' => $mediaPath,
            'media_mime' => $mediaMime,
            'external_id' => $result['id'],
            'occurred_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Terkirim.', 'id' => $msg->id]);
    }

    public function linkCustomer(Request $request)
    {
        $sessionId = $this->currentSessionId();
        if (!$sessionId) {
            return response()->json(['status' => 'error', 'message' => 'Silakan login dulu.'], 401);
        }

        // Taut ulang customer = hak SPV/admin agar scoping agent tidak diakali.
        if ($this->currentAgent()) {
            return response()->json(['status' => 'error', 'message' => 'Hanya SPV yang boleh menautkan percakapan.'], 403);
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

    public function receipt(Request $request)
    {
        if ((string) $request->header('X-Gateway-Token') !== (string) env('WA_GATEWAY_TOKEN', '')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'session_id' => 'required|string|max:64',
            'message_id' => 'required|string|max:128',
            'stage' => 'required|in:delivered,read',
        ]);

        $update = $request->stage === 'read'
            ? ['delivered_at' => now(), 'read_at' => now()]
            : ['delivered_at' => now()];

        \App\Models\WaMessage::where('session_id', $request->session_id)
            ->where('direction', 'out')
            ->where('external_id', $request->message_id)
            ->where(function ($q) use ($request) {
                // delivered jangan menimpa read yang sudah ada
                if ($request->stage === 'delivered') {
                    $q->whereNull('read_at');
                }
            })
            ->update($update);

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
