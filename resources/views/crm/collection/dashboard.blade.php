@extends('layouts.app')

@section('content')
{{-- Style tag placed inline (not in @push) so it renders even if layout.app has no @stack('styles') --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .font-body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
    .tnum { font-variant-numeric: tabular-nums; }
</style>

<div class="flex flex-col gap-5 font-body p-1" style="background:#EDF0F7;">

    <!-- ============ WELCOME BANNER ============ -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Collection Dashboard</h1>
            <p class="text-sm text-slate-500 mt-0.5">Welcome back, <span class="font-semibold text-brand-600">{{ $roleTitle ?? 'User' }}</span></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button onclick="collectionAutoAssign()" id="btn-auto-assign" class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 px-4 py-2 rounded-xl text-xs font-bold text-white uppercase tracking-wider transition-colors">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Assign Collector
            </button>
            <a href="{{ route('crm.collection.ptp') }}" class="inline-flex items-center gap-2 bg-white px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider hover:bg-slate-50 transition-colors">
                <i class="fa-solid fa-handshake"></i> PTP Management
            </a>
        </div>
    </div>

    <!-- ============ SECTION HEADER ============ -->
    <div class="flex items-end justify-between px-1">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Ringkasan</h2>
            <p class="text-sm text-slate-500">Portfolio, risiko dan status PTP hari ini.</p>
        </div>
    </div>

    <!-- ============ STAT CARDS ============ -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-users text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-slate-900 tnum">{{ $bucketSummary->sum('count') }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Total cases</p>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-money-bill-wave text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-slate-900 tnum">Rp {{ number_format($bucketSummary->sum('total_amount'), 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Total portfolio</p>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-check-circle text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-emerald-600 tnum">Rp {{ number_format($bucketSummary->sum('paid_amount'), 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Collected</p>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-clock text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-orange-600 tnum">Rp {{ number_format($bucketSummary->sum('remaining_amount'), 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Outstanding</p>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-triangle-exclamation text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-rose-600 tnum">
                    Rp {{ number_format($bucketSummary->where('bucket', 'NPL')->sum('remaining_amount'), 0, ',', '.') }}
                </p>
                <p class="text-xs text-slate-500 mt-0.5">NPL exposure <span class="tnum">({{ $bucketSummary->where('bucket', 'NPL')->sum('count') }} case)</span></p>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-handshake text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-yellow-600 tnum">{{ $ptpStats['active'] }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Active PTP</p>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-check-circle text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-green-600 tnum">{{ $ptpStats['kept_today'] }}</p>
                <p class="text-xs text-slate-500 mt-0.5">PTP kept today</p>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-xmark-circle text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-red-600 tnum">{{ $ptpStats['broken_today'] }}</p>
                <p class="text-xs text-slate-500 mt-0.5">PTP broken today</p>
            </div>
        </div>
    </div>

    <!-- ============ ROW 2: BUCKET & RISK ============ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Bucket Aging -->
        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6">
            <h3 class="text-base font-bold text-slate-900">Bucket aging summary</h3>
            <p class="text-xs text-slate-500 mt-0.5">Portfolio dan recovery per bucket.</p>
            <div class="overflow-x-auto mt-3">
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Bucket</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Cases</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Portfolio</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Collected</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Outstanding</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Recovery %</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Avg DPD</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($bucketSummary as $b)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium"
                                        @class([
                                            'bg-blue-50 text-blue-600' => $b->bucket === 'Current',
                                            'bg-green-50 text-green-600' => $b->bucket === 'Bucket 1',
                                            'bg-yellow-50 text-yellow-600' => $b->bucket === 'Bucket 2',
                                            'bg-orange-50 text-orange-600' => $b->bucket === 'Bucket 3',
                                            'bg-rose-50 text-rose-600' => $b->bucket === 'NPL',
                                        ])>
                                        {{ $b->bucket }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-right tnum">{{ number_format($b->count) }}</td>
                                <td class="px-3 py-3 text-right text-slate-700 tnum">Rp {{ number_format($b->total_amount, 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-right text-emerald-600 font-medium tnum">Rp {{ number_format($b->paid_amount, 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-right text-orange-600 font-medium tnum">Rp {{ number_format($b->remaining_amount, 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-slate-900 tnum">
                                    {{ $b->total_amount > 0 ? number_format(($b->paid_amount / $b->total_amount) * 100, 1) : 0 }}%
                                </td>
                                <td class="px-3 py-3 text-right text-slate-400 tnum">{{ number_format($b->avg_dpd, 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Risk Level -->
        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6">
            <h3 class="text-base font-bold text-slate-900">Risk distribution</h3>
            <p class="text-xs text-slate-500 mt-0.5">Jumlah case dan exposure per level risiko.</p>
            <div class="mt-3 divide-y divide-slate-100">
                @foreach($riskSummary as $r)
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <span class="w-2.5 h-2.5 rounded-full"
                                @class([
                                    'bg-green-500' => $r->risk_level === 'low',
                                    'bg-yellow-500' => $r->risk_level === 'medium',
                                    'bg-orange-500' => $r->risk_level === 'high',
                                    'bg-red-500' => $r->risk_level === 'critical',
                                ])></span>
                            <span class="font-medium text-slate-700 capitalize">{{ $r->risk_level }}</span>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-slate-900 tnum">{{ number_format($r->count) }} cases</div>
                            <div class="text-xs text-slate-400 tnum">Rp {{ number_format($r->exposure, 0, ',', '.') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- ============ ROW 3: COLLECTOR PERFORMANCE ============ -->
    <div class="grid grid-cols-1 gap-4">
        <!-- Collector Performance -->
        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6">
            <h3 class="text-base font-bold text-slate-900">Collector performance</h3>
            <p class="text-xs text-slate-500 mt-0.5">Kolektabilitas tiap collector.</p>
            <div class="overflow-x-auto mt-3">
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Collector</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Cases</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Portfolio</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Collected</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Recovery %</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Closed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($collectorPerformance as $c)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3">
                                    <div class="font-medium text-slate-900">{{ $c->collector_name }}</div>
                                    <div class="text-xs text-slate-400 tnum">{{ $c->collector_type === 'field' ? 'Lapangan' : 'Desk' }}{{ $c->collector_phone ? ' • ' . $c->collector_phone : '' }}</div>
                                </td>
                                <td class="px-3 py-3 text-right tnum">{{ number_format($c->assigned_cases) }}</td>
                                <td class="px-3 py-3 text-right text-slate-700 tnum">Rp {{ number_format($c->portfolio_value, 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-right text-emerald-600 font-medium tnum">Rp {{ number_format($c->collected, 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-indigo-600 tnum">{{ number_format($c->avg_collection_rate, 1) }}%</td>
                                <td class="px-3 py-3 text-right tnum">{{ number_format($c->closed_count) }}</td>
                            </tr>
                        @endforeach
                        @if($collectorPerformance->isEmpty())
                            <tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">Belum ada collector</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ ROW 4: PTP & UPCOMING DUE ============ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- PTP Summary -->
        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6">
            <h3 class="text-base font-bold text-slate-900">Promise to Pay (PTP) status</h3>
            <p class="text-xs text-slate-500 mt-0.5">Ringkasan janji bayar hari ini.</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
                <div class="p-4 rounded-2xl bg-green-50 text-center">
                    <div class="text-2xl font-bold text-green-600 tnum">{{ $ptpStats['active'] }}</div>
                    <div class="text-xs text-green-600 mt-1">Active PTP</div>
                </div>
                <div class="p-4 rounded-2xl bg-red-50 text-center">
                    <div class="text-2xl font-bold text-red-600 tnum">{{ $ptpStats['overdue'] }}</div>
                    <div class="text-xs text-red-600 mt-1">Overdue PTP</div>
                </div>
                <div class="p-4 rounded-2xl bg-emerald-50 text-center">
                    <div class="text-2xl font-bold text-emerald-600 tnum">{{ $ptpStats['kept_today'] }}</div>
                    <div class="text-xs text-emerald-600 mt-1">Kept today</div>
                </div>
                <div class="p-4 rounded-2xl bg-rose-50 text-center">
                    <div class="text-2xl font-bold text-rose-600 tnum">{{ $ptpStats['broken_today'] }}</div>
                    <div class="text-xs text-rose-600 mt-1">Broken today</div>
                </div>
            </div>
        </div>

        <!-- Upcoming Due -->
        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6">
            <h3 class="text-base font-bold text-slate-900">Jatuh tempo 7 hari ke depan</h3>
            <p class="text-xs text-slate-500 mt-0.5">Diurutkan dari yang paling dekat.</p>
            <div class="mt-3 divide-y divide-slate-100 max-h-96 overflow-y-auto">
                @foreach($upcomingDue as $c)
                    <div class="py-3 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-slate-900 truncate">{{ $c->name }}</div>
                                <div class="text-xs text-slate-400 tnum">{{ $c->phone }}</div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-emerald-600 font-semibold tnum text-sm">Rp {{ number_format($c->remaining_amount, 0, ',', '.') }}</div>
                                <div class="text-[11px] text-slate-400 tnum">{{ $c->due_date->format('d M Y') }}</div>
                            </div>
                        </div>
                        <div class="mt-1 flex items-center gap-2 text-[11px] text-slate-400 tnum">
                            <span>Total: Rp {{ number_format($c->total_amount, 0, ',', '.') }}</span>
                            @if($c->collector)
                                <span class="bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full">{{ $c->collector->name }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
                @if($upcomingDue->isEmpty())
                    <div class="text-center py-8 text-slate-400 text-sm">Tidak ada jatuh tempo mendatang</div>
                @endif
            </div>
        </div>
    </div>

    <!-- ============ ROW 4.5: SLA BREACH ============ -->
    <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6" style="border-left: 4px solid #F97316;">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-orange-600">SLA breach</h3>
                <p class="text-xs text-slate-500 mt-0.5">Case dengan DPD melewati batas bucket.</p>
            </div>
            <button onclick="collectionSlaCheck()" id="btn-sla-check" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold shadow-sm transition-colors">
                <i class="fa-solid fa-bolt text-xs"></i> Run SLA Check &amp; Eskalasi
            </button>
        </div>
        @if(empty($slaBreaches))
            <div class="text-center py-6 text-emerald-600 text-sm mt-2">
                <i class="fa-solid fa-circle-check text-2xl mb-2"></i>
                <p>Tidak ada breach. Semua case masih dalam SLA bucket-nya.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mt-4">
                @foreach($slaBreaches as $s)
                    <div class="rounded-2xl bg-orange-50 p-4">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-800">{{ $s['bucket'] }}</span>
                            <span class="text-xs text-orange-600 tnum">max {{ $s['max_dpd'] }} DPD</span>
                        </div>
                        <p class="text-2xl font-bold text-orange-600 mt-1 tnum">{{ number_format($s['count']) }} <span class="text-sm font-normal">case breach</span></p>
                        <p class="text-xs text-slate-500 mt-1">{{ $s['action'] }}</p>
                    </div>
                @endforeach
            </div>
            <p class="text-[11px] text-slate-400 mt-3">Run SLA Check akan recalculate bucket dulu, lalu menaikkan risk_level case yang breach sesuai aturan.</p>
        @endif
    </div>

    <!-- ============ ROW 5: TOP NPL ============ -->
    <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6" style="border-left: 4px solid #E11D48;">
        <h3 class="text-base font-bold text-rose-600">Top 15 NPL cases</h3>
        <p class="text-xs text-slate-500 mt-0.5">Butuh perhatian prioritas.</p>
        <div class="overflow-x-auto mt-3">
            <table class="w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Debtor</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Phone</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Portfolio</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Collected</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Outstanding</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">DPD</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Collector</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($topNPL as $c)
                        <tr class="hover:bg-rose-50/60">
                            <td class="px-3 py-3 font-medium text-slate-900">{{ $c->name }}</td>
                            <td class="px-3 py-3 text-right text-slate-500 tnum">{{ $c->phone }}</td>
                            <td class="px-3 py-3 text-right text-slate-700 tnum">Rp {{ number_format($c->total_amount, 0, ',', '.') }}</td>
                            <td class="px-3 py-3 text-right text-emerald-600 tnum">Rp {{ number_format($c->paid_amount, 0, ',', '.') }}</td>
                            <td class="px-3 py-3 text-right text-rose-600 font-bold tnum">Rp {{ number_format($c->remaining_amount, 0, ',', '.') }}</td>
                            <td class="px-3 py-3 text-right text-rose-500 tnum">{{ $c->days_past_due }} days</td>
                            <td class="px-3 py-3">
                                @if($c->collector)
                                    <span class="text-indigo-600 font-medium">{{ $c->collector->name }}</span>
                                @else
                                    <span class="text-slate-300">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal hasil auto-assign -->
<div id="assign-result-modal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" onclick="closeAssignResult()"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[85vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between p-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Hasil Auto-Assign Collector</h3>
                <button onclick="closeAssignResult()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div class="p-4 border-b border-slate-100 text-sm text-slate-600" id="assign-result-summary"></div>
            <div class="flex-1 overflow-y-auto p-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Customer</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Telepon</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Bucket</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Collector</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200" id="assign-result-body"></tbody>
                    </table>
                </div>
                <p id="assign-result-truncated" class="text-[11px] text-slate-400 mt-2" style="display: none;">Hanya 200 baris pertama yang ditampilkan.</p>
            </div>
            <div class="p-4 border-t border-slate-200 flex justify-end gap-2">
                <button onclick="closeAssignResult()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Tutup</button>
                <button onclick="window.location.reload()" class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm hover:bg-brand-700 transition-colors">Tutup & Refresh</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Auto-refresh every 60 seconds
    setInterval(() => {
        if (document.visibilityState === 'visible') {
            window.location.reload();
        }
    }, 60000);
});

async function collectionSlaCheck() {
    if (!confirm('Jalankan SLA check? Bucket dihitung ulang, risk case yang breach akan dieskalasi.')) return;
    const btn = document.getElementById('btn-sla-check');
    btn.disabled = true;
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
    try {
        const res = await fetch('{{ url('/dashboard/crm/collection/sla-check') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });
        const data = await res.json();
        alert(data.message || 'Selesai');
        window.location.reload();
    } catch (e) {
        console.error(e);
        alert('Terjadi kesalahan');
    } finally {
        btn.disabled = false;
        btn.innerHTML = old;
    }
}

async function collectionAutoAssign() {
    if (!confirm('Auto-assign semua case belum ada collector ke debt collector aktif (bagi rata per bucket)?')) return;
    const btn = document.getElementById('btn-auto-assign');
    btn.disabled = true;
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Assign...';
    try {
        const res = await fetch('{{ url('/dashboard/crm/collection/auto-assign') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ only_unassigned: true }),
        });
        const data = await res.json();
        if (data.status === 'success') {
            showAssignResult(data);
        } else {
            alert(data.message || 'Error');
        }
    } catch (e) {
        console.error(e);
        alert('Terjadi kesalahan');
    } finally {
        btn.disabled = false;
        btn.innerHTML = old;
    }
}

function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function showAssignResult(data) {
    document.getElementById('assign-result-summary').textContent = data.message || 'Selesai';
    const rows = data.assignments || [];
    document.getElementById('assign-result-body').innerHTML = rows.length === 0
        ? '<tr><td colspan="4" class="px-3 py-8 text-center text-slate-400">Tidak ada case yang di-assign.</td></tr>'
        : rows.map(a => `<tr class="hover:bg-slate-50">
                <td class="px-3 py-2 font-medium text-slate-900">${escHtml(a.name)}</td>
                <td class="px-3 py-2 font-mono text-slate-600">${escHtml(a.phone)}</td>
                <td class="px-3 py-2"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">${escHtml(a.bucket)}</span></td>
                <td class="px-3 py-2 text-slate-600">${escHtml(a.collector)}</td>
            </tr>`).join('');
    document.getElementById('assign-result-truncated').style.display = data.truncated ? 'block' : 'none';
    document.getElementById('assign-result-modal').style.display = 'block';
}

function closeAssignResult() {
    document.getElementById('assign-result-modal').style.display = 'none';
}
</script>
@endsection