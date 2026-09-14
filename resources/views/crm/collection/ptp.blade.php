@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-6">
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-handshake text-brand-600"></i> Promise to Pay (PTP) Management
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Kelola janji bayar customer.</p>
        </div>
        <a href="{{ route('crm.collection.dashboard') }}" class="inline-flex items-center gap-2 bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Filter Tabs -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-2">
        <div class="flex flex-wrap gap-1">
            @foreach(['new' => 'New', 'overdue' => 'Overdue', 'kept' => 'Kept', 'rolling' => 'Rolling', 'all' => 'All'] as $key => $label)
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
                    <p class="text-xs text-slate-500 font-medium">New PTP</p>
                    <p class="text-2xl font-bold text-green-700">{{ $ptps->whereIn('promise_to_pay.status', ['new', 'pending'])->where('promise_to_pay.date', '>=', now()->toDateString())->count() }}</p>
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
                    <p class="text-2xl font-bold text-red-700">{{ $ptps->whereIn('promise_to_pay.status', ['new', 'pending'])->where('promise_to_pay.date', '<', now()->toDateString())->count() }}</p>
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
                    <p class="text-xs text-slate-500 font-medium">Rolling</p>
                    <p class="text-2xl font-bold text-rose-700">{{ $ptps->whereIn('promise_to_pay.status', ['rolling', 'broken'])->count() }}</p>
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
                            $ptpStatus = $ptp['status'] === 'pending' ? 'new' : ($ptp['status'] === 'broken' ? 'rolling' : $ptp['status']);
                            $isOverdue = $ptpStatus === 'new' && $ptp['date'] < now()->toDateString();
                            $isRolling = $ptpStatus === 'rolling';
                            $extendUsed = ($ptp['extend_count'] ?? 0) >= 1;
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
                                        'bg-yellow-50 text-yellow-700 border-yellow-200' => $ptpStatus === 'new' && !$isOverdue,
                                        'bg-red-50 text-red-700 border-red-200' => $isOverdue,
                                        'bg-green-50 text-green-700 border-green-200' => $ptpStatus === 'kept',
                                        'bg-rose-50 text-rose-700 border-rose-200' => $isRolling,
                                    ])>
                                    @if($ptpStatus === 'new' && !$isOverdue) New
                                    @elseif($isOverdue) Overdue
                                    @elseif($ptpStatus === 'kept') Kept
                                    @else Rolling
                                    @endif
                                </span>
                                @if(($ptp['extend_count'] ?? 0) > 0)
                                    <span class="inline-block mt-1 px-1.5 py-0.5 bg-sky-100 text-sky-700 text-[10px] rounded" title="Sudah pernah di-extend 1x">EXT 1x</span>
                                @endif
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
                                    @if($ptpStatus === 'new')
                                        <button onclick="updatePTP({{ $c->id }}, 'kept')"
                                                class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs rounded transition-colors"
                                                title="Mark Kept">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                        <button onclick="updatePTP({{ $c->id }}, 'rolling')"
                                                class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white text-xs rounded transition-colors"
                                                title="Mark Rolling">
                                            <i class="fa-solid fa-rotate"></i>
                                        </button>
                                    @endif
                                    @if(($isRolling || $isOverdue) && !$extendUsed)
                                        <button onclick="openExtendModal({{ $c->id }}, '{{ addslashes($c->name) }}', '{{ $ptp['date'] ?? '' }}')"
                                                class="px-2.5 py-1 bg-sky-600 hover:bg-sky-700 text-white text-xs rounded transition-colors"
                                                title="Request Extend 1x">
                                            <i class="fa-solid fa-calendar-plus"></i>
                                        </button>
                                    @endif
                                    <button onclick="editPTP(this)"
                                            data-id="{{ $c->id }}"
                                            data-name="{{ $c->name }}"
                                            data-amount="{{ $ptp['amount'] ?? 0 }}"
                                            data-date="{{ $ptp['date'] ?? '' }}"
                                            data-note="{{ $ptp['note'] ?? '' }}"
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

    <!-- Modal Edit PTP -->
    <div id="ptp-edit-modal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" onclick="closePtpEditModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-4 border-b border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Edit PTP — <span id="ptp-edit-name"></span></h3>
                    <button onclick="closePtpEditModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <form onsubmit="return submitPtpEdit(event)" class="p-4 space-y-4">
                    <input type="hidden" id="ptp-edit-id">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nominal Janji Bayar (Rp) <span class="text-red-500">*</span></label>
                        <input type="number" id="ptp-edit-amount" min="1" step="1" required class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Janji <span class="text-red-500">*</span></label>
                        <input type="date" id="ptp-edit-date" required min="{{ now()->toDateString() }}" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Catatan</label>
                        <textarea id="ptp-edit-note" rows="3" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
                    </div>
                    <p class="text-[11px] text-amber-600 bg-amber-50 border border-amber-200 rounded-lg p-2">Menyimpan akan mengaktifkan ulang PTP (status kembali new).</p>
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <button type="button" onclick="closePtpEditModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                        <button type="submit" id="ptp-edit-submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm hover:bg-brand-700 transition-colors disabled:opacity-50">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    <!-- Modal Request Extend 1x -->
    <div id="ptp-extend-modal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" onclick="closeExtendModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-4 border-b border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Request Extend — <span id="ptp-extend-name"></span></h3>
                    <button onclick="closeExtendModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <form onsubmit="return submitExtend(event)" class="p-4 space-y-4">
                    <input type="hidden" id="ptp-extend-id">
                    <p class="text-[11px] text-sky-700 bg-sky-50 border border-sky-200 rounded-lg p-2">Perpanjangan hanya bisa <strong>1x per PTP</strong>. Tanggal lama: <span id="ptp-extend-old" class="font-mono"></span>. Setelah extend, status kembali <strong>new</strong>.</p>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Janji Baru <span class="text-red-500">*</span></label>
                        <input type="date" id="ptp-extend-date" required min="{{ now()->toDateString() }}" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Catatan Extend</label>
                        <textarea id="ptp-extend-note" rows="3" placeholder="cth: minta mundur ke gajian..." class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <button type="button" onclick="closeExtendModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                        <button type="submit" id="ptp-extend-submit" class="px-4 py-2 bg-sky-600 text-white rounded-lg text-sm hover:bg-sky-700 transition-colors disabled:opacity-50">Extend 1x</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function updatePTP(customerId, action) {
    const confirmMsg = action === 'kept'
        ? 'Tandai PTP ini sebagai DITEPATI (customer sudah bayar)?'
        : 'Tandai PTP ini sebagai ROLLING (janji gagal / perlu dijadwal ulang)?';
    
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

