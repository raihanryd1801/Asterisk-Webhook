<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\DialJob;
use App\Models\PdsRotation;
use App\Services\Asterisk\PdsDialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DialerController extends Controller
{
    protected function getCurrentUserId()
    {
        // created_by merujuk ke tabel users -> hanya isi ID user asli.
        // Supervisor login via session agent tidak punya users.id, jadi null
        // (sebelumnya mengisi ID agent -> FK violation 1452).
        if (Auth::check()) {
            return Auth::id();
        }
        return null;
    }

    public function index(Request $request, PdsDialService $pds)
    {
        $jobs = DialJob::latest()->paginate(10)->withQueryString();

        $jobs->getCollection()->transform(function ($job) {
            $job->progress_data = $job->progress();
            return $job;
        });

        $rotation = PdsRotation::with('agent')->orderBy('joined_at')->get();
        $buckets = ['Bucket 1', 'Bucket 2', 'Bucket 3', 'NPL'];

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($jobs);
        }

        return view('crm.dialer.index', compact('jobs', 'rotation', 'buckets'));
    }

    public function store(Request $request, PdsDialService $pds)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'buckets' => 'required|array|min:1',
            'buckets.*' => 'nullable|integer|min:0|max:5000',
            'lines_per_agent' => 'required|integer|min:1|max:5',
            'max_attempts' => 'required|integer|min:1|max:10',
            'note' => 'nullable|string|max:2000',
        ]);

        $config = [];
        foreach ($request->buckets as $bucket => $limit) {
            $limit = (int) $limit;
            if ($limit > 0) {
                $config[$bucket] = $limit;
            }
        }

        if (empty($config)) {
            return response()->json(['status' => 'error', 'message' => 'Isi minimal 1 kuota bucket lebih dari 0.'], 422);
        }

        $job = DialJob::create([
            'name' => $request->name,
            'buckets_config' => $config,
            'lines_per_agent' => $request->lines_per_agent,
            'max_attempts' => $request->max_attempts,
            'status' => 'draft',
            'note' => $request->note,
            'created_by' => $this->getCurrentUserId(),
        ]);

        $built = $pds->buildQueue($job);
        $inserted = $built['inserted'];
        $sk = $built['skipped'];

        $detail = [];
        foreach ($built['per_bucket'] as $bucket => $count) {
            $detail[] = "{$bucket}: {$count}";
        }
        $message = "Job dibuat dengan {$inserted} nomor (" . implode(', ', $detail) . ").";
        $skipInfo = [];
        if ($sk['paid'] > 0) {
            $skipInfo[] = "{$sk['paid']} lunas (diskip)";
        }
        if ($sk['handed_over'] > 0) {
            $skipInfo[] = "{$sk['handed_over']} sudah handover (diskip)";
        }
        if ($sk['no_phone'] > 0) {
            $skipInfo[] = "{$sk['no_phone']} tanpa nomor (diskip)";
        }
        if ($skipInfo) {
            $message .= ' Skip: ' . implode(', ', $skipInfo) . '.';
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'job' => $job->loadCount([]),
        ]);
    }

    public function show(DialJob $job)
    {
        $items = $job->items()->with('customer:id,name')->latest()->paginate(20);

        return response()->json([
            'status' => 'success',
            'job' => $job,
            'progress' => $job->progress(),
            'items' => $items,
        ]);
    }

    public function start(DialJob $job, PdsDialService $pds)
    {
        // GATE ROTASI: tanpa 1 pun agent join, PDS tidak boleh jalan.
        // (Mencegah customer diangkat tapi tidak ada agent.)
        if ($pds->rotationCount() === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'PDS tidak bisa dijalankan: belum ada agent yang JOIN rotation. Minta minimal 1 agent join dari Workspace.',
            ], 422);
        }

        if (!in_array($job->status, ['draft', 'paused', 'stopped'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Job sudah berjalan/selesai.'], 422);
        }

        // Reset item dialing yang menggantung jadi queued lagi
        $job->items()->where('status', 'dialing')->update(['status' => 'queued', 'agent_extension' => null]);

        $job->update(['status' => 'running', 'started_at' => now(), 'finished_at' => null]);

        return response()->json([
            'status' => 'success',
            'message' => "Job '{$job->name}' RUNNING. Pastikan daemon pds:work jalan di server.",
        ]);
    }

    public function pause(DialJob $job)
    {
        if ($job->status !== 'running') {
            return response()->json(['status' => 'error', 'message' => 'Hanya job running yang bisa di-pause.'], 422);
        }
        $job->update(['status' => 'paused']);

        return response()->json(['status' => 'success', 'message' => 'Job di-pause. Item dialing dibiarkan selesai oleh agent.']);
    }

    public function stop(DialJob $job)
    {
        if (!in_array($job->status, ['running', 'paused'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Job tidak sedang berjalan.'], 422);
        }

        $job->items()->where('status', 'dialing')->update(['status' => 'queued', 'agent_extension' => null]);
        $job->update(['status' => 'stopped', 'finished_at' => now()]);

        return response()->json(['status' => 'success', 'message' => 'Job dihentikan.']);
    }

    public function repeat(DialJob $job)
    {
        if (!in_array($job->status, ['completed', 'stopped'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Hanya job completed/stopped yang bisa diulangi.'], 422);
        }

        // Reset antrian jadi fresh lagi (attempt di-nol-kan)
        $job->items()->update([
            'status' => 'queued',
            'attempts' => 0,
            'agent_extension' => null,
            'last_attempt_at' => null,
            'note' => null,
        ]);

        $job->update(['status' => 'draft', 'started_at' => null, 'finished_at' => null]);

        return response()->json([
            'status' => 'success',
            'message' => "Job '{$job->name}' di-reset ke draft. Tekan Start untuk menjalankan ulang.",
        ]);
    }

    public function destroy(DialJob $job)
    {
        if ($job->status === 'running') {
            return response()->json(['status' => 'error', 'message' => 'Stop dulu job yang running sebelum dihapus.'], 422);
        }
        $job->delete();

        return response()->json(['status' => 'success', 'message' => 'Job dihapus beserta antriannya.']);
    }

    public function rotationList()
    {
        $rotation = PdsRotation::with('agent:id,name,extension,status')->orderBy('joined_at')->get();

        return response()->json(['status' => 'success', 'data' => $rotation]);
    }

    // ============ ROTATION MILIK AGENT (session agent, di luar premium gate) ============

    protected function currentAgent()
    {
        $ext = session('agent_extension');
        if (!$ext) {
            return null;
        }
        return Agent::where('extension', $ext)->first();
    }

    public function rotationStatus()
    {
        $agent = $this->currentAgent();
        if (!$agent) {
            return response()->json(['status' => 'error', 'message' => 'Silakan login agent dulu.'], 401);
        }

        $joined = PdsRotation::where('agent_id', $agent->id)->exists();

        return response()->json([
            'status' => 'success',
            'joined' => $joined,
            'agent_status' => $agent->status,
        ]);
    }

    public function rotationJoin()
    {
        $agent = $this->currentAgent();
        if (!$agent) {
            return response()->json(['status' => 'error', 'message' => 'Silakan login agent dulu.'], 401);
        }

        // Join = consent: siap menerima panggilan otomatis PDS (pastikan
        // auto-answer aktif di MicroSIP agar "otomatis ngangkat").
        PdsRotation::updateOrCreate(
            ['agent_id' => $agent->id],
            ['extension' => $agent->extension, 'joined_at' => now()]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Anda JOIN rotation PDS. Pastikan status Online & auto-answer MicroSIP aktif.',
        ]);
    }

    public function rotationLeave()
    {
        $agent = $this->currentAgent();
        if (!$agent) {
            return response()->json(['status' => 'error', 'message' => 'Silakan login agent dulu.'], 401);
        }

        PdsRotation::where('agent_id', $agent->id)->delete();

        return response()->json(['status' => 'success', 'message' => 'Anda keluar dari rotation (kembali Manual Call).']);
    }
}
