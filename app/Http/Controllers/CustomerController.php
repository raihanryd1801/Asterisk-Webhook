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
}