<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use Illuminate\Support\Str;
use App\Services\Asterisk\ProvisionerService;

class AgentController extends Controller
{
    protected $provisioner;

    public function __construct(ProvisionerService $provisioner)
    {
        $this->provisioner = $provisioner;
    }

    public function index()
    {
        $agents = Agent::all();
        $supervisors = Agent::where('role', 'supervisor')->get(); 
        
        return view('admin.agents.index', compact('agents', 'supervisors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'extension'     => 'required|string|unique:agents,extension',
            'supervisor_id' => 'nullable|exists:agents,id', 
            'secret'        => 'nullable|string|min:4',
            'role'          => 'required|in:agent,supervisor'
        ]);

        $sipSecret = $request->filled('secret') ? $request->secret : Str::random(12);

        $agent = Agent::create([
            'name'          => $request->name,
            'extension'     => $request->extension,
            'secret'        => $sipSecret,
            'role'          => $request->role,
            'supervisor_id' => $request->supervisor_id,
            'status'        => 'offline',
        ]);

        try {
            $output = $this->provisioner->provision($agent);

            return response()->json([
                'status'       => 'success',
                'message'      => "Agen {$agent->name} (Ext: {$agent->extension}) berhasil dibuat!",
                'asterisk_log' => trim($output)
            ]);

        } catch (\Exception $e) {
            $agent->delete();
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal sinkronisasi ke FreePBX: ' . $e->getMessage()
            ], 500);
        }
    }

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
            $secretChanged = false;
            
            $agent->name          = $request->name;
            $agent->supervisor_id = $request->supervisor_id;
            $agent->role          = $request->role;

            if ($request->filled('secret')) {
                $agent->secret = $request->secret;
                $secretChanged = true;
            }

            $agent->save();
            $this->provisioner->modify($agent, $secretChanged);

            return response()->json([
                'status'  => 'success',
                'message' => "Data agen {$agent->name} berhasil di-update!"
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
            $output = $this->provisioner->remove($agent);
            $agent->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Agen berhasil dihapus!',
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