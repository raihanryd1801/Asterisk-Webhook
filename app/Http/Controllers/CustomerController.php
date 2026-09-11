<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Agent;
use App\Models\DebtCollector;
use Illuminate\Support\Facades\Auth;
use Rap2hpoutre\FastExcel\FastExcel;

class CustomerController extends Controller
{
    protected function getCurrentUserId()
    {
        // created_by merujuk ke tabel users -> hanya isi ID user asli.
        // Supervisor login via session agent tidak punya users.id, jadi null
        // (sebelumnya mengisi ID agent -> FK violation 1452).
        if (Auth::check()) {
            return Auth::id();
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
        
        $query = Customer::with(['assignedAgent', 'collector', 'creator']);

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
        $collectors = DebtCollector::active()->orderBy('name')->get(['id', 'name', 'phone', 'type']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($customers);
        }

        return view('crm.customers.index', compact('customers', 'agents', 'statuses', 'paymentStatuses', 'buckets', 'collectors'));
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
            'collector_id' => 'nullable|exists:debt_collectors,id',
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
            'collector_id' => 'nullable|exists:debt_collectors,id',
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

        $customers = Customer::where('assigned_agent_id', $agent->id)
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

        if ($customer->assigned_agent_id !== $agent->id) {
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

        // Collector Performance
        $collectorPerformance = Customer::selectRaw('
            d.name as collector_name, d.phone as collector_phone, d.type as collector_type,
            COUNT(customers.id) as assigned_cases,
            SUM(customers.total_amount) as portfolio_value,
            SUM(customers.paid_amount) as collected,
            SUM(customers.total_amount - customers.paid_amount - customers.discount_amount) as outstanding,
            COUNT(CASE WHEN customers.payment_status = "paid" THEN 1 END) as closed_count,
            AVG(CASE WHEN customers.total_amount > 0 THEN ((customers.paid_amount + customers.discount_amount) / customers.total_amount) * 100 ELSE 0 END) as avg_collection_rate
        ')
            ->leftJoin('debt_collectors as d', 'customers.collector_id', '=', 'd.id')
            ->where('customers.total_amount', '>', 0)
            ->whereNotNull('customers.collector_id')
            ->groupBy('d.id', 'd.name', 'd.phone', 'd.type')
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
            ->with('collector')
            ->orderBy('due_date')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'due_date', 'total_amount', 'paid_amount', 'discount_amount', 'collector_id'])
            ->map(function ($c) {
                $c->remaining_amount = max(0, $c->total_amount - $c->paid_amount - $c->discount_amount);
                return $c;
            });

        // Top NPL Cases
        $topNPL = Customer::where('bucket', 'NPL')
            ->where('total_amount', '>', 0)
            ->orderByDesc('total_amount')
            ->limit(15)
            ->with('collector')
            ->get(['id', 'name', 'phone', 'total_amount', 'paid_amount', 'discount_amount', 'days_past_due', 'collector_id'])
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
            'bucketSummary', 'riskSummary',
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

    public function buckets(Request $request)
    {
        $buckets = Customer::selectRaw('
            COALESCE(bucket, "Tanpa Bucket") as bucket,
            COUNT(*) as count,
            SUM(total_amount) as total_amount,
            SUM(paid_amount) as paid_amount,
            SUM(total_amount - paid_amount - discount_amount) as remaining_amount,
            AVG(days_past_due) as avg_dpd
        ')
            ->groupBy('bucket')
            ->orderByRaw("CASE COALESCE(bucket, '')
                WHEN 'Current' THEN 1
                WHEN 'Bucket 1' THEN 2
                WHEN 'Bucket 2' THEN 3
                WHEN 'Bucket 3' THEN 4
                WHEN 'NPL' THEN 5
                ELSE 6 END")
            ->get();

        $ranges = \App\Models\BucketRange::allRules();

        return view('crm.collection.buckets', compact('buckets', 'ranges'));
    }

    public function updateBucketRanges(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'ranges' => 'required|array|min:1',
            'ranges.*.bucket' => 'required|string|max:50',
            'ranges.*.min_dpd' => 'required|integer|min:0|max:3650',
            'ranges.*.max_dpd' => 'nullable|integer|min:0|max:3650',
            'ranges.*.risk_level' => 'required|in:low,medium,high,critical',
        ]);

        $ranges = collect($request->ranges)->values();
        $unbounded = $ranges->whereNull('max_dpd');

        if ($unbounded->count() !== 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Harus ada tepat 1 bucket tanpa batas atas (untuk DPD terbesar, mis. NPL).',
            ], 422);
        }

        if ($unbounded->keys()->first() !== $ranges->count() - 1) {
            // Pindahkan yang unbounded ke akhir agar konsisten
            $ranges = $ranges->reject(fn($r) => $r['max_dpd'] === null)->values()->push($unbounded->first());
        }

        // Validasi tiap baris + tidak boleh overlap/terbalik
        $prevMax = null;
        foreach ($ranges as $i => $r) {
            if ($r['max_dpd'] !== null && $r['min_dpd'] > $r['max_dpd']) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Baris '{$r['bucket']}': min DPD tidak boleh lebih besar dari max DPD.",
                ], 422);
            }
            if ($prevMax !== null && $r['min_dpd'] <= $prevMax) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Rentang '{$r['bucket']}' overlap dengan baris sebelumnya. Urutkan min DPD menaik tanpa tumpang tindih.",
                ], 422);
            }
            $prevMax = $r['max_dpd'];
        }

        foreach ($ranges as $i => $r) {
            \App\Models\BucketRange::updateOrCreate(
                ['bucket' => $r['bucket']],
                [
                    'min_dpd' => $r['min_dpd'],
                    'max_dpd' => $r['max_dpd'],
                    'risk_level' => $r['risk_level'],
                    'sort' => $i + 1,
                ]
            );
        }
        // Hapus bucket yang tidak ada di daftar baru
        \App\Models\BucketRange::whereNotIn('bucket', $ranges->pluck('bucket'))->delete();
        \App\Models\BucketRange::forgetCache();

        return response()->json([
            'status' => 'success',
            'message' => 'Rentang DPD tersimpan. Klik Recalculate Bucket agar case ikut aturan baru.',
        ]);
    }

    public function ptpManagement(Request $request)
    {
        $this->authorizeAccess();

        $filter = $request->get('filter', 'active'); // active, kept, broken, overdue, all

        $query = Customer::whereNotNull('promise_to_pay')
            ->where('total_amount', '>', 0)
            ->with('collector');

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

        $customers = Customer::whereNotNull('due_date')->get(['id', 'name', 'phone', 'bucket', 'due_date']);
        $updated = 0;
        $changes = [];

        foreach ($customers as $customer) {
            $oldBucket = $customer->bucket;
            $customer->recalculateBucket();
            if ($customer->bucket !== $oldBucket) {
                $updated++;
                if (count($changes) < 200) {
                    $changes[] = [
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'from' => $oldBucket ?: '-',
                        'to' => $customer->bucket ?: '-',
                    ];
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Bucket recalculated. {$updated} dari {$customers->count()} cases pindah bucket.",
            'checked' => $customers->count(),
            'updated' => $updated,
            'changes' => $changes,
            'truncated' => $updated > count($changes),
        ]);
    }

    public function bulkAssignCollector(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'exists:customers,id',
            'collector_id' => 'required|exists:debt_collectors,id',
        ]);

        $collector = DebtCollector::find($request->collector_id);

        $updated = Customer::whereIn('id', $request->customer_ids)
            ->update(['collector_id' => $request->collector_id]);

        return response()->json([
            'status' => 'success',
            'message' => "{$updated} cases assigned ke {$collector->name}.",
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

        $query = Customer::with(['collector', 'assignedAgent'])
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

    public function autoAssignCollectors(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'buckets' => 'nullable|array',
            'buckets.*' => 'string|max:50',
            'only_unassigned' => 'nullable|boolean',
        ]);

        $onlyUnassigned = $request->boolean('only_unassigned', true);

        $buckets = $request->filled('buckets')
            ? $request->buckets
            : \App\Models\BucketRange::orderBy('sort')->pluck('bucket')->toArray();

        $collectors = DebtCollector::active()->orderBy('id')->get(['id', 'name']);
        $collectorIds = $collectors->pluck('id')->values();

        if ($collectorIds->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Belum ada debt collector aktif. Tambahkan dulu di menu Debt Collectors.'], 422);
        }

        $collectorNames = $collectors->pluck('name', 'id')->toArray();

        $result = [];
        $assignments = [];
        $total = 0;
        $roundRobin = 0;

        foreach ($buckets as $bucket) {
            $q = Customer::where('bucket', $bucket)
                ->where('payment_status', '!=', 'paid');
            if ($onlyUnassigned) {
                $q->whereNull('collector_id');
            }

            $customers = $q->orderBy('id')->get(['id', 'name', 'phone', 'bucket']);
            $count = 0;

            foreach ($customers as $customer) {
                $collectorId = $collectorIds[$roundRobin % $collectorIds->count()];
                $roundRobin++;
                $customer->update(['collector_id' => $collectorId]);
                $count++;

                if (count($assignments) < 200) {
                    $assignments[] = [
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'bucket' => $customer->bucket ?: '-',
                        'collector' => $collectorNames[$collectorId] ?? '-',
                    ];
                }
            }

            $result[] = ['bucket' => $bucket, 'assigned' => $count];
            $total += $count;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Auto-assign selesai: {$total} case ke collector.",
            'total' => $total,
            'detail' => $result,
            'assignments' => $assignments,
            'truncated' => $total > count($assignments),
        ]);
    }

    public function blastBucketPreview(Request $request)
    {
        $this->authorizeAccess();

        $request->validate(['bucket' => 'required|string|max:50']);

        $targets = Customer::where('bucket', $request->bucket)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNotNull('phone')
            ->count();

        $history = \App\Models\BlastLog::where('bucket', $request->bucket)
            ->latest()->limit(5)->get();

        $gateway = app(\App\Services\WhatsAppGateway::class);
        $sessionId = \App\Services\WhatsAppGateway::sessionIdForCurrentUser();
        $wa = $sessionId ? $gateway->status($sessionId) : null;

        return response()->json([
            'status' => 'success',
            'targets' => $targets,
            'history' => $history,
            'sender' => [
                'connected' => ($wa['status'] ?? null) === 'connected',
                'phone' => $wa['phone'] ?? null,
            ],
        ]);
    }

    public function blastBucketSend(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'bucket' => 'required|string|max:50',
            'channel' => 'required|in:wa',
            'message' => 'required|string|max:1000',
        ]);

        $gateway = app(\App\Services\WhatsAppGateway::class);
        $sessionId = \App\Services\WhatsAppGateway::sessionIdForCurrentUser();

        if (!$sessionId) {
            return response()->json(['status' => 'error', 'message' => 'Silakan login dulu.'], 401);
        }

        $wa = $gateway->status($sessionId);
        if (($wa['status'] ?? null) !== 'connected') {
            return response()->json(['status' => 'error', 'message' => 'WhatsApp Anda belum terhubung. Scan QR dulu di menu WhatsApp.'], 409);
        }

        $targets = Customer::where('bucket', $request->bucket)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNotNull('phone')
            ->count();

        if ($targets === 0) {
            return response()->json(['status' => 'error', 'message' => 'Tidak ada target (bucket ini belum ada case unpaid/partial bernomor)'], 422);
        }

        $senderLabel = (auth()->check() ? (auth()->user()->name . ' (admin)') : ('SPV ' . session('supervisor_extension')))
            . ' / ' . ($wa['phone'] ?? '?');

        $log = \App\Models\BlastLog::create([
            'bucket' => $request->bucket,
            'channel' => $request->channel,
            'message' => $request->message,
            'total_target' => $targets,
            'sent' => 0,
            'failed' => 0,
            'status' => 'queued',
            'note' => 'Dikirim via sesi ' . $sessionId,
            'sender' => $senderLabel,
            'created_by' => $this->getCurrentUserId(),
        ]);

        \App\Jobs\ProcessWaBlast::dispatch($log->id, $sessionId);

        return response()->json([
            'status' => 'success',
            'message' => "Blast {$targets} nomor {$request->bucket} masuk antrean dan dikirim dari nomor Anda ({$wa['phone']}). Pantau di riwayat.",
            'log' => $log,
        ]);
    }

    protected function filteredCustomerQuery(Request $request)
    {
        $query = Customer::with(['assignedAgent', 'collector']);

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
                'collector' => $c->collector?->name,
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

}