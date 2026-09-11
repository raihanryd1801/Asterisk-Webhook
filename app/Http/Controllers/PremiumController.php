<?php

namespace App\Http\Controllers;

use App\Models\FeatureFlag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PremiumController extends Controller
{
    public function index()
    {
        $flags = FeatureFlag::whereIn('key', array_keys(FeatureFlag::MODULES))
            ->get()
            ->keyBy('key');

        return view('admin.premium.index', compact('flags'));
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'key' => 'required|in:crm,collection,dialer',
            'enabled' => 'required|boolean',
        ]);

        FeatureFlag::setEnabled($request->key, $request->boolean('enabled'), Auth::id());

        $label = FeatureFlag::MODULES[$request->key];
        $state = $request->boolean('enabled') ? 'DIBUKA' : 'DIGEMBOK';

        return response()->json([
            'status' => 'success',
            'message' => "{$label} berhasil {$state}.",
            'states' => FeatureFlag::states(),
        ]);
    }
}
