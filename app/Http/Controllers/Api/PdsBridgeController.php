<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DialQueueItem;
use App\Services\Asterisk\PdsDialService;
use Illuminate\Http\Request;

/**
 * Endpoint internal untuk AGI pds-bridge.php di server Asterisk.
 * Otorisasi via header X-Pds-Token (isi = PDS_BRIDGE_TOKEN), tanpa session login.
 *
 * Alur customer-first:
 *  1. Customer mengangkat -> dialplan [pds-connect] jalan.
 *  2. (Opsional) AMD -> vonis dikirim sebagai `amd`.
 *  3. AGI POST /api/pds/bridge {job_id, item_id, amd} -> Laravel menjawab
 *     ext agent yang valid detik itu juga (reservasi divalidasi ulang,
 *     kalau basi diganti agent idle lain) -> dialplan Dial ke ext itu.
 *  4. h-extension (opsional, nanti) POST /api/pds/result untuk hasil akhir.
 */
class PdsBridgeController extends Controller
{
    protected function checkToken(Request $request): ?string
    {
        $configured = (string) config('services.pds.bridge_token', '');
        if ($configured === '') {
            return 'PDS bridge belum dikonfigurasi (PDS_BRIDGE_TOKEN kosong).';
        }
        $given = (string) $request->header('X-Pds-Token', '');
        if (!hash_equals($configured, $given)) {
            return 'Unauthorized.';
        }
        return null;
    }

    protected function findItem(Request $request): ?DialQueueItem
    {
        $request->validate([
            'job_id' => 'required|integer',
            'item_id' => 'required|integer',
        ]);

        return DialQueueItem::where('id', $request->item_id)
            ->where('job_id', $request->job_id)
            ->where('status', 'dialing')
            ->first();
    }

    public function bridge(Request $request, PdsDialService $pds)
    {
        if ($err = $this->checkToken($request)) {
            return response()->json(['status' => 'error', 'message' => $err], $err === 'Unauthorized.' ? 401 : 503);
        }

        $item = $this->findItem($request);
        if (!$item) {
            // Item tidak dikenal / sudah selesai -> dialplan ke fallback queue.
            return response()->json(['status' => 'success', 'agent_ext' => '']);
        }

        $update = ['answered_at' => now()];
        if ($request->filled('amd')) {
            $amd = strtoupper((string) $request->input('amd'));
            if (in_array($amd, ['HUMAN', 'MACHINE', 'NOTSURE'], true)) {
                $update['amd_verdict'] = $amd;
            }
        }

        // 1. Reservasi awal masih valid?
        $ext = $item->agent_extension && $pds->agentStillIdle($item->agent_extension)
            ? $item->agent_extension
            : null;

        // 2. Basi -> ganti agent idle lain (atasi race).
        if (!$ext) {
            $idle = $pds->idleRotationAgents();
            $ext = $idle[0]->extension ?? null;
            if ($ext) {
                $update['agent_extension'] = $ext;
                $update['note'] = 'Reservasi dialihkan ke ' . $ext . ' (awal basi)';
            }
        }

        $item->update($update);

        return response()->json(['status' => 'success', 'agent_ext' => $ext ?? '']);
    }

    public function result(Request $request)
    {
        if ($err = $this->checkToken($request)) {
            return response()->json(['status' => 'error', 'message' => $err], $err === 'Unauthorized.' ? 401 : 503);
        }

        $request->validate([
            'job_id' => 'required|integer',
            'item_id' => 'required|integer',
            'outcome' => 'required|in:bridged,abandoned,machine,failed',
            'agent_ext' => 'nullable|string|max:20',
            'amd' => 'nullable|string|max:20',
            'note' => 'nullable|string|max:200',
        ]);

        $item = DialQueueItem::where('id', $request->item_id)
            ->where('job_id', $request->job_id)
            ->first();
        if (!$item) {
            return response()->json(['status' => 'error', 'message' => 'Item tidak ditemukan.'], 404);
        }

        $update = [];
        if ($request->filled('amd')) {
            $update['amd_verdict'] = strtoupper((string) $request->input('amd'));
        }

        switch ($request->outcome) {
            case 'bridged':
                $update['bridged_agent'] = $request->input('agent_ext') ?: $item->agent_extension;
                $update['answered_at'] = $item->answered_at ?: now();
                // Status tetap dialing — reconcile menandai done saat call selesai.
                break;
            case 'abandoned':
                $update['status'] = 'failed';
                $update['note'] = 'Abandoned: customer angkat tapi tidak ada agent (' . ($request->input('note') ?? '') . ')';
                break;
            case 'machine':
                $update['status'] = 'failed';
                $update['note'] = 'AMD: voicemail/mesin penjawab';
                break;
            case 'failed':
                $update['status'] = 'failed';
                $update['note'] = substr((string) $request->input('note', 'Dialplan gagal'), 0, 200);
                break;
        }

        $item->update($update);

        return response()->json(['status' => 'success']);
    }
}
