<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Agent;
use App\Models\Cdr;
use App\Services\Asterisk\OriginateService;
use App\Services\Asterisk\ProvisionerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Exports\CallLogsExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Jobs\ProvisionAsteriskAgent;
use Illuminate\Support\Facades\DB;

class SupervisorMonitoringController extends Controller
{
    protected $originateService;
    protected $provisioner; 

    public function __construct(OriginateService $originateService, ProvisionerService $provisioner)
    {
        $this->originateService = $originateService;
        $this->provisioner = $provisioner;
    }

    public function agentsList()
    {
        try {
            $rawAgents = collect(); 

            // 1. Ambil data agent berdasarkan hak akses session/auth
            if (auth()->check()) {
                $rawAgents = Agent::all(); 
            } 
            elseif (session()->has('supervisor_extension')) {
                $spvExt = session('supervisor_extension');
                $spv = Agent::where('extension', $spvExt)->first();
                
                if ($spv) {
                    $rawAgents = Agent::where('supervisor_id', $spv->id)
                                    ->orWhere('id', $spv->id) 
                                    ->get();
                }
            } 
            else {
                $rawAgents = Agent::all();
            }

            // 2. Mapping data ringan
            $agents = $rawAgents->map(function ($agent) {
                $callState = Cache::get('active_call_' . $agent->extension);

                $data = $agent->toArray();
                
                $data['microsip_online']     = ($agent->status === 'online');
                $data['ami_device_state']    = $agent->status === 'online' ? 'NOT_INUSE' : 'UNAVAILABLE'; 
                $data['is_calling']          = $callState['is_calling'] ?? false;
                $data['call_status']         = $callState['call_status'] ?? null;
                $data['current_destination'] = $callState['destination'] ?? null;
            
                return $data;
            }); 

            // 3. Hitung statistik dashboard
            $stats = [
                'total'   => $agents->count(),
                'online'  => $agents->where('status', 'online')->count(),
                'break'   => $agents->where('status', 'break')->count(),
                'offline' => $agents->where('status', 'offline')->count(),
            ];

            return response()->json([
                'status' => 'success',
                'stats'  => $stats,
                'agents' => $agents->values() 
            ]);

        } catch (\Exception $e) {
            Log::error("Error agentsList: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'stats'  => ['total' => 0, 'online' => 0, 'break' => 0, 'offline' => 0],
                'agents' => []
            ], 200); 
        }
    }

    /**
     * 🚀 FUNGSI CREATE AGENT YANG SEBELUMNYA HILANG
     */
    public function createAgent(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'extension' => 'required|string|unique:agents,extension',
            'secret'    => 'required|string',
        ]);

        try {
            $agent = Agent::create([
                'name'          => $request->name,
                'extension'     => $request->extension,
                'secret'        => $request->secret,
                'status'        => 'offline',
                'supervisor_id' => $request->supervisor_id ?? null,
            ]);

            // Dispatch job provisioning ke Asterisk jika ada
            if (class_exists(ProvisionAsteriskAgent::class)) {
                ProvisionAsteriskAgent::dispatch($agent);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Agent berhasil ditambahkan dan diprovisi.',
                'agent'   => $agent
            ]);

        } catch (\Exception $e) {
            Log::error("Error createAgent: " . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menambah agen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🚀 FUNGSI UPDATE AGENT
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'supervisor_id' => 'nullable|exists:agents,id', 
            'secret'        => 'nullable|string|min:4',
            'role'          => 'required|in:agent,supervisor'
        ]);

        try {
            $agent = Agent::findOrFail($id);
            $oldSecret = $agent->secret;
            
            $agent->name          = $request->name;
            $agent->supervisor_id = $request->supervisor_id;
            $agent->role          = $request->role;

            $secretChanged = false;
            if ($request->filled('secret')) {
                $agent->secret = $request->secret;
                $secretChanged = ($request->secret !== $oldSecret);
            }

            // 1. Simpan perubahan ke database lokal
            $agent->save();

            // 2. Lempar proses update FreePBX ke background queue
            if (class_exists(ProvisionAsteriskAgent::class)) {
                ProvisionAsteriskAgent::dispatch($agent, 'update', $secretChanged);
            }

            return response()->json([
                'status'  => 'success',
                'message' => "Data agen {$agent->name} berhasil diperbarui!"
            ]);

        } catch (\Exception $e) {
            Log::error("Error updateAgent: " . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal memperbarui agen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🚀 FUNGSI DELETE / DESTROY AGENT
     */
    public function destroy($id)
    {
        try {
            $agent = Agent::findOrFail($id);
            
            // Buat snapshot data agent sebelum dihapus dari database lokal
            $agentSnapshot = clone $agent;

            // 1. Hapus dari database lokal secepat kilat
            $agent->delete();

            // 2. Lempar proses pembersihan FreePBX ke background queue
            if (class_exists(ProvisionAsteriskAgent::class)) {
                ProvisionAsteriskAgent::dispatch($agentSnapshot, 'delete');
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Agen berhasil dihapus dari sistem!'
            ]);

        } catch (\Exception $e) {
            Log::error("Error destroyAgent: " . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menghapus agen: ' . $e->getMessage()
            ], 500);
        }
    }

    public function spyAction(Request $request)
{
    $request->validate([
        'target_channel'  => 'required|string', 
        'mode'            => 'nullable|in:w,B',
        'spy_ext'         => 'nullable|string'  
    ]);

    $supervisorExt = null;

    // 1. PRIORITAS UTAMA: Jika ada input ekstensi dari prompt frontend (Untuk Admin)
    if ($request->filled('spy_ext')) {
        $supervisorExt = $request->spy_ext;
    } 
    // 2. Jika yang login adalah Supervisor, ambil langsung dari session
    elseif (session()->has('supervisor_extension')) {
        $supervisorExt = session('supervisor_extension');
    }

    // Jika ekstensi pendengar masih kosong, balas dengan error 400
    // agar JavaScript di frontend memunculkan kotak prompt input ekstensi untuk Admin.
    if (!$supervisorExt) {
        return response()->json([
            'status' => 'error',
            'message' => 'Ekstensi pendengar tidak ditemukan. Silakan masukkan nomor ekstensi softphone Anda untuk mendengarkan.'
        ], 400);
    }

    try {
        $mode = $request->mode ?? '';
        $response = $this->originateService->supervisorAction(
            $supervisorExt, 
            $request->target_channel, 
            $mode
        );

        return response()->json([
            'status' => 'success',
            'message' => "Aksi berhasil! Menghubungkan ke Softphone Anda (Ext: {$supervisorExt})",
            'asterisk_response' => trim($response)
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
}
public function saveNote(Request $request, $uniqueid)
{
    $request->validate([
        'notes' => 'nullable|string'
    ]);

    try {
        $tableName = (new \App\Models\Cdr())->getTable();
        $cleanId = trim($uniqueid);

        // 1. Cek dulu apakah data dengan uniqueid tersebut benar-benar ada di tabel cdr_live
        $record = DB::table($tableName)->where('uniqueid', $cleanId)->first();

        if (!$record) {
            // Coba cari dengan LIKE untuk melihat apakah ada string yang mirip (mengantisipasi ekstensi/karakter tambahan)
            $similar = DB::table($tableName)->where('uniqueid', 'like', "%{$cleanId}%")->first();
            
            $msg = "Uniqueid '{$cleanId}' tidak ditemukan di tabel '{$tableName}'.";
            if ($similar) {
                $msg .= " (Peringatan: Ditemukan uniqueid mirip di DB yaitu '{$similar->uniqueid}')";
            }

            return response()->json([
                'status' => 'error',
                'message' => $msg
            ], 404);
        }

        // 2. Jika ketemu, lakukan update
        DB::table($tableName)
            ->where('uniqueid', $cleanId)
            ->update([
                'notes' => $request->notes
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan berhasil disimpan!'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Database Error: ' . $e->getMessage()
        ], 500);
    }
}

    public function callLogs(Request $request)
{
    $query = Cdr::select([
        'uniqueid','calldate', 'src', 'dst', 'duration', 
        'billsec', 'disposition', 'recordingfile', 'cnam', 'cnum', 'sip_code', 'terminated_by','notes'// <-- Tambahkan 'sip_code' di sini
    ])->orderBy('calldate', 'desc');

    if (session()->has('supervisor_extension')) {
        $spvExt = session('supervisor_extension');
        $spv = Agent::where('extension', $spvExt)->first();

        if ($spv) {
            $managedExtensions = Agent::where('supervisor_id', $spv->id)
                                      ->orWhere('id', $spv->id)
                                      ->pluck('extension')
                                      ->toArray();

            $query->where(function($q) use ($managedExtensions) {
                $q->whereIn('src', $managedExtensions)->orWhereIn('dst', $managedExtensions);
            });
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    if ($request->filled('agent_extension')) {
        $ext = $request->agent_extension;
        $query->where(function($q) use ($ext) {
            $q->where('src', $ext)->orWhere('dst', $ext);
        });
    }
    elseif (session()->has('agent_extension')) {
        $extension = session('agent_extension');
        $query->where(function($q) use ($extension) {
            $q->where('src', $extension)->orWhere('dst', $extension);
        });
    }

    if ($request->filled('search')) {
        $keyword = $request->search;
        $query->where(function($q) use ($keyword) {
            $q->where('src', 'like', "%{$keyword}%")
              ->orWhere('dst', 'like', "%{$keyword}%")
              ->orWhere('cnam', 'like', "%{$keyword}%")
              ->orWhere('cnum', 'like', "%{$keyword}%");
        });
    }

    if (!$request->filled('start_date') && !$request->filled('end_date')) {
        $query->whereDate('calldate', '>=', now()->subDays(7));
    } else {
        if ($request->filled('start_date')) {
            $query->whereDate('calldate', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('calldate', '<=', $request->end_date);
        }
    }

    $perPage = $request->query('per_page', 15);
    $paginatedLogs = $query->simplePaginate($perPage);
    $paginatedLogs->appends($request->except('page'));

    $paginatedLogs->getCollection()->transform(function ($log) {
        if ($log->src === $log->dst && strlen($log->src) > 5) {
            $log->src = 'Ext / Agent'; 
        }
        return $log;
    });

    return response()->json([
        'status' => 'success',
        'data'   => $paginatedLogs
    ]);
}
    public function updateStatus(Request $request, $extension)
    {
        $request->validate([
            'status' => 'required|in:online,prayer,break,lunch,offline'
        ]);

        $agent = Agent::where('extension', $extension)->firstOrFail();
        $agent->status = $request->status;
        $agent->save();

        broadcast(new \App\Events\AgentStatusUpdated($agent));

        return response()->json([
            'status' => 'success',
            'message' => "Status berhasil diubah menjadi {$agent->status}"
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

        try {
            $audioContent = @file_get_contents($publicAudioUrl);
            
            if ($audioContent === false) {
                return response()->json(['error' => 'File rekaman tidak ditemukan di server FreePBX.'], 404);
            }

            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $contentType = $extension === 'mp3' ? 'audio/mpeg' : 'audio/wav';

            return response($audioContent, 200)
                ->header('Content-Type', $contentType)
                ->header('Accept-Ranges', 'bytes');

        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal memuat rekaman: ' . $e->getMessage()], 500);
        }
    }

    public function exportExcel(Request $request)
    {
        $filename = 'call-history-' . date('Y-m-d_H-i-s') . '.xlsx';
        return Excel::download(new CallLogsExport($request), $filename);
    }

    public function agentClickToCall(Request $request)
    {
        $request->validate([
            'extension'   => 'required',
            'destination' => 'required'
        ]);

        $extension = $request->extension;
        $destination = $request->destination;

        $agent = Agent::where('extension', $extension)->first();

        if (!$agent || $agent->status !== 'online') {
            return response()->json([
                'status' => 'error',
                'message' => 'Panggilan ditolak! Status Anda saat ini bukan Online.'
            ], 403);
        }

        try {
            $isMicroSIPOnline = \DB::table('ps_contacts')
                ->where('endpoint', $extension)
                ->exists();

            if (!$isMicroSIPOnline) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Panggilan ditolak! Aplikasi MicroSIP Ext ' . $extension . ' sedang Offline / Belum Terdaftar.'
                ], 403);
            }
        } catch (\Exception $e) {
            // Lewati jika tabel ps_contacts beda database
        }

        try {
            $response = $this->originateService->clickToDial($extension, $destination);

            return response()->json([
                'status' => 'success',
                'message' => 'MicroSIP Ext ' . $extension . ' berdering, menghubungkan ke ' . $destination . '...',
                'response' => trim($response)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal Memanggil: ' . $e->getMessage()
            ], 500);
        }
    }
}