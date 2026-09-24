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
use App\Exports\CallRecordingsZipExport;

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
                // Penanda call dari PDS Auto-Dialer (di-set worker saat originate)
                $data['pds']                 = Cache::get('pds_call_' . $agent->extension);
            
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
            if ($request->filled('supervisor_ids')) {
                // Pastikan formatnya selalu array (jika frontend mengirim string tunggal atau null)
                $spvIds = is_array($request->supervisor_ids) 
                          ? $request->supervisor_ids 
                          : [$request->supervisor_ids];
                
                $agent->supervisors()->sync($spvIds);
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
    // Single-flight per sesi: bila request sebelumnya masih jalan (user klik
    // berkali-kali / double submit), tolak dengan 429 agar query berat tidak
    // menumpuk di MySQL. Frontend menampilkan toast "tunggu sebentar".
    $flightKey = 'calllogs_flight_' . (session()->getId() ?: $request->ip());
    $flight = \Illuminate\Support\Facades\Cache::lock($flightKey, 120);
    try {
        $acquired = $flight->get();
    } catch (\Throwable $e) {
        $acquired = false;
    }
    if (!$acquired) {
        return response()->json([
            'status' => 'error',
            'message' => 'Permintaan sebelumnya masih diproses. Tunggu sebentar.',
        ], 429);
    }

    try {
    $query = Cdr::select([
        'uniqueid','calldate', 'src', 'dst', 'duration', 
        'billsec', 'disposition', 'recordingfile', 'cnam', 'cnum', 'sip_code', 'terminated_by','notes'
    ]);

    // Kumpulkan kondisi extension sebagai SET (src∈S ∨ dst∈S). Bila hanya ada
    // SATU set dan tanpa LIKE-search, OR dipecah jadi 2 cabang disjoint yang
    // masing-masing index-friendly (tanpa filesort ratusan ribu baris):
    //   A: src ∈ S   |   B: dst ∈ S ∧ src ∉ S
    // (LIKE-search / multi-set tetap jalur single-query lama.)
    $extSets = [];
    $noRows = false;
    $likeSearch = false;

    if (session()->has('supervisor_extension')) {
        $spv = Agent::where('extension', session('supervisor_extension'))->first();
        if ($spv) {
            $extSets[] = $spv->agents()->pluck('extension')->merge([$spv->extension])->unique()->values()->toArray();
        } else {
            $noRows = true;
        }
    } elseif (session()->has('agent_extension')) {
        $extSets[] = [session('agent_extension')];
    }

    if ($request->filled('agent_extension')) {
        $extSets[] = [$request->agent_extension];
    }

    // Sederhanakan set: identik -> satu; bila ada singleton {e} yang termuat
    // di set besar S, maka S redundan ((src=e∨dst=e) ∧ (src∈S∨dst∈S) ≡ src=e∨dst=e).
    // Hasil umum: SPV + filter agen anggotanya = 1 set → jalur UNION cepat.
    $uniqSets = [];
    foreach ($extSets as $s) {
        $s = array_values(array_unique($s));
        $uniqSets[json_encode($s)] = $s;
    }
    $extSets = array_values($uniqSets);
    $singles = [];
    foreach ($extSets as $s) {
        if (count($s) === 1) {
            $singles[] = $s[0];
        }
    }
    if (!empty($singles)) {
        $extSets = array_values(array_filter($extSets, function ($s) use ($singles) {
            if (count($s) === 1) {
                return true;
            }
            foreach ($singles as $e) {
                if (in_array($e, $s, true)) {
                    return false;
                }
            }
            return true;
        }));
    }

    $keyword = null;
    if ($request->filled('search')) {
        $keyword = trim((string) $request->search);
        $digits = preg_replace('/\D/', '', $keyword);
        if ($digits !== '' && strlen($digits) >= 6 && preg_match('/^[\d\s\+\-\(\)]+$/', $keyword)) {
            // Pencarian nomor: exact match (pakai index src/dst = milidetik).
            // Dicoba juga varian 08xx <-> 62xx agar format beda tetap ketemu.
            // LIKE '%...%' lama dihapus untuk kasus ini karena memaksa full
            // table scan (20 menit di jutaan baris).
            $variants = array_values(array_unique(array_filter([$keyword, $digits])));
            if (str_starts_with($digits, '62')) {
                $variants[] = '0' . substr($digits, 2);
            } elseif (str_starts_with($digits, '0')) {
                $variants[] = '62' . substr($digits, 1);
            }
            $extSets[] = $variants;
        } else {
            $likeSearch = true;
        }
    }

    // Filter Tanggal
    if ($request->filled('start_date')) {
        $query->whereDate('calldate', '>=', $request->start_date);
    }
    if ($request->filled('end_date')) {
        $query->whereDate('calldate', '<=', $request->end_date);
    }

    // LIKE-search (nama/teks pendek) tetap ditempel di query apa pun jalurnya
    if ($likeSearch && $keyword !== null) {
        $query->where(function($q) use ($keyword) {
            $q->where('src', 'like', "%{$keyword}%")
              ->orWhere('dst', 'like', "%{$keyword}%")
              ->orWhere('cnam', 'like', "%{$keyword}%")
              ->orWhere('cnum', 'like', "%{$keyword}%");
        });
    }

    // Tentukan Sorting (kolom + arah; arah bisa dibalik untuk trik ambil-dari-ujung)
    $sort = $request->query('sort', 'oldest');
    switch ($sort) {
        case 'oldest': $sortCol = 'calldate'; $sortDir = 'asc'; break;
        case 'longest': $sortCol = 'billsec'; $sortDir = 'desc'; break;
        case 'shortest': $sortCol = 'billsec'; $sortDir = 'asc'; break;
        case 'newest':
        default: $sortCol = 'calldate'; $sortDir = 'desc'; break;
    }

    $perPage = $request->query('per_page', 15);
    $page = max(1, (int) $request->query('page', 1));
    $offset = ($page - 1) * $perPage;

    // 1. Total di-cache 60 detik per kombinasi filter.
    $countKey = 'calllogs_count_' . md5(json_encode([
        'spv' => session('supervisor_extension'),
        'agent' => session('agent_extension'),
        'agent_extension' => $request->input('agent_extension'),
        'search' => $request->input('search'),
        'start_date' => $request->input('start_date'),
        'end_date' => $request->input('end_date'),
    ]));

    // Jalur UNION: tepat 1 ext-set tanpa LIKE → 2 cabang disjoint index-murni.
    $useUnion = !$noRows && !$likeSearch && count($extSets) === 1;

    if ($noRows) {
        $total = 0;
        $logsCollection = collect();
    } elseif ($useUnion) {
        $set = array_values(array_unique($extSets[0]));
        $branchA = function () use ($query, $set) {
            return (clone $query)->whereIn('src', $set);
        };
        $branchB = function () use ($query, $set) {
            return (clone $query)->whereIn('dst', $set)->whereNotIn('src', $set);
        };
        $total = \Illuminate\Support\Facades\Cache::remember($countKey, 60, function () use ($branchA, $branchB) {
            return $branchA()->count() + $branchB()->count();
        });

        // Ambil offset+limit per cabang (index walk, tanpa filesort), gabung
        // 2 aliran terurut di PHP, potong jendela. Guard memori: jendela
        // raksasa (>50 rb) fallback ke jalur single-query.
        $need = $offset + $perPage;
        if ($need > 50000) {
            $fallback = clone $query;
            $fallback->where(function ($q) use ($set) {
                $q->whereIn('src', $set)->orWhereIn('dst', $set);
            });
            $logsCollection = (clone $fallback)
                ->orderBy($sortCol, $sortDir)
                ->orderBy('id', $sortDir)
                ->offset($offset)
                ->limit($perPage)
                ->get();
        } else {
            $aRows = $branchA()->orderBy($sortCol, $sortDir)->orderBy('id', $sortDir)->limit($need)->get();
            $bRows = $branchB()->orderBy($sortCol, $sortDir)->orderBy('id', $sortDir)->limit($need)->get();
            $desc = $sortDir === 'desc';
            $cmp = function ($a, $b) use ($sortCol, $desc) {
                $av = $a->{$sortCol};
                $bv = $b->{$sortCol};
                $c = (is_numeric($av) && is_numeric($bv)) ? ($av <=> $bv) : strcmp((string) $av, (string) $bv);
                if ($c === 0) {
                    $c = $a->id <=> $b->id;
                }
                return $desc ? -$c : $c;
            };
            $merged = [];
            $ia = 0;
            $ib = 0;
            $na = $aRows->count();
            $nb = $bRows->count();
            $take = min($need, $na + $nb);
            for ($i = 0; $i < $take; $i++) {
                if ($ib >= $nb || ($ia < $na && $cmp($aRows[$ia], $bRows[$ib]) <= 0)) {
                    $merged[] = $aRows[$ia++];
                } else {
                    $merged[] = $bRows[$ib++];
                }
            }
            $logsCollection = collect(array_slice($merged, $offset, $perPage));
        }
    } else {
    // 1. Total di-cache 60 detik per kombinasi filter (COUNT di jutaan baris
    // itu 1-2 detik sendiri; filter jarang berubah dalam semenit).
    // Multi-set: terapkan OR-OR seperti semula.
    if (!$likeSearch) {
        foreach ($extSets as $set) {
            $query->where(function ($q) use ($set) {
                $q->whereIn('src', $set)->orWhereIn('dst', $set);
            });
        }
    }
    $total = \Illuminate\Support\Facades\Cache::remember($countKey, 60, function () use ($query) {
        return (clone $query)->count();
    });

    // 2. Satu query langsung. Trik ujung: bila halaman yang diminta ada di
    // separuh belakang, ambil dari ujung berlawanan (sort dibalik, offset dari
    // akhir) lalu balik urutannya di PHP — OFFSET kecil = cepat. Contoh:
    // page terakhir 2,6 jt baris = page pertama sort terbalik (milidetik).
    // Tiebreaker id agar urutan stabil di kedua arah.
    $offset = ($page - 1) * $perPage;
    $fromEnd = $total > 0 && $offset > $total / 2;
    if ($fromEnd) {
        $effDir = $sortDir === 'asc' ? 'desc' : 'asc';
        // Ambil jendela yang sama dari ujung berlawanan: [total-offset-limit, total-offset)
        $revOffset = max(0, $total - $offset - $perPage);
        $revLimit = min($perPage, $total - $offset);
        $logsCollection = $revLimit > 0
            ? (clone $query)
                ->orderBy($sortCol, $effDir)
                ->orderBy('id', $effDir)
                ->offset($revOffset)
                ->limit($revLimit)
                ->get()
                ->reverse()
                ->values()
            : collect();
    } else {
        $logsCollection = (clone $query)
            ->orderBy($sortCol, $sortDir)
            ->orderBy('id', $sortDir)
            ->offset($offset)
            ->limit($perPage)
            ->get();
    }
    } // end else: jalur single-query (tanpa filter ext / multi-set / LIKE)

    // 4. Bungkus ke Paginator
    $paginatedLogs = new \Illuminate\Pagination\LengthAwarePaginator(
        $logsCollection,
        $total,
        $perPage,
        $page,
        ['path' => $request->url(), 'query' => $request->query()]
    );

    $paginatedLogs->appends($request->except('page'));

    $paginatedLogs->getCollection()->transform(function ($log) {
        if ($log->src === $log->dst && strlen($log->src) > 5) {
            $log->src = 'Ext / Agent'; 
        }
        return $log;
    });

    // Nama agent penelepon: sisi ext (src outbound / dst inbound) dipetakan
    // sekali via tabel agents, fallback ke cnam CDR.
    $agentNames = \App\Models\Agent::pluck('name', 'extension')->toArray();
    $paginatedLogs->getCollection()->transform(function ($log) use ($agentNames) {
        $ext = null;
        if (isset($agentNames[$log->src])) {
            $ext = $log->src;
        } elseif (isset($agentNames[$log->dst])) {
            $ext = $log->dst;
        }
        $log->agent_name = $ext !== null
            ? $agentNames[$ext]
            : (trim((string) ($log->cnam ?? '')) !== '' ? $log->cnam : '-');
        return $log;
    });

    return response()->json([
        'status' => 'success',
        'data'   => $paginatedLogs
    ]);
    } finally {
        $flight->release();
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

    /** Label rentang tanggal untuk nama file export (Y-m-d_sd_Y-m-d). */
    protected function exportDateLabel(array $filters): string
    {
        $fmt = function ($d) {
            $d = (string) ($d ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                return null;
            }
            [$y, $m, $dd] = explode('-', $d);
            return checkdate((int) $m, (int) $dd, (int) $y) ? $d : null;
        };
        $from = $fmt($filters['start_date'] ?? null);
        $to = $fmt($filters['end_date'] ?? null);
        if ($from && $to) {
            return $from . '_sd_' . $to;
        }
        if ($from) {
            return $from . '_sd_sekarang';
        }
        if ($to) {
            return 'awal_sd_' . $to;
        }
        return now()->subDays(30)->toDateString() . '_sd_' . now()->toDateString();
    }

    public function exportExcel(Request $request)
    {
        // Single-flight: 1 export per sesi dalam satu waktu. Klik ganda bikin
        // 2 job raksasa berebut file + CPU (pernah: rename gagal karena tabrakan).
        $flightKey = 'export_running_' . (session()->getId() ?: $request->ip());
        if (\Illuminate\Support\Facades\Cache::get($flightKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Export sebelumnya masih berjalan. Tunggu selesai dulu.',
            ], 429);
        }
        \Illuminate\Support\Facades\Cache::put($flightKey, true, now()->addHours(6));

        // 🚀 Tangkap format dari frontend (.xlsx atau .csv)
        $format = $request->query('format', 'xlsx');
        if (!in_array($format, ['xlsx', 'csv'])) {
            $format = 'xlsx';
        }

        // Filter dulu (dipakai untuk nama file DAN job) — JANGAN di bawah
        // pemakaian $filters, dulu pernah undefined variable di sini yang
        // bikin export 500 TAPI flag single-flight sudah terlanjur diset,
        // akibatnya semua klik berikutnya nyangkut 429 selama 6 jam.
        $filters = $request->only(['agent_extension', 'search', 'start_date', 'end_date']);
        $filters['supervisor_extension'] = session('supervisor_extension');
        // Job melepas flag saat selesai/gagal (finally di handle()).
        $filters['_flight_key'] = $flightKey;

        // Terapkan format ke nama file (uniqid anti tabrakan klik ganda)
        // cth: call-history-2026-08-01_sd_2026-08-31-20260923_103751-abc123.xlsx
        $filename = 'call-history-' . $this->exportDateLabel($filters) . '-' . date('Y-m-d_H-i-s') . '-' . uniqid() . '.' . $format;
        $filePath = 'exports/' . $filename;

        // Lempar ke Job. Kalau dispatch-nya sendiri gagal (cth. queue down),
        // lepas flag langsung agar user tidak nyangkut 429.
        try {
            ProcessCallLogExport::dispatch($filters, $filePath);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Cache::forget($flightKey);
            throw $e;
        }

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
                // Path relatif agar ikut host yang sedang dipakai browser
                // (asset() mengunci ke APP_URL yang bisa localhost).
                'url' => '/storage/' . $path,
            ]);
        }

        // Progres pengerjaan (ditulis job tiap 5000 baris) + info cap
        $progress = \Illuminate\Support\Facades\Cache::get('export_progress_' . $filename, []);

        return response()->json(array_merge(['ready' => false], $progress));
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
public function exportZip(Request $request)
    {
        try {
            $filters = $request->all();
            // cth: recordings-2026-08-01_sd_2026-08-31-20260923_103751.zip
            $filename = 'recordings-' . $this->exportDateLabel($filters) . '-' . date('Y-m-d_H-i-s') . '.zip';

            // Hitung total dulu (untuk progres). Bisa beberapa detik di jutaan baris.
            $countQuery = \App\Jobs\ProcessZipChunk::countQuery($filters);
            $total = (clone $countQuery)->count();

            \Illuminate\Support\Facades\Cache::put('zip_status_' . $filename, [
                'ready' => false,
                'done' => 0,
                'found' => 0,
                'total' => $total,
                'last_id' => 0,
            ], now()->addHours(12));

            // Potongan pertama; potongan berikut berantai otomatis dari job.
            // Tiap potong < timeout worker sehingga export sebesar apa pun selesai.
            \App\Jobs\ProcessZipChunk::dispatch($filename, $filters, 0);

            return response()->json([
                'status' => 'success',
                'filename' => $filename
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function exportZipStatus(Request $request)
    {
        $filename = $request->query('filename');
        $status = Cache::get('zip_status_' . $filename, ['ready' => false]);

        // Gembok: klaim ready tapi file tidak ada (mis. run lama yang gagal
        // di tengah) jangan sampai mengarah ke 403 misterius.
        if (!empty($status['ready']) && !is_file(storage_path('app/public/exports/' . basename((string) $filename)))) {
            $status['ready'] = false;
            $status['error'] = 'File hasil tidak ditemukan (ekspor gagal di tengah jalan?). Silakan ulangi export.';
        }

        return response()->json($status);
    }
}