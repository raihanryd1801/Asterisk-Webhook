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
use App\Exports\CallLogsExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Jobs\ProvisionAsteriskAgent;

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
        $rawAgents = collect(); 

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

        $agents = $rawAgents->map(function ($agent) {
            $callState = Cache::get('active_call_' . $agent->extension);

            $data = $agent->toArray();
            $data['is_calling']          = $callState['is_calling'] ?? false;
            $data['call_status']         = $callState['call_status'] ?? null;
            $data['current_destination'] = $callState['destination'] ?? null;

            return $data;
        });

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
    }

    public function spyAction(Request $request)
    {
        $request->validate([
            'target_channel'  => 'required|string', 
            'mode'            => 'nullable|in:w,B'  
        ]);

        $supervisorExt = null;

        if (session()->has('supervisor_extension')) {
            $supervisorExt = session('supervisor_extension');
        } 
        elseif (auth()->check()) {
            $agentExt = str_replace('PJSIP/', '', $request->target_channel);
            $agent = Agent::where('extension', $agentExt)->first();

            if ($agent && $agent->supervisor_id) {
                $spvUser = Agent::find($agent->supervisor_id);
                if ($spvUser) {
                    $supervisorExt = $spvUser->extension;
                }
            }

            if (!$supervisorExt) {
                $fallbackSpv = Agent::where('role', 'supervisor')->first();
                $supervisorExt = $fallbackSpv ? $fallbackSpv->extension : '201'; 
            }
        }

        if (!$supervisorExt) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ekstensi tujuan Supervisor (MicroSIP) tidak ditemukan.'
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
                'message' => "Aksi berhasil! Menghubungkan ke MicroSIP SPV (Ext: {$supervisorExt})",
                'asterisk_response' => trim($response)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function callLogs(Request $request)
    {
        // 1. Inisialisasi Query dasar (tanpa get() atau all())
        $query = \App\Models\Cdr::orderBy('calldate', 'desc');

        // 2. Filter hak akses Supervisor/Agent (dieksekusi oleh MySQL)
        if (session()->has('supervisor_extension')) {
            $spvExt = session('supervisor_extension');
            $spv = \App\Models\Agent::where('extension', $spvExt)->first();

            if ($spv) {
                $managedExtensions = \App\Models\Agent::where('supervisor_id', $spv->id)
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

        // Filter jika dipilih via dropdown (oleh Supervisor/Admin)
     if ($request->filled('agent_extension')) {
         $ext = $request->agent_extension;
         $query->where(function($q) use ($ext) {
             $q->where('src', $ext)->orWhere('dst', $ext);
         });
     }

     // Filter jika yang login murni Agent (berdasarkan session)
     elseif (session()->has('agent_extension')) {
         $extension = session('agent_extension');
         $query->where(function($q) use ($extension) {
             $q->where('src', $extension)->orWhere('dst', $extension);
         });
     }

        // 3. Filter berdasarkan pencarian keyword (DIPROSES DI MYSQL)
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function($q) use ($keyword) {
                $q->where('src', 'like', "%{$keyword}%")
                  ->orWhere('dst', 'like', "%{$keyword}%")
                  ->orWhere('cnam', 'like', "%{$keyword}%")
                  ->orWhere('cnum', 'like', "%{$keyword}%");
            });
        }

        // 4. Filter berdasarkan rentang tanggal (DIPROSES DI MYSQL)
        // Jika user tidak memilih tanggal, default tampilkan 7 hari terakhir agar ringan
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

        // 5. Eksekusi Pagination (Misal 15 data per halaman)
        $perPage = $request->query('per_page', 15);
        $paginatedLogs = $query->paginate($perPage);

        // Transform data ringan jika diperlukan
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

    /**
     * Aksi Click-to-Call dari Agent Workspace ke Nomor Tujuan
     */
    public function agentClickToCall(Request $request)
    {
        $request->validate([
            'extension'   => 'required',
            'destination' => 'required'
        ]);

        $extension = $request->extension;
        $destination = $request->destination;

        // 1. Cek status di tabel agents (Tombol Web)
        $agent = Agent::where('extension', $extension)->first();

        if (!$agent || $agent->status !== 'online') {
            return response()->json([
                'status' => 'error',
                'message' => 'Panggilan ditolak! Status Anda saat ini bukan Online.'
            ], 403);
        }

        // 2. CEK REGISTRASI PJSIP DENGAN AMAN (Mencegah Error 500 jika beda database)
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
            // Jika tabel ps_contacts tidak ada di database default, 
            // lewati pengecekan ini agar tidak memicu error 500
        }

        try {
            // 3. Jalankan perintah Click-to-Dial via service
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