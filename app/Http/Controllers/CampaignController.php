<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Campaign;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CampaignController extends Controller
{
    protected function authorizeAccess()
    {
        $isAdmin = Auth::check();
        $isSupervisor = session()->has('supervisor_extension');
        
        if (!$isAdmin && !$isSupervisor) {
            abort(403, 'Unauthorized. Admin or Supervisor access required.');
        }
    }

    protected function getCurrentUserId()
    {
        if (Auth::check()) {
            return Auth::id();
        }
        $ext = session('supervisor_extension');
        if ($ext) {
            $spv = Agent::where('extension', $ext)->first();
            return $spv?->id;
        }
        return null;
    }

    public function index(Request $request)
    {
        $this->authorizeAccess();

        $query = Campaign::with('creator');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $campaigns = $query->latest()->paginate(15)->withQueryString();
        $types = ['early', 'late', 'legal', 'recovery'];

        if ($request->wantsJson()) {
            return response()->json($campaigns);
        }

        return view('crm.campaigns.index', compact('campaigns', 'types'));
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:campaigns,code',
            'description' => 'nullable|string',
            'type' => 'required|in:early,late,legal,recovery',
            'target_buckets' => 'nullable|array',
            'channels' => 'nullable|array',
            'max_attempts_per_day' => 'nullable|integer|min:1|max:10',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'schedule_days' => 'nullable|array',
            'script_template' => 'nullable|string',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $campaign = Campaign::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'type' => $request->type,
            'target_buckets' => $request->target_buckets ?? [],
            'channels' => $request->channels ?? ['call', 'sms'],
            'max_attempts_per_day' => $request->max_attempts_per_day ?? 3,
            'start_time' => $request->start_time ?? '08:00:00',
            'end_time' => $request->end_time ?? '17:00:00',
            'schedule_days' => $request->schedule_days ?? [1,2,3,4,5],
            'script_template' => $request->script_template,
            'is_active' => $request->boolean('is_active', true),
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'created_by' => $this->getCurrentUserId(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign created successfully!',
            'campaign' => $campaign,
        ]);
    }

    public function show(Campaign $campaign)
    {
        $this->authorizeAccess();
        $campaign->load('creator');
        return response()->json($campaign);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $this->authorizeAccess();

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:campaigns,code,' . $campaign->id,
            'description' => 'nullable|string',
            'type' => 'required|in:early,late,legal,recovery',
            'target_buckets' => 'nullable|array',
            'channels' => 'nullable|array',
            'max_attempts_per_day' => 'nullable|integer|min:1|max:10',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'schedule_days' => 'nullable|array',
            'script_template' => 'nullable|string',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $campaign->update([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'type' => $request->type,
            'target_buckets' => $request->target_buckets ?? [],
            'channels' => $request->channels ?? ['call', 'sms'],
            'max_attempts_per_day' => $request->max_attempts_per_day ?? 3,
            'start_time' => $request->start_time ?? '08:00:00',
            'end_time' => $request->end_time ?? '17:00:00',
            'schedule_days' => $request->schedule_days ?? [1,2,3,4,5],
            'script_template' => $request->script_template,
            'is_active' => $request->boolean('is_active', true),
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign updated successfully!',
            'campaign' => $campaign,
        ]);
    }

    public function destroy(Campaign $campaign)
    {
        $this->authorizeAccess();
        $campaign->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign deleted successfully!',
        ]);
    }

    public function getCollectors(Request $request)
    {
        $agents = Agent::where('role', 'agent')->get(['id', 'name', 'extension']);
        return response()->json($agents);
    }

    public function blastPreview(Campaign $campaign)
    {
        $this->authorizeAccess();

        $targets = \App\Models\Customer::where('campaign_id', $campaign->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNotNull('phone')
            ->count();

        $blasts = \App\Models\BlastLog::where('campaign_id', $campaign->id)
            ->latest()->limit(5)->get();

        return response()->json([
            'status' => 'success',
            'targets' => $targets,
            'history' => $blasts,
        ]);
    }

    public function blastSend(Request $request, Campaign $campaign)
    {
        $this->authorizeAccess();

        $request->validate([
            'channel' => 'required|in:wa,sms',
            'message' => 'required|string|max:1000',
        ]);

        $targets = \App\Models\Customer::where('campaign_id', $campaign->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNotNull('phone')
            ->count();

        if ($targets === 0) {
            return response()->json(['status' => 'error', 'message' => 'Tidak ada target (campaign ini belum ada case unpaid/partial)'], 422);
        }

        $log = \App\Models\BlastLog::create([
            'campaign_id' => $campaign->id,
            'channel' => $request->channel,
            'message' => $request->message,
            'total_target' => $targets,
            'sent' => $targets,
            'failed' => 0,
            'status' => 'sent',
            'note' => 'Log lokal — hubungkan gateway WA/SMS untuk pengiriman nyata.',
            'created_by' => $this->getCurrentUserId(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Blast dicatat: {$targets} target via " . strtoupper($request->channel),
            'log' => $log,
        ]);
    }
}