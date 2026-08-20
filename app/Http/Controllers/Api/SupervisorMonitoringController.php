<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Agent;
use App\Models\Cdr;
use App\Services\Asterisk\OriginateService;
use App\Services\Asterisk\ProvisionerService;

class SupervisorMonitoringController extends Controller
{
    protected $originateService;
    protected $provisioner; // <--- Deklarasikan properti di sini

    // Inject kedua service (Originate & Provisioner) ke dalam construct
    public function __construct(OriginateService $originateService, ProvisionerService $provisioner)
    {
        $this->originateService = $originateService;
        $this->provisioner = $provisioner;
    }

    /**
     * List semua agen dan statusnya untuk Dashboard Supervisor
     */
    public function agentsList()
    {
        $agents = Agent::all();

        // Hitung statistik ringkas di atas dashboard (Online, Offline, dll)
        $stats = [
            'total'   => $agents->count(),
            'online'  => $agents->where('status', 'online')->count(),
            'break'   => $agents->where('status', 'break')->count(),
            'offline' => $agents->where('status', 'offline')->count(),
        ];

        return response()->json([
            'status' => 'success',
            'stats'  => $stats,
            'agents' => $agents
        ]);
    }

    /**
     * Tombol Supervisor Action (Listen / Whisper / Barge)
     */
    public function spyAction(Request $request)
    {
        $request->validate([
            'supervisor_ext'  => 'required|string', // Ekstensi supervisor (misal: 201)
            'target_channel'  => 'required|string', // Channel agen yang mau di-spy (misal: PJSIP/101)
            'mode'            => 'nullable|in:w,B'  // Kosong = Listen, 'w' = Whisper, 'B' = Barge
        ]);

        try {
            $mode = $request->mode ?? '';
            $response = $this->originateService->supervisorAction(
                $request->supervisor_ext, 
                $request->target_channel, 
                $mode
            );

            return response()->json([
                'status' => 'success',
                'message' => "Aksi supervisor berhasil dikirim ke channel {$request->target_channel}",
                'asterisk_response' => trim($response)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function callLogs()
    {
        // Ambil 50 riwayat panggilan terakhir
        $logs = Cdr::orderBy('calldate', 'desc')
                    ->limit(50)
                    ->get(['calldate', 'src', 'dst', 'duration', 'disposition', 'recordingfile']);

        // Koreksi tampilan data jika terjadi "self-call" akibat Click-to-Dial
        $formattedLogs = $logs->map(function ($log) {
            if ($log->src === $log->dst && strlen($log->src) > 5) {
                $log->src = 'Ext 101 / Agent'; 
            }
            return $log;
        });

        return response()->json([
            'status' => 'success',
            'data' => $formattedLogs
        ]);
    }

    public function playRecording(Request $request)
    {
        $filename = $request->query('file');
        
        if (!$filename) {
            return response()->json(['error' => 'No filename provided'], 400);
        }

        preg_match('/-(\d{4})(\d{2})(\d{2})-/', $filename, $matches);
        
        if (count($matches) == 4) {
            $year = $matches[1];
            $month = $matches[2];
            $day = $matches[3];
            $publicAudioUrl = "http://192.168.99.73/monitor/{$year}/{$month}/{$day}/{$filename}";
        } else {
            $publicAudioUrl = "http://192.168.99.73/monitor/{$filename}";
        }

        return redirect($publicAudioUrl);
    }

    /**
     * Membuat Agent Baru & Sinkronisasi ke FreePBX
     */
    public function createAgent(Request $request)
    {
        // 1. Validasi Input dari form SPV
        $request->validate([
            'extension' => 'required|numeric|unique:agents,extension',
            'name'      => 'required|string|max:255',
        ]);

        try {
            // 2. Generate secret SIP yang kuat (bukan buatan user)
            $sipSecret = Str::random(12);

            // 3. Simpan data Agent ke Database Laravel
            $agent = new Agent();
            $agent->extension = $request->extension;
            $agent->name      = $request->name;
            $agent->secret    = $sipSecret;
            $agent->status    = 'offline';
            $agent->save();

            // 4. Tembak Server Asterisk (SFTP & SSH Bulk Import)
            $output = $this->provisioner->provision($agent);

            return response()->json([
                'status'  => 'success',
                'message' => "Agent {$agent->name} (Ext: {$agent->extension}) berhasil dibuat dan disinkronisasi ke PABX.",
                'data'    => [
                    'extension' => $agent->extension,
                    'secret'    => $agent->secret 
                ],
                'asterisk_log' => trim($output)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal membuat agent: ' . $e->getMessage()
            ], 500);
        }
    }  

    public function updateStatus(Request $request, $extension)
{
    $request->validate([
        'status' => 'required|in:online,prayer,break,lunch,offline'
    ]);

    $agent = Agent::where('extension', $extension)->firstOrFail();
    $agent->status = $request->status;
    $agent->save();

    // Broadcast perubahan status agar langsung terlihat di Dashboard Supervisor
    broadcast(new \App\Events\AgentStatusUpdated($agent));

    return response()->json([
        'status' => 'success',
        'message' => "Status berhasil diubah menjadi {$agent->status}"
    ]);
}
}