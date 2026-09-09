<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Agent;
use Illuminate\Support\Facades\Auth;
use Rap2hpoutre\FastExcel\FastExcel;

class CustomerController extends Controller
{
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

    protected function authorizeAccess()
    {
        $isAdmin = Auth::check();
        $isSupervisor = session()->has('supervisor_extension');
        
        if (!$isAdmin && !$isSupervisor) {
            abort(403, 'Unauthorized. Admin or Supervisor access required.');
        }
    }

    public function dashboard(Request $request)
    {
        $this->authorizeAccess();

        // ============ STATISTIK UTAMA ============
        $totalCustomers = Customer::count();
        $totalAmount = Customer::sum('total_amount');
        $totalPaid = Customer::sum('paid_amount');
        $totalDiscount = Customer::sum('discount_amount');
        $totalRemaining = max(0, $totalAmount - $totalPaid - $totalDiscount);
        $collectionRate = $totalAmount > 0 ? round(($totalPaid + $totalDiscount) / $totalAmount * 100, 1) : 0;

        // ============ BREAKDOWN BY PAYMENT STATUS ============
        $paymentStatusStats = Customer::selectRaw('payment_status, COUNT(*) as count, SUM(total_amount) as total_amount, SUM(paid_amount) as paid_amount')
            ->groupBy('payment_status')
            ->pluck('count', 'payment_status')
            ->toArray();

        $paymentStatusAmounts = Customer::selectRaw('payment_status, SUM(total_amount) as total_amount, SUM(paid_amount) as paid_amount')
            ->groupBy('payment_status')
            ->get()
            ->keyBy('payment_status')
            ->map(fn($item) => ['total' => $item->total_amount, 'paid' => $item->paid_amount])
            ->toArray();

        // ============ BREAKDOWN BY PIPELINE STATUS ============
        $pipelineStats = Customer::selectRaw('status, COUNT(*) as count, SUM(total_amount) as total_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->map(fn($item) => ['count' => $item->count, 'amount' => $item->total_amount])
            ->toArray();

        $statusOrder = ['new', 'contacted', 'qualified', 'proposal', 'closed_won', 'closed_lost'];
        $pipelineData = [];
        foreach ($statusOrder as $status) {
            $pipelineData[$status] = $pipelineStats[$status] ?? ['count' => 0, 'amount' => 0];
        }

        // ============ TOP CUSTOMERS BY AMOUNT ============
        $topCustomers = Customer::with('assignedAgent')
            ->where('total_amount', '>', 0)
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get(['id', 'name', 'phone', 'company', 'total_amount', 'paid_amount', 'discount_amount', 'payment_status', 'assigned_agent_id']);

        // ============ AGENT PERFORMANCE ============
        $agentPerformance = Customer::selectRaw('assigned_agent_id, COUNT(*) as total_customers, SUM(total_amount) as total_amount, SUM(paid_amount) as paid_amount, SUM(discount_amount) as discount_amount')
            ->whereNotNull('assigned_agent_id')
            ->groupBy('assigned_agent_id')
            ->get()
            ->map(function ($item) {
                $agent = Agent::find($item->assigned_agent_id);
                $remaining = max(0, $item->total_amount - $item->paid_amount - $item->discount_amount);
                $rate = $item->total_amount > 0 ? round(($item->paid_amount + $item->discount_amount) / $item->total_amount * 100, 1) : 0;
                return [
                    'agent_name' => $agent?->name ?? 'Unknown',
                    'agent_extension' => $agent?->extension ?? '-',
                    'total_customers' => $item->total_customers,
                    'total_amount' => $item->total_amount,
                    'paid_amount' => $item->paid_amount,
                    'discount_amount' => $item->discount_amount,
                    'remaining' => $remaining,
                    'collection_rate' => $rate,
                ];
            })
            ->sortByDesc('total_amount')
            ->values()
            ->toArray();

        // ============ MONTHLY COLLECTION TREND (Last 6 months) ============
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i)->startOfMonth();
            $nextMonth = $date->copy()->addMonth();
            
            $paidInMonth = Customer::where('last_payment_date', '>=', $date)
                ->where('last_payment_date', '<', $nextMonth)
                ->sum('paid_amount');
            
            $discountInMonth = Customer::where('last_payment_date', '>=', $date)
                ->where('last_payment_date', '<', $nextMonth)
                ->sum('discount_amount');
            
            $monthlyTrend[] = [
                'month' => $date->format('M Y'),
                'paid' => $paidInMonth,
                'discount' => $discountInMonth,
                'total' => $paidInMonth + $discountInMonth,
            ];
        }

