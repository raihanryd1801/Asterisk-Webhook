<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Agent;
use Illuminate\Support\Facades\Auth;

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
        
        $query = Customer::with(['assignedAgent', 'creator']);

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

        $customers = $query->latest()->paginate(15)->withQueryString();
        $agents = Agent::where('role', 'agent')->get(['id', 'name', 'extension']);
        $statuses = ['new', 'contacted', 'qualified', 'proposal', 'closed_won', 'closed_lost'];
        $paymentStatuses = ['unpaid', 'partial', 'paid'];

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($customers);
        }

        return view('crm.customers.index', compact('customers', 'agents', 'statuses', 'paymentStatuses'));
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
            'payment_status' => 'nullable|in:unpaid,partial,paid',
            'payment_notes' => 'nullable|string',
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
        ]);

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
            'payment_status' => 'nullable|in:unpaid,partial,paid',
            'payment_notes' => 'nullable|string',
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
        ]);

        // Auto-update payment status based on amounts
        $remaining = max(0, $customer->total_amount - $customer->paid_amount - $customer->discount_amount);
        if ($remaining <= 0 && $customer->total_amount > 0) {
            $customer->update(['payment_status' => 'paid', 'last_payment_date' => now()]);
        } elseif ($customer->paid_amount > 0 || $customer->discount_amount > 0) {
            $customer->update(['payment_status' => 'partial', 'last_payment_date' => now()]);
        } else {
            $customer->update(['payment_status' => 'unpaid']);
        }

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

        $customers = Customer::where('assigned_agent_id', $agent->id)
            ->whereIn('status', ['new', 'contacted', 'qualified', 'proposal'])
            ->latest()
            ->get(['id', 'name', 'phone', 'email', 'company', 'status', 'notes', 'last_contacted_at', 'total_amount', 'paid_amount', 'discount_amount', 'payment_status', 'payment_notes', 'last_payment_date']);

        return response()->json([
            'status' => 'success',
            'data' => $customers
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

        return view('crm.collection.dashboard', compact(
            'bucketSummary', 'riskSummary', 'campaignPerformance',
            'collectorPerformance', 'ptpStats', 'upcomingDue', 'topNPL'
        ));
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
}