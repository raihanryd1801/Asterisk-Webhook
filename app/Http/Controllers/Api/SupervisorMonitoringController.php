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
use phpseclib3\Net\SSH2; // 🚀 WAJIB DITAMBAHKAN UNTUK TAKEOVER
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Jobs\ProcessCallLogExport;
use Illuminate\Support\Facades\Artisan;

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
                    // 🚀 Ubah dari supervisor_id menjadi relasi Many-to-Many pivot
                    $managedIds = $spv->agents()->pluck('agents.id')->toArray();
                    $managedIds[] = $spv->id; // Masukkan ID SPV itu sendiri agar ikut tampil

                    $rawAgents = Agent::whereIn('id', $managedIds)->get();
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

    public function createAgent(Request $request)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'extension'        => 'required|string|unique:agents,extension',
            'secret'           => 'required|string',
            'supervisor_ids'   => 'nullable|array', // 🚀 Validasi array multi-SPV
            'supervisor_ids.*' => 'exists:agents,id'
        ]);

        try {
            $agent = Agent::create([
                'name'      => $request->name,
                'extension' => $request->extension,
                'secret'    => $request->secret,
                'status'    => 'offline',
                // Hapus supervisor_id karena sudah dipindah ke tabel pivot
            ]);

            // 🚀 Sinkronisasi Multiple Supervisor ke tabel pivot
            if ($request->has('supervisor_ids')) {
                $agent->supervisors()->sync($request->supervisor_ids);
            }

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

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'supervisor_ids'   => 'nullable|array', // 🚀 Validasi array multi-SPV
            'supervisor_ids.*' => 'exists:agents,id',
            'secret'           => 'nullable|string|min:4',
            'role'             => 'required|in:agent,supervisor'
        ]);

        try {
            $agent = Agent::findOrFail($id);
            $oldSecret = $agent->secret;
            
            $agent->name = $request->name;
            $agent->role = $request->role;
            // Hapus assignment supervisor_id

            $secretChanged = false;
            if ($request->filled('secret')) {
                $agent->secret = $request->secret;
                $secretChanged = ($request->secret !== $oldSecret);
            }

            $agent->save();

            // 🚀 Sinkronisasi Multiple Supervisor ke tabel pivot
            if ($request->has('supervisor_ids')) {
                $agent->supervisors()->sync($request->supervisor_ids);
            } else {
                $agent->supervisors()->detach();
            }

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

    public function destroy($id)
    {
        try {
            $agent = Agent::findOrFail($id);
            $agentSnapshot = clone $agent;
            
            $agent->delete();

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

        if ($request->filled('spy_ext')) {
            $supervisorExt = $request->spy_ext;
        } 
        elseif (session()->has('supervisor_extension')) {
            $supervisorExt = session('supervisor_extension');
        }

        if (!$supervisorExt) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ekstensi pendengar tidak ditemukan. Silakan masukkan nomor ekstensi softphone Anda.'
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

            $record = DB::table($tableName)->where('uniqueid', $cleanId)->first();

            if (!$record) {
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
            'billsec', 'disposition', 'recordingfile', 'cnam', 'cnum', 'sip_code', 'terminated_by','notes'
        ])->orderBy('calldate', 'desc');

        if (session()->has('supervisor_extension')) {
            $spvExt = session('supervisor_extension');
            $spv = Agent::where('extension', $spvExt)->first();

            if ($spv) {
                // 🚀 Tarik semua ekstensi agen bawahan via relasi Many-to-Many
                $managedExtensions = $spv->agents()
                                        ->pluck('extension')
                                        ->merge([$spv->extension])
                                        ->unique()
                                        ->toArray();

                $query->where(function($q) use ($managedExtensions) {
                    $q->whereIn('src', $managedExtensions)->orWhereIn('dst', $managedExtensions);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // ... (sisa filter pencarian & pagination callLogs tetap sama) ...

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
            $publicAudioUrl = "http://172.16.1.24/monitor/{$year}/{$month}/{$day}/{$filename}";
        } else {
            $publicAudioUrl = "http://172.16.1.24/monitor/{$filename}";
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
        // 🚀 Tangkap format dari frontend (.xlsx atau .csv)
        $format = $request->query('format', 'xlsx');
        if (!in_array($format, ['xlsx', 'csv'])) {
            $format = 'xlsx';
        }

        // Terapkan format ke nama file
        $filename = 'call-history-' . date('Y-m-d_H-i-s') . '.' . $format;
        $filePath = 'exports/' . $filename;

        $filters = $request->only(['agent_extension', 'search', 'start_date', 'end_date']);
        $filters['supervisor_extension'] = session('supervisor_extension');

        // Lempar ke Job FastExcel
        ProcessCallLogExport::dispatch($filters, $filePath);

        return response()->json([
            'status' => 'processing',
            'filename' => $filename
        ]);
    }

   public function checkExportStatus(\Illuminate\Http\Request $request)
    {
        $filename = $request->query('filename');
        if (!$filename) {
            return response()->json(['ready' => false]);
        }

        $path = 'exports/' . $filename;
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        // 🚀 GEMBOK GANDA: File harus wujud DAN ukurannya lebih dari 0 bytes
        if ($disk->exists($path) && $disk->size($path) > 0) {
            return response()->json([
                'ready' => true,
                'url' => asset('storage/' . $path)
            ]);
        }

        return response()->json(['ready' => false]);
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

    /**
     * 🚀 FUNGSI BARU: TAKEOVER (MERAMPAS PANGGILAN) 🚀
     */
    /**
     * 🚀 FUNGSI BARU: TAKEOVER (MERAMPAS PANGGILAN) 🚀
     */
    /**
     * 🚀 FUNGSI BARU: TAKEOVER (MERAMPAS PANGGILAN) 🚀
     */
    public function takeoverAction(Request $request)
    {
        $request->validate([
            'target_channel'  => 'required|string', 
            'spy_ext'         => 'nullable|string'  
        ]);

        $supervisorExt = null;

        if ($request->filled('spy_ext')) {
            $supervisorExt = $request->spy_ext;
        } elseif (session()->has('supervisor_extension')) {
            $supervisorExt = session('supervisor_extension');
        }

        if (!$supervisorExt) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ekstensi supervisor tidak ditemukan. Silakan masukkan ekstensi softphone Anda.'
            ], 400);
        }

        try {
            // Tarik kredensial dengan aman dari konfigurasi
            $host = config('services.freepbx.host'); 
            $user = config('services.freepbx.user');
            $pass = config('services.freepbx.pass');

            // Proteksi jika lupa set password di .env
            if (empty($pass)) {
                throw new \Exception("Password SSH FreePBX belum dikonfigurasi di file .env server.");
            }

            $ssh = new SSH2($host);
            if (!$ssh->login($user, $pass)) {
                throw new \Exception("Gagal login SSH ke server Asterisk.");
            }
            
            // ... (lanjut ke proses ekstrak PJSIP) ...

            // 🚀 1. Ekstrak nomor ekstensi murni
            $agentExt = str_replace('PJSIP/', '', $request->target_channel);

            // 🚀 2. Cari NAMA EXACT channel agen yang sedang aktif
            $getAgentChannelCmd = "asterisk -rx 'core show channels' | grep -m 1 -o 'PJSIP/{$agentExt}-[a-zA-Z0-9]*'";
            $exactAgentChannel = trim($ssh->exec($getAgentChannelCmd));

            if (empty($exactAgentChannel)) {
                throw new \Exception("Channel PJSIP untuk agen {$agentExt} tidak terdeteksi aktif di Asterisk.");
            }

            // 🚀 3. Dapatkan Bridge ID (Karena Asterisk modern menggunakan Bridge ID)
            $getBridgeIdCmd = "asterisk -rx 'core show channel {$exactAgentChannel}' | grep 'Bridge ID:' | awk '{print \$3}'";
            $bridgeId = trim($ssh->exec($getBridgeIdCmd));

            $bridgedChannel = "";

            if (!empty($bridgeId)) {
                // 🚀 4. Jika Bridge ID ketemu, cari channel milik Customer di ruangan yang sama
                // (Mencari semua channel di dalam bridge, lalu membuang channel milik agen)
                $getPeerCmd = "asterisk -rx 'bridge show {$bridgeId}' | grep 'Channel:' | awk '{print \$2}' | grep -v '^{$exactAgentChannel}$' | head -n 1";
                $bridgedChannel = trim($ssh->exec($getPeerCmd));
            } else {
                // Fallback darurat jika sistem tidak merespons Bridge ID
                $fallbackCmd = "asterisk -rx 'core show channelvar {$exactAgentChannel} BRIDGEPEER'";
                $bridgePeer = trim($ssh->exec($fallbackCmd));
                if (strpos($bridgePeer, 'BRIDGEPEER=') !== false) {
                    $bridgedChannel = str_replace('BRIDGEPEER=', '', $bridgePeer);
                }
            }

            if (empty($bridgedChannel)) {
                throw new \Exception("Gagal menemukan channel lawan (Customer) di dalam sistem.");
            }

            // 🚀 5. Eksekusi Redirect / Takeover!
            // Lempar channel lawan ke softphone Supervisor
            $spvExt = escapeshellarg($supervisorExt);
            //Merekam TakeOver
            $redirectCmd = "asterisk -rx 'channel redirect {$bridgedChannel} custom-takeover,{$spvExt},1'";
            $ssh->exec($redirectCmd);

            return response()->json([
                'status' => 'success',
                'message' => "Berhasil Takeover! Customer dialihkan ke Softphone Anda (Ext: {$supervisorExt})."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal Takeover: ' . $e->getMessage()
            ], 500);
        }
    }
    public function syncCdr()
{
    try {
        // Menjalankan perintah artisan cdr:sync
        \Illuminate\Support\Facades\Artisan::call('cdr:sync');

        return response()->json([
            'status' => 'success',
            'message' => 'CDR synced successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
}
}