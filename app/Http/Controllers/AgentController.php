<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\Group;
use Illuminate\Support\Str;
use App\Services\Asterisk\ProvisionerService; // 🚀 Import service provisioner

class AgentController extends Controller
{
    protected $provisioner;

    // 🚀 Inject ProvisionerService melalui constructor
    public function __construct(ProvisionerService $provisioner)
    {
        $this->provisioner = $provisioner;
    }

    public function index()
    {
        $agents = Agent::with('group')->get();
        $groups = Group::all();
        return view('admin.agents.index', compact('agents', 'groups'));
    }

    public function store(Request $request)
{
    $request->validate([
        'name'      => 'required|string|max:255',
        'extension' => 'required|string|unique:agents,extension',
        'group_id'  => 'nullable|exists:groups,id',
        'secret'    => 'nullable|string|min:4'
    ]);

    // Jika admin mengisi password, gunakan itu. Jika kosong, generate random.
    $sipSecret = $request->filled('secret') ? $request->secret : Str::random(12);

    // 1. Simpan ke database lokal Laravel
    $agent = Agent::create([
        'name'      => $request->name,
        'extension' => $request->extension,
        'secret'    => $sipSecret,
        'group_id'  => $request->group_id,
        'status'    => 'offline',
    ]);

    // 2. Eksekusi Provisioning ke FreePBX
    try {
        $output = $this->provisioner->provision($agent);

        return response()->json([
            'status'  => 'success',
            'message' => "Agen {$agent->name} (Ext: {$agent->extension}) berhasil dibuat dengan password SIP & Recording aktif!",
            'asterisk_log' => trim($output)
        ]);

    } catch (\Exception $e) {
        // Hapus data lokal jika provisioning gagal
        $agent->delete();

        return response()->json([
            'status'  => 'error',
            'message' => 'Gagal sinkronisasi ke FreePBX: ' . $e->getMessage()
        ], 500);
    }
}

    // Ganti parameter 'Agent $agent' menjadi '$id'
    public function update(Request $request, $id)
    {
        // Validasi input
        $request->validate([
            'name'      => 'required|string|max:255',
            'group_id'  => 'nullable|exists:groups,id',
            'secret'    => 'nullable|string|min:4' // Opsional
        ]);

        try {
            // PENCARIAN EKSPLISIT: Cari agen di database berdasarkan ID
            $agent = Agent::findOrFail($id);
            
            $secretChanged = false;
            
            $agent->name = $request->name;
            $agent->group_id = $request->group_id;

            if ($request->filled('secret')) {
                $agent->secret = $request->secret;
                $secretChanged = true;
            }

            // 1. Simpan perubahan di database Laravel (sekarang pasti UPDATE, bukan INSERT)
            $agent->save();

            // 2. Sinkronisasi perubahan ke FreePBX
            $this->provisioner->modify($agent, $secretChanged);

            return response()->json([
                'status'  => 'success',
                'message' => "Data agen {$agent->name} berhasil di-update & sinkron dengan FreePBX!"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal sinkronisasi ke FreePBX: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $agent = Agent::findOrFail($id);

        try {
            // 1. Eksekusi penghapusan di FreePBX
            $output = $this->provisioner->remove($agent);
            
            // 2. Hapus data lokal Laravel jika berhasil
            $agent->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Agen dan ekstensi FreePBX berhasil dihapus!',
                'log'     => $output
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menghapus dari FreePBX: ' . $e->getMessage()
            ], 500);
        }
    }
}