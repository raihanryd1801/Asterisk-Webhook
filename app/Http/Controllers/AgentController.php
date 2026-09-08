<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use Illuminate\Support\Str;
use App\Jobs\ProvisionAsteriskAgent;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
{
    public function index()
    {
        $agents = Agent::all();
        $supervisors = Agent::where('role', 'supervisor')->get(); 
        
        return view('admin.agents.index', compact('agents', 'supervisors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'extension'        => 'required|string|unique:agents,extension',
            'secret'           => 'nullable|string|min:4',
            'role'             => 'required|in:agent,supervisor',
            'context'          => 'nullable|string|in:from-internal,blokir-total',
            
            // 🚀 Ubah ke validasi Array Multi-SPV
            'supervisor_ids'   => 'nullable|array',
            'supervisor_ids.*' => 'exists:agents,id'
        ]);

        $sipSecret = $request->filled('secret') ? $request->secret : Str::random(12);
        $context = $request->context ?? 'from-internal';

        $agent = Agent::create([
            'name'      => $request->name,
            'extension' => $request->extension,
            'secret'    => $sipSecret,
            'role'      => $request->role,
            'status'    => 'offline',
            'context'   => $context,
            // 🚀 Hapus 'supervisor_id' dari sini karena pakai tabel pivot
        ]);

        // 🚀 Jalankan sinkronisasi Multiple Supervisor ke tabel pivot (VERSI KEBAL)
        if ($request->filled('supervisor_ids')) {
            $spvIds = is_array($request->supervisor_ids) 
                      ? $request->supervisor_ids 
                      : [$request->supervisor_ids];
            
            $agent->supervisors()->sync($spvIds);
        }

        // Lempar ke background queue
        ProvisionAsteriskAgent::dispatch($agent, 'create');

        return response()->json([
            'status'  => 'success',
            'message' => "Agen {$agent->name} berhasil disimpan dan diproses di latar belakang!"
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'secret'           => 'nullable|string|min:4',
            'role'             => 'required|in:agent,supervisor',
            'context'          => 'nullable|string|in:from-internal,blokir-total',
            'supervisor_ids'   => 'nullable|array',
            'supervisor_ids.*' => 'exists:agents,id' 
        ]);

        $agent = Agent::findOrFail($id);
        
        // 🚀 1. Rekam data lama sebelum diubah
        $oldSecret = $agent->secret;
        $oldContext = $agent->context; 
        $oldRole = $agent->role; // Rekam jabatan lamanya
        
        $agent->name          = $request->name;
        $agent->role          = $request->role;
        $agent->context       = $request->context ?? 'from-internal';
        
        $secretChanged = false;
        if ($request->filled('secret')) {
            $agent->secret = $request->secret;
            $secretChanged = ($request->secret !== $oldSecret);
        }

        // Simpan data utama agen
        $agent->save();

        // 2. Update SPV untuk dirinya sendiri
        if ($request->has('supervisor_ids')) {
            $agent->supervisors()->sync($request->supervisor_ids);
        } else {
            $agent->supervisors()->detach(); 
        }

        // 🚀 3. LOGIKA DEMOSI (Turun Jabatan)
        // Jika tadinya supervisor dan sekarang jadi agent, copot dia dari semua agen bawahannya
        if ($oldRole === 'supervisor' && $request->role === 'agent') {
            $agent->agents()->detach(); 
        }

        // Lempar proses update ke background queue
        ProvisionAsteriskAgent::dispatch($agent, 'update', $secretChanged);

        return response()->json([
            'status'  => 'success',
            'message' => "Data agen {$agent->name} sedang diperbarui di latar belakang!"
        ]);
    }

    public function destroy($id)
    {
        $agent = Agent::findOrFail($id);
        $extension = $agent->extension;

        // Hapus dari database lokal
        $agent->delete();

        // Lempar ekstensi string ke background queue
        ProvisionAsteriskAgent::dispatch($extension, 'delete');

        return response()->json([
            'status'  => 'success',
            'message' => 'Agen sedang dihapus dari sistem di latar belakang!'
        ]);
    }
}