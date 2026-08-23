<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CallLog;

class CallLogController extends Controller
{
    public function store(Request $request)
    {
        // Tangkap data dari Asterisk
        CallLog::create([
            'caller'     => $request->caller,
            'destination'=> $request->destination,
            'disposition'=> $request->disposition,
            'sip_code'   => $request->sip_code ?? '200', // Contoh: 404, 486, 503
            'duration'   => $request->duration ?? 0,
            'terminated_by'=> $request->terminated_by,
        ]);

        return response()->json(['status' => 'success']);
    }
}