@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Promise to Pay (PTP) Management</h1>
            <p class="text-slate-500 mt-1">Kelola janji bayar customer</p>
        </div>
        <a href="{{ route('crm.collection.dashboard') }}" class="bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Filter Tabs -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-2">
        <div class="flex flex-wrap gap-1">
            @foreach(['active' => 'Active', 'overdue' => 'Overdue', 'kept' => 'Kept', 'broken' => 'Broken', 'all' => 'All'] as $key => $label)
                <a href="{{ route('crm.collection.ptp', ['filter' => $key]) }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap
                       {{ $filter === $key 
                           ? 'bg-brand-600 text-white shadow-sm' 
                           : 'text-slate-600 hover:bg-slate-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <!-- PTP Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-green-500">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-green-50 text-green-600 flex items-center justify-center">
                    <i class="fa-solid fa-handshake text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Active PTP</p>
                    <p class="text-2xl font-bold text-green-700">{{ $ptps->where('promise_to_pay.status', 'pending')->where('promise_to_pay.date', '>=', now()->toDateString())->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-red-500">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center">
                    <i class="fa-solid fa-clock text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Overdue PTP</p>
                    <p class="text-2xl font-bold text-red-700">{{ $ptps->where('promise_to_pay.status', 'pending')->where('promise_to_pay.date', '<', now()->toDateString())->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-emerald-500">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="fa-solid fa-check-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Kept</p>
                    <p class="text-2xl font-bold text-emerald-700">{{ $ptps->where('promise_to_pay.status', 'kept')->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-rose-500">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                    <i class="fa-solid fa-xmark-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Broken</p>
                    <p class="text-2xl font-bold text-rose-700">{{ $ptps->where('promise_to_pay.status', 'broken')->count() }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- PTP Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Debtor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Phone</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">PTP Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">PTP Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Outstanding</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Collector</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Note</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($ptps as $c)
                        @php
                            $ptp = $c->promise_to_pay;
                            $isOverdue = $ptp['status'] === 'pending' && $ptp['date'] < now()->toDateString();
                        @endphp
                        <tr class="hover:bg-slate-50 {{ $isOverdue ? 'bg-red-50' : '' }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $c->name }}</div>
                                <div class="text-sm text-slate-500 font-mono">{{ $c->phone }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono">{{ $c->phone }}</td>
                            <td class="px-4 py-3 font-mono text-brand-700 font-semibold">Rp {{ number_format($ptp['amount'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3">
                                <div class="font-mono text-slate-700">{{ \Carbon\Carbon::parse($ptp['date'])->format('d M Y') }}</div>
                                @if($isOverdue)
                                    <span class="inline-block mt-1 px-1.5 py-0.5 bg-red-100 text-red-700 text-[10px] rounded">OVERDUE</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium border"
                                    @class([
                                        'bg-yellow-50 text-yellow-700 border-yellow-200' => $ptp['status'] === 'pending' && !$isOverdue,
                                        'bg-red-50 text-red-700 border-red-200' => $isOverdue,
                                        'bg-green-50 text-green-700 border-green-200' => $ptp['status'] === 'kept',
                                        'bg-rose-50 text-rose-700 border-rose-200' => $ptp['status'] === 'broken',
                                    ])>
                                    @if($ptp['status'] === 'pending' && !$isOverdue) Active
                                    @elseif($isOverdue) Overdue
                                    @elseif($ptp['status'] === 'kept') Kept
                                    @else Broken
                                    @endif
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-red-600">Rp {{ number_format($c->remaining_amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3">
                                @if($c->collector)
                                    <span class="text-brand-700 font-medium">{{ $c->collector->name }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-sm max-w-xs truncate">{{ $ptp['note'] ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    @if($ptp['status'] === 'pending')
                                        <button onclick="updatePTP({{ $c->id }}, 'kept')" 
                                                class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs rounded transition-colors"
                                                title="Mark Kept">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                        <button onclick="updatePTP({{ $c->id }}, 'broken')" 
                                                class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white text-xs rounded transition-colors"
                                                title="Mark Broken">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    @endif
                                    <button onclick="editPTP({{ $c->id }})" 
                                            class="px-2.5 py-1 bg-slate-600 hover:bg-slate-700 text-white text-xs rounded transition-colors"
                                            title="Edit PTP">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @if($ptps->isEmpty())
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-slate-400">
                                <i class="fa-solid fa-handshake text-3xl mb-2 block text-slate-300"></i>
                                Tidak ada PTP
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($ptps->hasPages())
            <div class="p-4 border-t border-slate-200 flex justify-center">
                {{ $ptps->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
function updatePTP(customerId, action) {
    const confirmMsg = action === 'kept' 
        ? 'Tandai PTP ini sebagai DITEPIL (customer sudah bayar)?' 
        : 'Tandai PTP ini sebagai BATAL (customer tidak bayar)?';
    
    if (!confirm(confirmMsg)) return;

    fetch(`/dashboard/crm/customers/${customerId}/ptp/${action}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            window.location.reload();
        } else {
            alert(data.message || 'Error');
        }
    })
    .catch(() => alert('Terjadi kesalahan'));
}

function editPTP(customerId) {
    // Open modal to edit PTP - implement as needed
    alert('Edit PTP feature - coming soon');
}
</script>
@endsection