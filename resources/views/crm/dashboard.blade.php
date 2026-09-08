@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">CRM Dashboard</h1>
            <p class="text-slate-500 mt-1">Ringkasan pembayaran, pipeline & performa koleksi</p>
        </div>
        <a href="{{ route('crm.customers.index') }}" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2">
            <i class="fa-solid fa-users"></i> Kelola Customer
        </a>
    </div>

    <!-- ============ STAT CARDS ============ -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="fa-solid fa-users text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Total Customer</p>
                    <p class="text-2xl font-bold text-slate-900">{{ number_format($totalCustomers) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="fa-solid fa-money-bill-wave text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Total Tagihan</p>
                    <p class="text-2xl font-bold text-slate-900">Rp {{ number_format($totalAmount, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-green-50 text-green-600 flex items-center justify-center">
                    <i class="fa-solid fa-check-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Total Terbayar</p>
                    <p class="text-2xl font-bold text-emerald-700">Rp {{ number_format($totalPaid, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <i class="fa-solid fa-tag text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Total Diskon</p>
                    <p class="text-2xl font-bold text-purple-700">Rp {{ number_format($totalDiscount, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i class="fa-solid fa-clock text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Sisa Tagihan</p>
                    <p class="text-2xl font-bold text-amber-700">Rp {{ number_format($totalRemaining, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <i class="fa-solid fa-chart-line text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Kolektabilitas</p>
                    <p class="text-2xl font-bold text-indigo-700">{{ $collectionRate }}%</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ ROW 2: CHARTS ============ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Payment Status Donut -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-circle-dollar-to-slot text-brand-600"></i> Status Pembayaran
            </h3>
            <div class="h-64 flex items-center justify-center">
                <canvas id="paymentStatusChart"></canvas>
            </div>
            <div class="flex flex-wrap gap-4 mt-4 justify-center">
                @foreach(['unpaid' => ['label' => 'Belum Bayar', 'color' => 'bg-red-500'], 'partial' => ['label' => 'Cicilan', 'color' => 'bg-yellow-500'], 'paid' => ['label' => 'Lunas', 'color' => 'bg-green-500']] as $key => $info)
                    <div class="flex items-center gap-2 text-sm">
                        <span class="w-3 h-3 rounded-full {{ $info['color'] }}"></span>
                        <span>{{ $info['label'] }}: <strong>{{ $paymentStatusStats[$key] ?? 0 }}</strong> (Rp {{ number_format($paymentStatusAmounts[$key]['paid'] ?? 0, 0, ',', '.') }})</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Pipeline Funnel -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-funnel-dollar text-brand-600"></i> Pipeline Status
            </h3>
            <div class="h-64 flex items-center justify-center">
                <canvas id="pipelineChart"></canvas>
            </div>
            <div class="flex flex-wrap gap-4 mt-4 justify-center text-xs">
                @foreach(['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'proposal' => 'Proposal', 'closed_won' => 'Menang', 'closed_lost' => 'Kalah'] as $key => $label)
                    <span class="flex items-center gap-1">
                        <strong>{{ $pipelineData[$key]['count'] ?? 0 }}</strong> {{ $label }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    <!-- ============ ROW 3: MONTHLY TREND ============ -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
        <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-area text-brand-600"></i> Tren Koleksi 6 Bulan Terakhir
        </h3>
        <div class="h-80">
            <canvas id="monthlyTrendChart"></canvas>
        </div>
    </div>

    <!-- ============ ROW 4: TABLES ============ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Top Customers -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-trophy text-amber-500"></i> Top 10 Customer by Tagihan
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Customer</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Tagihan</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Terbayar</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Sisa</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($topCustomers as $c)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2">
                                    <div class="font-medium text-slate-900">{{ $c->name }}</div>
                                    <div class="text-xs text-slate-500 font-mono">{{ $c->phone }}</div>
                                </td>
                                <td class="px-3 py-2 text-slate-700 font-mono">Rp {{ number_format($c->total_amount, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-emerald-700 font-mono">Rp {{ number_format($c->paid_amount, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-amber-700 font-mono">Rp {{ number_format(max(0, $c->total_amount - $c->paid_amount - $c->discount_amount), 0, ',', '.') }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium border"
                                        @class([
                                            'bg-red-50 text-red-700 border-red-200' => $c->payment_status === 'unpaid',
                                            'bg-yellow-50 text-yellow-700 border-yellow-200' => $c->payment_status === 'partial',
                                            'bg-green-50 text-green-700 border-green-200' => $c->payment_status === 'paid',
                                        ])>
                                        @if($c->payment_status === 'unpaid') Belum Bayar
                                        @elseif($c->payment_status === 'partial') Cicilan
                                        @else Lunas
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Agent Performance -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-user-tie text-brand-600"></i> Performa Agent
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Agent</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Customer</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Total Tagihan</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Terbayar</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Kolektabilitas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($agentPerformance as $a)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2">
                                    <div class="font-medium text-slate-900">{{ $a['agent_name'] }}</div>
                                    <div class="text-xs text-slate-500 font-mono">Ext: {{ $a['agent_extension'] }}</div>
                                </td>
                                <td class="px-3 py-2 text-slate-700">{{ $a['total_customers'] }}</td>
                                <td class="px-3 py-2 font-mono">Rp {{ number_format($a['total_amount'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-emerald-700 font-mono">Rp {{ number_format($a['paid_amount'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2">
                                    <div class="w-24 h-2 bg-slate-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-brand-600" style="width: {{ $a['collection_rate'] }}%"></div>
                                    </div>
                                    <span class="text-xs text-slate-500">{{ $a['collection_rate'] }}%</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ ROW 5: RECENT PAYMENTS & ATTENTION ============ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Recent Payments -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-emerald-500"></i> Pembayaran Terbaru
                </h3>
            </div>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($recentPayments as $p)
                    <div class="border border-slate-200 rounded-lg p-3 hover:bg-slate-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium text-slate-900">{{ $p->name }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ $p->phone }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-emerald-700 font-semibold font-mono text-sm">Rp {{ number_format($p->paid_amount, 0, ',', '.') }}</div>
                                <div class="text-[10px] text-slate-400">{{ $p->last_payment_date->format('d M Y H:i') }}</div>
                            </div>
                        </div>
                        @if($p->payment_notes)
                            <div class="mt-2 text-[11px] text-slate-500 bg-slate-50 p-2 rounded border border-slate-100 line-clamp-1">{{ $p->payment_notes }}</div>
                        @endif
                    </div>
                @endforeach
                @if($recentPayments->isEmpty())
                    <div class="text-center py-8 text-slate-400 text-sm">Belum ada pembayaran tercatat</div>
                @endif
            </div>
        </div>

        <!-- Attention Needed -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-red-500">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2 text-red-600">
                    <i class="fa-solid fa-triangle-exclamation"></i> Perlu Perhatian (Belum Lunas)
                </h3>
            </div>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($attentionCustomers as $c)
                    <div class="border border-slate-200 rounded-lg p-3 hover:bg-red-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-slate-900 truncate">{{ $c->name }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ $c->phone }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-red-700 font-semibold font-mono text-sm">Rp {{ number_format(max(0, $c->total_amount - $c->paid_amount - $c->discount_amount), 0, ',', '.') }}</div>
                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-[10px] font-medium border"
                                    @class([
                                        'bg-red-50 text-red-700 border-red-200' => $c->payment_status === 'unpaid',
                                        'bg-yellow-50 text-yellow-700 border-yellow-200' => $c->payment_status === 'partial',
                                    ])>
                                    @if($c->payment_status === 'unpaid') Belum Bayar @else Cicilan @endif
                                </span>
                            </div>
                        </div>
                        <div class="mt-1 flex items-center gap-3 text-[11px] text-slate-500">
                            <span>Tagihan: Rp {{ number_format($c->total_amount, 0, ',', '.') }}</span>
                            <span>Terbayar: Rp {{ number_format($c->paid_amount, 0, ',', '.') }}</span>
                            @if($c->discount_amount > 0)
                                <span class="text-emerald-600">Diskon: Rp {{ number_format($c->discount_amount, 0, ',', '.') }}</span>
                            @endif
                        </div>
                        @if($c->payment_notes)
                            <div class="mt-2 text-[11px] text-slate-500 bg-slate-50 p-2 rounded border border-slate-100 line-clamp-1">{{ $c->payment_notes }}</div>
                        @endif
                    </div>
                @endforeach
                @if($attentionCustomers->isEmpty())
                    <div class="text-center py-8 text-emerald-500 text-sm">
                        <i class="fa-solid fa-check-circle text-2xl mb-2"></i>
                        Semua customer sudah lunas!
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script data-turbo-eval="true">
    window.crmChartData = {
        payment: {
            unpaid: {{ $paymentStatusStats['unpaid'] ?? 0 }},
            partial: {{ $paymentStatusStats['partial'] ?? 0 }},
            paid: {{ $paymentStatusStats['paid'] ?? 0 }}
        },
        pipeline: {
            new: {{ $pipelineData['new']['count'] ?? 0 }},
            contacted: {{ $pipelineData['contacted']['count'] ?? 0 }},
            qualified: {{ $pipelineData['qualified']['count'] ?? 0 }},
            proposal: {{ $pipelineData['proposal']['count'] ?? 0 }},
            won: {{ $pipelineData['closed_won']['count'] ?? 0 }},
            lost: {{ $pipelineData['closed_lost']['count'] ?? 0 }}
        },
        trend: {
            months: @json(array_column($monthlyTrend, 'month')),
            paid: @json(array_column($monthlyTrend, 'paid')),
            discount: @json(array_column($monthlyTrend, 'discount')),
            total: @json(array_column($monthlyTrend, 'total'))
        }
    };
</script>
@endsection