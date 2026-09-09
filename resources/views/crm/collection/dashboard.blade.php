@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Collection Dashboard</h1>
            <p class="text-slate-500 mt-1">Bucket aging, campaign & collector performance, PTP tracking</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('crm.collection.aging') }}" class="bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2">
                <i class="fa-solid fa-table-columns"></i> Aging Report
            </a>
            <a href="{{ route('crm.collection.ptp') }}" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2">
                <i class="fa-solid fa-handshake"></i> PTP Management
            </a>
        </div>
    </div>

    <!-- ============ STAT CARDS ============ -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="fa-solid fa-users text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Total Cases</p>
                    <p class="text-2xl font-bold text-slate-900">{{ $bucketSummary->sum('count') }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="fa-solid fa-money-bill-wave text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Total Portfolio</p>
                    <p class="text-2xl font-bold text-slate-900">Rp {{ number_format($bucketSummary->sum('total_amount'), 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-green-50 text-green-600 flex items-center justify-center">
                    <i class="fa-solid fa-check-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Collected</p>
                    <p class="text-2xl font-bold text-emerald-700">Rp {{ number_format($bucketSummary->sum('paid_amount'), 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i class="fa-solid fa-clock text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Outstanding</p>
                    <p class="text-2xl font-bold text-amber-700">Rp {{ number_format($bucketSummary->sum('remaining_amount'), 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-red-500">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center">
                    <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">NPL Exposure</p>
                    <p class="text-2xl font-bold text-red-700">
                        Rp {{ number_format($bucketSummary->where('bucket', 'NPL')->sum('remaining_amount'), 0, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-yellow-500">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center">
                    <i class="fa-solid fa-handshake text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Active PTP</p>
                    <p class="text-2xl font-bold text-yellow-700">{{ $ptpStats['active'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-green-500">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-green-50 text-green-600 flex items-center justify-center">
                    <i class="fa-solid fa-check-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">PTP Kept Today</p>
                    <p class="text-2xl font-bold text-green-700">{{ $ptpStats['kept_today'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-red-500">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center">
                    <i class="fa-solid fa-xmark-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">PTP Broken Today</p>
                    <p class="text-2xl font-bold text-red-700">{{ $ptpStats['broken_today'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ ROW 2: BUCKET & RISK ============ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Bucket Aging -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-brand-600"></i> Bucket Aging Summary
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Bucket</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Cases</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Portfolio</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Collected</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Outstanding</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Recovery %</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Avg DPD</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($bucketSummary as $b)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium border"
                                        @class([
                                            'bg-blue-50 text-blue-700 border-blue-200' => $b->bucket === 'Current',
                                            'bg-green-50 text-green-700 border-green-200' => $b->bucket === 'Bucket 1',
                                            'bg-yellow-50 text-yellow-700 border-yellow-200' => $b->bucket === 'Bucket 2',
                                            'bg-orange-50 text-orange-700 border-orange-200' => $b->bucket === 'Bucket 3',
                                            'bg-red-50 text-red-700 border-red-200' => $b->bucket === 'NPL',
                                        ])>
                                        {{ $b->bucket }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format($b->count) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-slate-700">Rp {{ number_format($b->total_amount, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-mono text-emerald-700">Rp {{ number_format($b->paid_amount, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-mono text-amber-700">Rp {{ number_format($b->remaining_amount, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-semibold">
                                    {{ $b->total_amount > 0 ? number_format(($b->paid_amount / $b->total_amount) * 100, 1) : 0 }}%
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-slate-500">{{ number_format($b->avg_dpd, 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Risk Level -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-brand-600"></i> Risk Distribution
            </h3>
            <div class="space-y-3">
                @foreach($riskSummary as $r)
                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <span class="w-4 h-4 rounded-full"
                                @class([
                                    'bg-green-500' => $r->risk_level === 'low',
                                    'bg-yellow-500' => $r->risk_level === 'medium',
                                    'bg-orange-500' => $r->risk_level === 'high',
                                    'bg-red-500' => $r->risk_level === 'critical',
                                ])></span>
                            <span class="font-medium text-slate-700 capitalize">{{ $r->risk_level }}</span>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-slate-900">{{ number_format($r->count) }} cases</div>
                            <div class="text-xs text-slate-500 font-mono">Rp {{ number_format($r->exposure, 0, ',', '.') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- ============ ROW 3: CAMPAIGN & COLLECTOR PERFORMANCE ============ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Campaign Performance -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-bullseye text-brand-600"></i> Campaign Performance
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Campaign</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Cases</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Portfolio</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Collected</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Recovery %</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Closed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($campaignPerformance as $c)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2">
                                    <div class="font-medium text-slate-900">{{ $c->campaign_name }}</div>
                                    <div class="text-xs text-slate-500 capitalize">{{ $c->type }}</div>
                                </td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format($c->total_cases) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-slate-700">Rp {{ number_format($c->portfolio_value, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-mono text-emerald-700">Rp {{ number_format($c->collected, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-semibold">
                                    {{ $c->portfolio_value > 0 ? number_format(($c->collected / $c->portfolio_value) * 100, 1) : 0 }}%
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-brand-700">{{ number_format($c->closed_count) }}</td>
                            </tr>
                        @endforeach
                        @if($campaignPerformance->isEmpty())
                            <tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">Belum ada campaign</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Collector Performance -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-user-tie text-brand-600"></i> Collector Performance
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Collector</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Cases</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Portfolio</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Collected</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Recovery %</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Closed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($collectorPerformance as $c)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2">
                                    <div class="font-medium text-slate-900">{{ $c->collector_name }}</div>
                                    <div class="text-xs text-slate-500 font-mono">Ext: {{ $c->extension }}</div>
                                </td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format($c->assigned_cases) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-slate-700">Rp {{ number_format($c->portfolio_value, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-mono text-emerald-700">Rp {{ number_format($c->collected, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-brand-700">{{ number_format($c->avg_collection_rate, 1) }}%</td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format($c->closed_count) }}</td>
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
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-handshake text-brand-600"></i> Promise to Pay (PTP) Status
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="p-4 bg-green-50 rounded-xl border border-green-200 text-center">
                    <div class="text-3xl font-bold text-green-700">{{ $ptpStats['active'] }}</div>
                    <div class="text-xs text-green-600">Active PTP</div>
                </div>
                <div class="p-4 bg-red-50 rounded-xl border border-red-200 text-center">
                    <div class="text-3xl font-bold text-red-700">{{ $ptpStats['overdue'] }}</div>
                    <div class="text-xs text-red-600">Overdue PTP</div>
                </div>
                <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-200 text-center">
                    <div class="text-3xl font-bold text-emerald-700">{{ $ptpStats['kept_today'] }}</div>
                    <div class="text-xs text-emerald-600">Kept Today</div>
                </div>
                <div class="p-4 bg-rose-50 rounded-xl border border-rose-200 text-center">
                    <div class="text-3xl font-bold text-rose-700">{{ $ptpStats['broken_today'] }}</div>
                    <div class="text-xs text-rose-600">Broken Today</div>
                </div>
            </div>
        </div>

        <!-- Upcoming Due -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-calendar-days text-brand-600"></i> Jatuh Tempo 7 Hari ke Depan
            </h3>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach($upcomingDue as $c)
                    <div class="border border-slate-200 rounded-lg p-3 hover:bg-slate-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-slate-900 truncate">{{ $c->name }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ $c->phone }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-emerald-700 font-semibold font-mono text-sm">Rp {{ number_format($c->remaining_amount, 0, ',', '.') }}</div>
                                <div class="text-[10px] text-slate-400">{{ $c->due_date->format('d M Y') }}</div>
                            </div>
                        </div>
                        <div class="mt-1 flex items-center gap-3 text-[11px] text-slate-500">
                            <span>Total: Rp {{ number_format($c->total_amount, 0, ',', '.') }}</span>
                            @if($c->collector)
                                <span class="bg-brand-50 text-brand-700 px-2 py-0.5 rounded text-[10px]">{{ $c->collector->name }}</span>
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

    <!-- ============ ROW 5: TOP NPL ============ -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-red-500">
        <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2 text-red-600">
            <i class="fa-solid fa-skull-crossbones"></i> Top 15 NPL Cases (Butuh Perhatian Prioritas)
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-red-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Debtor</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Phone</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Portfolio</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Collected</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">Outstanding</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 uppercase">DPD</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Collector</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Campaign</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($topNPL as $c)
                        <tr class="hover:bg-red-50">
                            <td class="px-3 py-2 font-medium text-slate-900">{{ $c->name }}</td>
                            <td class="px-3 py-2 font-mono">{{ $c->phone }}</td>
                            <td class="px-3 py-2 text-right font-mono text-slate-700">Rp {{ number_format($c->total_amount, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-mono text-emerald-700">Rp {{ number_format($c->paid_amount, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-mono text-red-700 font-bold">Rp {{ number_format($c->remaining_amount, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-mono text-red-600">{{ $c->days_past_due }} days</td>
                            <td class="px-3 py-2">
                                @if($c->collector)
                                    <span class="text-brand-700 font-medium">{{ $c->collector->name }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if($c->campaign)
                                    <span class="text-brand-700 text-xs">{{ $c->campaign->name }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
</script>
@endsection