        // ============ RECENT PAYMENTS ============
        $recentPayments = Customer::with('assignedAgent')
            ->whereNotNull('last_payment_date')
            ->where('paid_amount', '>', 0)
            ->orderByDesc('last_payment_date')
            ->limit(10)
            ->get(['id', 'name', 'phone', 'paid_amount', 'discount_amount', 'payment_status', 'payment_notes', 'last_payment_date', 'assigned_agent_id']);

        // ============ OVERDUE / ATTENTION NEEDED ============
        $attentionCustomers = Customer::with('assignedAgent')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('total_amount', '>', 0)
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get(['id', 'name', 'phone', 'total_amount', 'paid_amount', 'discount_amount', 'payment_status', 'payment_notes', 'assigned_agent_id']);

        return view('crm.dashboard', compact(
            'totalCustomers', 'totalAmount', 'totalPaid', 'totalDiscount', 'totalRemaining', 'collectionRate',
            'paymentStatusStats', 'paymentStatusAmounts',
            'pipelineData',
            'topCustomers',
            'agentPerformance',
            'monthlyTrend',
            'recentPayments',
            'attentionCustomers'
        ));
    }

    public function index(Request $request)
    {
        $this->authorizeAccess();
        
        $query = Customer::with(['assignedAgent', 'collector', 'campaign', 'creator']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status')) {
            $query->status($request->status);
        }

        if ($request->filled('agent_id')) {
            $query->assignedTo($request->agent_id);
        }

        if ($request->filled('payment_status')) {
            $query->paymentStatus($request->payment_status);
        }

        if ($request->filled('bucket')) {
            $query->bucket($request->bucket);
        }

        if ($request->filled('campaign_id')) {
            $query->inCampaign($request->campaign_id);
        }

        if ($request->filled('handover_status')) {
            $query->handoverStatus($request->handover_status);
        }

        if ($request->boolean('bad_debt_only')) {
            $query->badDebt();
        }

        $customers = $query->latest()->paginate(15)->withQueryString();
        $agents = Agent::where('role', 'agent')->get(['id', 'name', 'extension']);
        $statuses = ['new', 'contacted', 'qualified', 'proposal', 'closed_won', 'closed_lost'];
        $paymentStatuses = ['unpaid', 'partial', 'paid', 'discounted'];
        $buckets = ['Current', 'Bucket 1', 'Bucket 2', 'Bucket 3', 'NPL'];
        $campaigns = \App\Models\Campaign::active()->orderBy('name')->get(['id', 'name', 'code', 'type']);
        $collectors = Agent::where('role', 'agent')->orderBy('name')->get(['id', 'name', 'extension']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($customers);
        }

        return view('crm.customers.index', compact('customers', 'agents', 'statuses', 'paymentStatuses', 'buckets', 'campaigns', 'collectors'));
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customers,phone',
            'email' => 'nullable|email|max:255',
            'company' => 'nullable|string|max:255',
            'status' => 'required|in:new,contacted,qualified,proposal,closed_won,closed_lost',
            'notes' => 'nullable|string',
            'assigned_agent_id' => 'nullable|exists:agents,id',
            'total_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_status' => 'nullable|in:unpaid,partial,paid,discounted',
            'payment_notes' => 'nullable|string',
            'due_date' => 'nullable|date',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'collector_id' => 'nullable|exists:agents,id',
            'risk_level' => 'nullable|in:low,medium,high,critical',
        ]);

        $customer = Customer::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'company' => $request->company,
            'status' => $request->status,
            'notes' => $request->notes,
            'assigned_agent_id' => $request->assigned_agent_id,
            'created_by' => $this->getCurrentUserId(),
            'last_contacted_at' => $request->status !== 'new' ? now() : null,
            'total_amount' => $request->total_amount ?? 0,
            'paid_amount' => $request->paid_amount ?? 0,
            'discount_amount' => $request->discount_amount ?? 0,
            'payment_status' => $request->payment_status ?? 'unpaid',
            'payment_notes' => $request->payment_notes,
            'due_date' => $request->due_date ?: null,
            'campaign_id' => $request->campaign_id ?: null,
            'collector_id' => $request->collector_id ?: null,
            'risk_level' => $request->risk_level ?? 'low',
        ]);

        if ($customer->due_date) $customer->recalculateBucket();

        return response()->json([
            'status' => 'success',
            'message' => 'Customer created successfully!',
            'customer' => $customer->load('assignedAgent'),
        ]);
    }

    public function show(Customer $customer)
    {
        $this->authorizeAccess();
        $customer->load(['assignedAgent', 'creator']);
        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeAccess();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customers,phone,' . $customer->id,
            'email' => 'nullable|email|max:255',
            'company' => 'nullable|string|max:255',
            'status' => 'required|in:new,contacted,qualified,proposal,closed_won,closed_lost',
            'notes' => 'nullable|string',
            'assigned_agent_id' => 'nullable|exists:agents,id',
            'total_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_status' => 'nullable|in:unpaid,partial,paid,discounted',
            'payment_notes' => 'nullable|string',
            'due_date' => 'nullable|date',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'collector_id' => 'nullable|exists:agents,id',
            'risk_level' => 'nullable|in:low,medium,high,critical',
        ]);

        $oldStatus = $customer->status;
        $newStatus = $request->status;

        $customer->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'company' => $request->company,
            'status' => $newStatus,
            'notes' => $request->notes,
            'assigned_agent_id' => $request->assigned_agent_id,
            'last_contacted_at' => $newStatus !== 'new' && $oldStatus === 'new' ? now() : ($newStatus !== $oldStatus ? now() : $customer->last_contacted_at),
            'total_amount' => $request->total_amount ?? $customer->total_amount,
            'paid_amount' => $request->paid_amount ?? $customer->paid_amount,
            'discount_amount' => $request->discount_amount ?? $customer->discount_amount,
            'payment_status' => $request->payment_status ?? $customer->payment_status,
            'payment_notes' => $request->payment_notes ?? $customer->payment_notes,
            'due_date' => $request->filled('due_date') ? $request->due_date : $customer->due_date,
            'campaign_id' => $request->has('campaign_id') ? ($request->campaign_id ?: null) : $customer->campaign_id,
            'collector_id' => $request->has('collector_id') ? ($request->collector_id ?: null) : $customer->collector_id,
            'risk_level' => $request->risk_level ?? $customer->risk_level,
        ]);

        // Auto-update payment status based on amounts
        $customer->refresh();
        $remaining = max(0, $customer->total_amount - $customer->paid_amount - $customer->discount_amount);
        if ($remaining <= 0 && $customer->total_amount > 0) {
            $customer->update(['payment_status' => $customer->discount_amount > 0 ? 'discounted' : 'paid', 'last_payment_date' => now()]);
        } elseif ($customer->paid_amount > 0 || $customer->discount_amount > 0) {
            if (!in_array($customer->payment_status, ['partial', 'discounted'])) {
                $customer->update(['payment_status' => 'partial', 'last_payment_date' => now()]);
            } else {
                $customer->update(['last_payment_date' => now()]);
            }
        } else {
            $customer->update(['payment_status' => 'unpaid']);
        }

        if ($customer->due_date) $customer->recalculateBucket();

        return response()->json([
            'status' => 'success',
            'message' => 'Customer updated successfully!',
            'customer' => $customer->load('assignedAgent'),
        ]);
    }

    public function destroy(Customer $customer)
    {
        $this->authorizeAccess();
        $customer->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Customer deleted successfully!',
        ]);
    }

    public function getCallHistory(Customer $customer)
    {
        $this->authorizeAccess();
        
        $calls = \App\Models\Cdr::where(function ($q) use ($customer) {
            $q->where('src', $customer->phone)
              ->orWhere('dst', $customer->phone);
        })->latest('calldate')->limit(50)->get([
            'calldate', 'src', 'dst', 'disposition', 'duration', 'billsec', 'uniqueid'
        ]);

        return response()->json($calls);
    }

    public function getAssignedCustomers(Request $request, $extension)
    {
        $agent = Agent::where('extension', $extension)->first();
        
        if (!$agent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Agent not found'
            ], 404);
        }

        $customers = Customer::where(function ($q) use ($agent) {
                $q->where('assigned_agent_id', $agent->id)
                  ->orWhere('collector_id', $agent->id);
            })
            ->whereIn('status', ['new', 'contacted', 'qualified', 'proposal'])
            ->latest()
            ->get(['id', 'name', 'phone', 'email', 'company', 'status', 'notes', 'last_contacted_at', 'total_amount', 'paid_amount', 'discount_amount', 'payment_status', 'payment_notes', 'last_payment_date', 'due_date', 'days_past_due', 'bucket', 'risk_level', 'promise_to_pay']);

        return response()->json([
            'status' => 'success',
            'data' => $customers
        ]);
    }

    public function agentSetPTP(Request $request, $extension, Customer $customer)
    {
        // Hanya agent yang sedang login & customer yang di-assign ke dia
        $loggedExt = session('agent_extension');
        if (!$loggedExt || $loggedExt !== $extension) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak. Silakan login ulang.'], 403);
        }

        $agent = Agent::where('extension', $extension)->first();
        if (!$agent) {
            return response()->json(['status' => 'error', 'message' => 'Agent tidak ditemukan'], 404);
        }

        if ($customer->assigned_agent_id !== $agent->id && $customer->collector_id !== $agent->id) {
            return response()->json(['status' => 'error', 'message' => 'Customer ini bukan assigned Anda'], 403);
        }

        $request->validate([
            'ptp_amount' => 'required|numeric|min:1',
            'ptp_date' => 'required|date|after_or_equal:today',
            'ptp_note' => 'nullable|string|max:1000',
        ]);

        $customer->setPromiseToPay(
            $request->ptp_amount,
            $request->ptp_date,
            $request->ptp_note
        );

        return response()->json([
            'status' => 'success',
            'message' => 'PTP berhasil dibuat untuk ' . $customer->name,
            'ptp' => $customer->promise_to_pay,
        ]);
    }

    // ============ COLLECTION BANKING METHODS ============

    public function collectionDashboard(Request $request)
    {
        $this->authorizeAccess();

        // Bucket Aging Summary
        $bucketSummary = Customer::selectRaw('
            COALESCE(bucket, "Unknown") as bucket,
            COUNT(*) as count,
            SUM(total_amount) as total_amount,
            SUM(paid_amount) as paid_amount,
            SUM(total_amount - paid_amount - discount_amount) as remaining_amount,
            AVG(days_past_due) as avg_dpd
        ')
            ->where('total_amount', '>', 0)
            ->groupBy('bucket')
            ->orderByRaw("CASE 
                WHEN bucket = 'Current' THEN 1
                WHEN bucket = 'Bucket 1' THEN 2
                WHEN bucket = 'Bucket 2' THEN 3
                WHEN bucket = 'Bucket 3' THEN 4
                WHEN bucket = 'NPL' THEN 5
                ELSE 6 END")
            ->get();

        // Risk Level Distribution
        $riskSummary = Customer::selectRaw('risk_level, COUNT(*) as count, SUM(total_amount) as exposure')
            ->where('total_amount', '>', 0)
            ->groupBy('risk_level')
            ->orderByRaw("CASE risk_level 
                WHEN 'critical' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
                ELSE 5 END")
            ->get();

        // Campaign Performance
        $campaignPerformance = Customer::selectRaw('
            c.name as campaign_name, c.type,
            COUNT(customers.id) as total_cases,
            SUM(customers.total_amount) as portfolio_value,
            SUM(customers.paid_amount) as collected,
            SUM(customers.total_amount - customers.paid_amount - customers.discount_amount) as outstanding,
            COUNT(CASE WHEN customers.payment_status = "paid" THEN 1 END) as closed_count
        ')
            ->leftJoin('campaigns as c', 'customers.campaign_id', '=', 'c.id')
            ->where('customers.total_amount', '>', 0)
            ->whereNotNull('customers.campaign_id')
            ->groupBy('c.id', 'c.name', 'c.type')
            ->get();

        // Collector Performance
        $collectorPerformance = Customer::selectRaw('
            a.name as collector_name, a.extension,
            COUNT(customers.id) as assigned_cases,
            SUM(customers.total_amount) as portfolio_value,
            SUM(customers.paid_amount) as collected,
            SUM(customers.total_amount - customers.paid_amount - customers.discount_amount) as outstanding,
            COUNT(CASE WHEN customers.payment_status = "paid" THEN 1 END) as closed_count,
            AVG(CASE WHEN customers.total_amount > 0 THEN ((customers.paid_amount + customers.discount_amount) / customers.total_amount) * 100 ELSE 0 END) as avg_collection_rate
        ')
            ->leftJoin('agents as a', 'customers.collector_id', '=', 'a.id')
            ->where('customers.total_amount', '>', 0)
            ->whereNotNull('customers.collector_id')
            ->groupBy('a.id', 'a.name', 'a.extension')
            ->orderByDesc('collected')
            ->get();

        // PTP Summary
        $ptpStats = [
            'active' => Customer::whereJsonContains('promise_to_pay->status', 'pending')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(promise_to_pay, '$.date')) >= ?", [now()->toDateString()])
                ->count(),
            'kept_today' => Customer::whereJsonContains('promise_to_pay->status', 'kept')
                ->whereRaw("DATE(JSON_UNQUOTE(JSON_EXTRACT(promise_to_pay, '$.kept_at'))) = ?", [now()->toDateString()])
                ->count(),
            'broken_today' => Customer::whereJsonContains('promise_to_pay->status', 'broken')
                ->whereRaw("DATE(JSON_UNQUOTE(JSON_EXTRACT(promise_to_pay, '$.broken_at'))) = ?", [now()->toDateString()])
                ->count(),
            'overdue' => Customer::whereJsonContains('promise_to_pay->status', 'pending')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(promise_to_pay, '$.date')) < ?", [now()->toDateString()])
                ->count(),
        ];

        // Upcoming Due (next 7 days)
        $upcomingDue = Customer::dueSoon(7)
            ->with('collector', 'campaign')
            ->orderBy('due_date')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'due_date', 'total_amount', 'paid_amount', 'discount_amount', 'collector_id', 'campaign_id'])
            ->map(function ($c) {
                $c->remaining_amount = max(0, $c->total_amount - $c->paid_amount - $c->discount_amount);
                return $c;
            });

        // Top NPL Cases
        $topNPL = Customer::where('bucket', 'NPL')
            ->where('total_amount', '>', 0)
            ->orderByDesc('total_amount')
            ->limit(15)
            ->with('collector', 'campaign')
            ->get(['id', 'name', 'phone', 'total_amount', 'paid_amount', 'discount_amount', 'days_past_due', 'collector_id', 'campaign_id'])
            ->map(function ($c) {
                $c->remaining_amount = max(0, $c->total_amount - $c->paid_amount - $c->discount_amount);
                return $c;
            });

        // SLA Breach Preview (tanpa update, hanya hitung)
        $slaRules = \App\Models\SlaRule::where('is_active', true)->orderBy('max_dpd')->get();
        $slaBreaches = [];
        foreach ($slaRules as $rule) {
            $count = Customer::where('bucket', $rule->bucket)
                ->where('days_past_due', '>', $rule->max_dpd)
                ->where('payment_status', '!=', 'paid')
                ->count();
            if ($count > 0) {
                $slaBreaches[] = [
                    'bucket' => $rule->bucket,
                    'max_dpd' => $rule->max_dpd,
                    'count' => $count,
                    'action' => $rule->action_note,
                ];
            }
        }

        return view('crm.collection.dashboard', compact(
            'bucketSummary', 'riskSummary', 'campaignPerformance',
            'collectorPerformance', 'ptpStats', 'upcomingDue', 'topNPL',
            'slaBreaches'
        ));
    }

    public function slaCheck(Request $request)
    {
        $this->authorizeAccess();

        // Recalculate dulu supaya DPD/bucket fresh
        foreach (Customer::whereNotNull('due_date')->get() as $customer) {
            $customer->recalculateBucket();
        }

        $rules = \App\Models\SlaRule::where('is_active', true)->get();
        $breaches = [];
        $escalated = 0;

        $rank = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

        foreach ($rules as $rule) {
            $cases = Customer::where('bucket', $rule->bucket)
                ->where('days_past_due', '>', $rule->max_dpd)
                ->where('payment_status', '!=', 'paid')
                ->get(['id', 'risk_level']);

            if ($cases->isEmpty()) continue;

            $ids = [];
            foreach ($cases as $c) {
                $ids[] = $c->id;
                $cur = $rank[$c->risk_level] ?? 0;
                $target = $rank[$rule->escalate_risk] ?? 0;
                if ($target > $cur) {
                    $c->update(['risk_level' => $rule->escalate_risk]);
                    $escalated++;
                }
            }

            $breaches[] = [
                'bucket' => $rule->bucket,
                'max_dpd' => $rule->max_dpd,
                'count' => count($ids),
                'action' => $rule->action_note,
                'ids' => array_slice($ids, 0, 50),
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'SLA check selesai: ' . count($breaches) . ' bucket breach, ' . $escalated . ' case dieskalasi.',
            'escalated' => $escalated,
            'breaches' => $breaches,
        ]);
    }

    public function agingReport(Request $request)
    {
        $this->authorizeAccess();

        $buckets = ['Current', 'Bucket 1', 'Bucket 2', 'Bucket 3', 'NPL'];
        $data = [];

        foreach ($buckets as $bucket) {
            $cases = Customer::when($bucket !== 'Current', function ($q) use ($bucket) {
                    $q->where('bucket', $bucket);
                }, function ($q) {
                    $q->where(function ($sq) {
                        $sq->whereNull('bucket')->orWhere('bucket', 'Current');
                    });
                })
                ->where('total_amount', '>', 0)
                ->with('collector', 'campaign')
                ->orderByDesc('total_amount')
                ->paginate(20, ['*'], $bucket . '_page');

            $data[$bucket] = $cases;
        }

        return view('crm.collection.aging', compact('data', 'buckets'));
    }

    public function ptpManagement(Request $request)
    {
        $this->authorizeAccess();

        $filter = $request->get('filter', 'active'); // active, kept, broken, overdue, all

        $query = Customer::whereNotNull('promise_to_pay')
            ->where('total_amount', '>', 0)
            ->with('collector', 'campaign');

        switch ($filter) {
            case 'active':
                $query->whereJsonContains('promise_to_pay->status', 'pending')
                      ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(promise_to_pay, '$.date')) >= ?", [now()->toDateString()]);
                break;
            case 'kept':
                $query->whereJsonContains('promise_to_pay->status', 'kept');
                break;
            case 'broken':
                $query->whereJsonContains('promise_to_pay->status', 'broken');
                break;
            case 'overdue':
                $query->whereJsonContains('promise_to_pay->status', 'pending')
                      ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(promise_to_pay, '$.date')) < ?", [now()->toDateString()]);
                break;
        }

        $ptps = $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(promise_to_pay, '$.date')) ASC")
            ->paginate(20)->withQueryString();

        return view('crm.collection.ptp', compact('ptps', 'filter'));
    }

    public function setPTP(Request $request, Customer $customer)
    {
        $this->authorizeAccess();

        $request->validate([
            'ptp_amount' => 'required|numeric|min:1',
            'ptp_date' => 'required|date|after_or_equal:today',
            'ptp_note' => 'nullable|string',
        ]);

        $customer->setPromiseToPay(
            $request->ptp_amount,
            $request->ptp_date,
            $request->ptp_note
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Promise to Pay berhasil dibuat',
            'ptp' => $customer->promise_to_pay,
        ]);
    }

    public function updatePTPStatus(Request $request, Customer $customer, $action)
    {
        $this->authorizeAccess();

        if ($action === 'kept') {
            $customer->markPromiseKept();
            $msg = 'PTP ditandai DITEPIL';
        } elseif ($action === 'broken') {
            $customer->markPromiseBroken();
            $msg = 'PTP ditandai BATAL';
        } else {
            return response()->json(['status' => 'error', 'message' => 'Invalid action'], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => $msg,
            'ptp' => $customer->promise_to_pay,
        ]);
    }

    public function recalculateBuckets(Request $request)
    {
        $this->authorizeAccess();

        $customers = Customer::whereNotNull('due_date')->get();
        $updated = 0;

        foreach ($customers as $customer) {
            $oldBucket = $customer->bucket;
            $customer->recalculateBucket();
            if ($customer->bucket !== $oldBucket) $updated++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Bucket recalculated. {$updated} cases updated.",
        ]);
    }

    public function bulkAssignCampaign(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'exists:customers,id',
            'campaign_id' => 'required|exists:campaigns,id',
            'collector_id' => 'nullable|exists:agents,id',
        ]);

        $updated = Customer::whereIn('id', $request->customer_ids)
            ->update([
                'campaign_id' => $request->campaign_id,
                'collector_id' => $request->collector_id,
            ]);

        return response()->json([
            'status' => 'success',
            'message' => "{$updated} cases assigned to campaign",
        ]);
    }

    public function markHandoverReady(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'exists:customers,id',
        ]);

        $updated = Customer::whereIn('id', $request->customer_ids)
            ->where('handover_status', '!=', 'handed_over')
            ->update(['handover_status' => 'ready']);

        return response()->json([
            'status' => 'success',
            'message' => "{$updated} case ditandai siap handover.",
        ]);
    }

    public function handoverSubmit(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'exists:customers,id',
            'handover_to' => 'required|string|max:255',
            'handover_date' => 'nullable|date',
            'handover_notes' => 'nullable|string|max:2000',
        ]);

        $updated = Customer::whereIn('id', $request->customer_ids)->update([
            'handover_status' => 'handed_over',
            'handover_to' => $request->handover_to,
            'handover_date' => $request->handover_date ?: now()->toDateString(),
            'handover_notes' => $request->handover_notes,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "{$updated} case diserahkan ke {$request->handover_to}.",
        ]);
    }

    public function handoverRecall(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'exists:customers,id',
        ]);

        $updated = Customer::whereIn('id', $request->customer_ids)
            ->where('handover_status', 'handed_over')
            ->update(['handover_status' => 'returned']);

        return response()->json([
            'status' => 'success',
            'message' => "{$updated} case ditarik kembali (returned).",
        ]);
    }

    public function exportHandover(Request $request)
    {
        $this->authorizeAccess();

        $query = Customer::with(['collector', 'campaign', 'assignedAgent'])
            ->when($request->filled('handover_status'), fn($q) => $q->where('handover_status', $request->handover_status))
            ->when(!$request->filled('handover_status'), fn($q) => $q->whereIn('handover_status', ['ready', 'handed_over']))
            ->when($request->filled('handover_to'), fn($q) => $q->where('handover_to', 'like', '%' . $request->handover_to . '%'))
            ->when($request->boolean('bad_debt_only'), fn($q) => $q->badDebt());

        $rows = $query->orderByDesc('total_amount')->get()->map(function ($c) {
            $ptp = $c->promise_to_pay ?? [];
            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'email' => $c->email,
                'company' => $c->company,
                'bucket' => $c->bucket,
                'days_past_due' => $c->days_past_due,
                'due_date' => $c->due_date?->toDateString(),
                'total_amount' => (float) $c->total_amount,
                'paid_amount' => (float) $c->paid_amount,
                'discount_amount' => (float) $c->discount_amount,
                'remaining_amount' => (float) max(0, $c->total_amount - $c->paid_amount - $c->discount_amount),
                'payment_status' => $c->payment_status,
                'risk_level' => $c->risk_level,
                'ptp_status' => $ptp['status'] ?? null,
                'ptp_amount' => isset($ptp['amount']) ? (float) $ptp['amount'] : null,
                'ptp_date' => $ptp['date'] ?? null,
                'ptp_note' => $ptp['note'] ?? null,
                'campaign' => $c->campaign?->code,
                'collector' => $c->collector?->name,
                'assigned_agent' => $c->assignedAgent?->name,
                'handover_status' => $c->handover_status,
                'handover_to' => $c->handover_to,
                'handover_date' => $c->handover_date,
                'handover_notes' => $c->handover_notes,
                'notes' => $c->notes,
                'payment_notes' => $c->payment_notes,
            ];
        });

        $filename = 'handover_' . now()->format('Ymd_His') . '.xlsx';

        return (new FastExcel($rows))->download($filename);
    }

    public function autoAssignCampaigns(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'campaign_id' => 'nullable|exists:campaigns,id',
            'only_unassigned' => 'nullable|boolean',
            'distribute_collectors' => 'nullable|boolean',
        ]);

        $onlyUnassigned = $request->boolean('only_unassigned', true);
        $distribute = $request->boolean('distribute_collectors', true);

        $campaigns = $request->filled('campaign_id')
            ? \App\Models\Campaign::where('id', $request->campaign_id)->active()->get()
            : \App\Models\Campaign::active()->get();

        if ($campaigns->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Tidak ada campaign aktif'], 422);
        }

        $collectors = Agent::where('role', 'agent')->orderBy('id')->get(['id']);
        $collectorIds = $collectors->pluck('id')->values();
        $roundRobin = 0;

        $result = [];
        $total = 0;

        foreach ($campaigns as $campaign) {
            $buckets = $campaign->target_buckets ?: [];
            if (empty($buckets)) continue;

            $q = Customer::whereIn('bucket', $buckets)
                ->where('payment_status', '!=', 'paid');
            if ($onlyUnassigned) {
                $q->whereNull('campaign_id');
            }

            $customers = $q->orderBy('id')->get(['id', 'collector_id']);
            $count = 0;

            foreach ($customers as $customer) {
                $update = ['campaign_id' => $campaign->id];
                if ($distribute && !$customer->collector_id && $collectorIds->isNotEmpty()) {
                    $update['collector_id'] = $collectorIds[$roundRobin % $collectorIds->count()];
                    $roundRobin++;
                }
                $customer->update($update);
                $count++;
            }

            $result[] = ['campaign' => $campaign->code, 'assigned' => $count];
            $total += $count;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Auto-assign selesai: {$total} case ke " . count($result) . " campaign.",
            'total' => $total,
            'detail' => $result,
        ]);
    }

    protected function filteredCustomerQuery(Request $request)
    {
        $query = Customer::with(['assignedAgent', 'collector', 'campaign']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }
        if ($request->filled('status')) {
            $query->status($request->status);
        }
        if ($request->filled('agent_id')) {
            $query->assignedTo($request->agent_id);
        }
        if ($request->filled('payment_status')) {
            $query->paymentStatus($request->payment_status);
        }
        if ($request->filled('bucket')) {
            $query->bucket($request->bucket);
        }
        if ($request->filled('campaign_id')) {
            $query->inCampaign($request->campaign_id);
        }

        return $query;
    }

    public function exportCustomers(Request $request)
    {
        $this->authorizeAccess();

        $customers = $this->filteredCustomerQuery($request)->orderBy('id')->get();

        $rows = $customers->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'email' => $c->email,
                'company' => $c->company,
                'status' => $c->status,
                'bucket' => $c->bucket,
                'days_past_due' => $c->days_past_due,
                'due_date' => $c->due_date?->toDateString(),
                'total_amount' => (float) $c->total_amount,
                'paid_amount' => (float) $c->paid_amount,
                'discount_amount' => (float) $c->discount_amount,
                'remaining_amount' => (float) max(0, $c->total_amount - $c->paid_amount - $c->discount_amount),
                'payment_status' => $c->payment_status,
                'payment_notes' => $c->payment_notes,
                'last_payment_date' => $c->last_payment_date?->toDateTimeString(),
                'campaign' => $c->campaign?->code,
                'collector' => $c->collector?->extension,
                'assigned_agent' => $c->assignedAgent?->extension,
                'risk_level' => $c->risk_level,
                'notes' => $c->notes,
                'last_contacted_at' => $c->last_contacted_at?->toDateTimeString(),
            ];
        });

        $filename = 'customers_' . now()->format('Ymd_His') . '.xlsx';

        return (new FastExcel($rows))->download($filename);
    }

    public function importCustomers(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $imported = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        try {
            $collection = (new FastExcel)->import($request->file('file'));
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membaca file: ' . $e->getMessage(),
            ], 422);
        }

        $rowNumber = 1; // header = baris 1
        $validStatuses = ['new', 'contacted', 'qualified', 'proposal', 'closed_won', 'closed_lost'];
        $validPayStatuses = ['unpaid', 'partial', 'paid'];

        foreach ($collection as $row) {
            $rowNumber++;
            $norm = [];
            foreach ($row as $k => $v) {
                $norm[strtolower(trim((string) $k))] = is_string($v) ? trim($v) : $v;
            }

            $name = $norm['name'] ?? null;
            $phone = $norm['phone'] ?? null;

            if (!$name || !$phone) {
                $failed++;
                if (count($errors) < 20) $errors[] = "Baris {$rowNumber}: name & phone wajib diisi";
                continue;
            }

            try {
                $status = in_array($norm['status'] ?? '', $validStatuses) ? $norm['status'] : 'new';
                $payStatus = in_array($norm['payment_status'] ?? '', $validPayStatuses) ? $norm['payment_status'] : null;

                $total = is_numeric($norm['total_amount'] ?? null) ? (float) $norm['total_amount'] : 0;
                $paid = is_numeric($norm['paid_amount'] ?? null) ? (float) $norm['paid_amount'] : 0;
                $disc = is_numeric($norm['discount_amount'] ?? null) ? (float) $norm['discount_amount'] : 0;

                $dueDate = null;
                if (!empty($norm['due_date'])) {
                    try {
                        $dueDate = \Carbon\Carbon::parse($norm['due_date'])->toDateString();
                    } catch (\Throwable $e) {
                        $dueDate = null;
                    }
                }

                if (!$payStatus) {
                    $remaining = max(0, $total - $paid - $disc);
                    $payStatus = ($remaining <= 0 && $total > 0) ? 'paid' : (($paid > 0 || $disc > 0) ? 'partial' : 'unpaid');
                }

                $existing = Customer::where('phone', $phone)->first();

                $payload = [
                    'name' => $name,
                    'email' => $norm['email'] ?? null,
                    'company' => $norm['company'] ?? null,
                    'status' => $status,
                    'notes' => $norm['notes'] ?? null,
                    'total_amount' => $total,
                    'paid_amount' => $paid,
                    'discount_amount' => $disc,
                    'payment_status' => $payStatus,
                    'payment_notes' => $norm['payment_notes'] ?? null,
                    'due_date' => $dueDate,
                ];

                if ($existing) {
                    $existing->update($payload);
                    if ($dueDate) $existing->recalculateBucket();
                    $updated++;
                } else {
                    $payload['phone'] = $phone;
                    $payload['created_by'] = $this->getCurrentUserId();
                    $customer = Customer::create($payload);
                    if ($dueDate) $customer->recalculateBucket();
                    $imported++;
                }
            } catch (\Throwable $e) {
                $failed++;
                if (count($errors) < 20) $errors[] = "Baris {$rowNumber}: " . $e->getMessage();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Import selesai: {$imported} baru, {$updated} update, {$failed} gagal.",
            'imported' => $imported,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
        ]);
    }

    public function exportAging(Request $request)
    {
        $this->authorizeAccess();

        $query = Customer::with(['collector', 'campaign'])
            ->where('total_amount', '>', 0);

        if ($request->filled('bucket')) {
            if ($request->bucket === 'Current') {
                $query->where(function ($q) {
                    $q->whereNull('bucket')->orWhere('bucket', 'Current');
                });
            } else {
                $query->where('bucket', $request->bucket);
            }
        }

        $rows = $query->orderByDesc('total_amount')->get()->map(function ($c) {
            return [
                'bucket' => $c->bucket ?? 'Current',
                'days_past_due' => $c->days_past_due,
                'due_date' => $c->due_date?->toDateString(),
                'name' => $c->name,
                'phone' => $c->phone,
                'company' => $c->company,
                'total_amount' => (float) $c->total_amount,
                'paid_amount' => (float) $c->paid_amount,
                'discount_amount' => (float) $c->discount_amount,
                'remaining_amount' => (float) max(0, $c->total_amount - $c->paid_amount - $c->discount_amount),
                'payment_status' => $c->payment_status,
                'risk_level' => $c->risk_level,
                'campaign' => $c->campaign?->code,
                'collector' => $c->collector?->name,
                'collector_ext' => $c->collector?->extension,
            ];
        });

        $suffix = $request->filled('bucket') ? strtolower(str_replace(' ', '_', $request->bucket)) . '_' : '';
        $filename = 'aging_' . $suffix . now()->format('Ymd_His') . '.xlsx';

        return (new FastExcel($rows))->download($filename);
    }
}