function editPTP(btn) {
    document.getElementById('ptp-edit-id').value = btn.dataset.id;
    document.getElementById('ptp-edit-name').textContent = btn.dataset.name || '';
    document.getElementById('ptp-edit-amount').value = btn.dataset.amount || '';
    document.getElementById('ptp-edit-date').value = btn.dataset.date || '';
    document.getElementById('ptp-edit-note').value = btn.dataset.note || '';
    document.getElementById('ptp-edit-modal').style.display = 'block';
}

function closePtpEditModal() {
    document.getElementById('ptp-edit-modal').style.display = 'none';
}

function openExtendModal(id, name, oldDate) {
    document.getElementById('ptp-extend-id').value = id;
    document.getElementById('ptp-extend-name').textContent = name || '';
    document.getElementById('ptp-extend-old').textContent = oldDate || '-';
    document.getElementById('ptp-extend-date').value = '';
    document.getElementById('ptp-extend-note').value = '';
    document.getElementById('ptp-extend-modal').style.display = 'block';
}

function closeExtendModal() {
    document.getElementById('ptp-extend-modal').style.display = 'none';
}

async function submitExtend(event) {
    event.preventDefault();
    const id = document.getElementById('ptp-extend-id').value;
    const btn = document.getElementById('ptp-extend-submit');
    btn.disabled = true;
    try {
        const res = await fetch(`/dashboard/crm/customers/${id}/ptp/extend`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                ptp_date: document.getElementById('ptp-extend-date').value,
                ptp_note: document.getElementById('ptp-extend-note').value,
            }),
        });
        const data = await res.json();
        if (data.status === 'success') {
            window.location.reload();
        } else {
            alert(data.message || 'Error');
        }
    } catch (e) {
        console.error(e);
        alert('Terjadi kesalahan');
    } finally {
        btn.disabled = false;
    }
    return false;
}

async function submitPtpEdit(event) {
    event.preventDefault();
    const id = document.getElementById('ptp-edit-id').value;
    const btn = document.getElementById('ptp-edit-submit');
    btn.disabled = true;
    try {
        const res = await fetch(`/dashboard/crm/customers/${id}/ptp`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                ptp_amount: document.getElementById('ptp-edit-amount').value,
                ptp_date: document.getElementById('ptp-edit-date').value,
                ptp_note: document.getElementById('ptp-edit-note').value,
            }),
        });
        const data = await res.json();
        if (data.status === 'success') {
            window.location.reload();
        } else {
            alert(data.message || 'Error');
        }
    } catch (e) {
        console.error(e);
        alert('Terjadi kesalahan');
    } finally {
        btn.disabled = false;
    }
    return false;
}
</script>
@endsection