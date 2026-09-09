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
    <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-6" style="background:#E8FAF1;">
        <div>
            <h1 class="text-2xl font-bold text-[#0F2B22]">CRM Dashboard</h1>
            <p class="text-sm text-[#5B6B63] mt-1">Ringkasan pembayaran, pipeline &amp; performa <span class="font-semibold" style="color:#0E8F5F;">koleksi</span></p>
        </div>
        <a href="{{ route('crm.customers.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50 transition-colors">
            <i class="fa-solid fa-users text-xs text-slate-500"></i> Kelola Customer
        </a>
    </div>

    <!-- ============ SECTION HEADER ============ -->
    <div class="flex items-end justify-between px-1">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Ringkasan</h2>
            <p class="text-sm text-slate-500">Status tagihan dan pembayaran customer.</p>
        </div>
    </div>

    <!-- ============ STAT CARDS ============ -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-users text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-slate-900 tnum">{{ number_format($totalCustomers) }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Total customer</p>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-money-bill-wave text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-slate-900 tnum">Rp {{ number_format($totalAmount, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Total tagihan</p>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-check-circle text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-emerald-600 tnum">Rp {{ number_format($totalPaid, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Total terbayar</p>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-tag text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-purple-600 tnum">Rp {{ number_format($totalDiscount, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Total diskon</p>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-clock text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-rose-600 tnum">Rp {{ number_format($totalRemaining, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Sisa tagihan</p>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-chart-line text-sm"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-indigo-600 tnum">{{ $collectionRate }}%</p>
                <p class="text-xs text-slate-500 mt-0.5">Kolektabilitas</p>
            </div>
        </div>
    </div>

    <!-- ============ ROW 2: CHARTS ============ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Pipeline Funnel -->
        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6">
            <h3 class="text-base font-bold text-slate-900">Pipeline status</h3>
            <p class="text-xs text-slate-500 mt-0.5">Jumlah customer di tiap tahap pipeline.</p>
            <div class="h-64 flex items-center justify-center mt-2">
                <canvas id="pipelineChart"></canvas>
            </div>
            <div class="mt-4 divide-y divide-slate-100">
                @foreach(['new' => ['label' => 'New', 'color' => '#3B82F6'], 'contacted' => ['label' => 'Contacted', 'color' => '#6366F1'], 'qualified' => ['label' => 'Qualified', 'color' => '#8B5CF6'], 'proposal' => ['label' => 'Proposal', 'color' => '#F59E0B'], 'closed_won' => ['label' => 'Menang', 'color' => '#10B981'], 'closed_lost' => ['label' => 'Kalah', 'color' => '#EF4444']] as $key => $info)
                    <div class="flex items-center justify-between py-2 text-sm">
                        <span class="flex items-center gap-2 text-slate-600">
                            <span class="w-2 h-2 rounded-full" style="background:{{ $info['color'] }};"></span>
                            {{ $info['label'] }}
                        </span>
                        <span class="font-semibold text-slate-900 tnum">{{ $pipelineData[$key]['count'] ?? 0 }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Payment Status Donut -->
        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6">
            <h3 class="text-base font-bold text-slate-900">Status pembayaran</h3>
            <p class="text-xs text-slate-500 mt-0.5">Bagaimana tagihan customer berakhir.</p>
            <div class="h-64 flex items-center justify-center mt-2">
                <canvas id="paymentStatusChart"></canvas>
            </div>
            <div class="mt-4 divide-y divide-slate-100">
                @foreach(['unpaid' => ['label' => 'Belum bayar', 'color' => '#EF4444'], 'partial' => ['label' => 'Cicilan', 'color' => '#F59E0B'], 'paid' => ['label' => 'Lunas', 'color' => '#10B981']] as $key => $info)
                    @php $cnt = $paymentStatusStats[$key] ?? 0; $tot = max(1, array_sum($paymentStatusStats ?? [])); $pct = round($cnt / $tot * 100); @endphp
                    <div class="flex items-center justify-between py-2 text-sm">
                        <span class="flex items-center gap-2 text-slate-600">
                            <span class="w-2 h-2 rounded-full" style="background:{{ $info['color'] }};"></span>
                            {{ $info['label'] }}
                        </span>
                        <span class="tnum">
                            <span class="font-semibold text-slate-900">{{ $cnt }}</span>
                            <span class="text-slate-400 ml-1">{{ $pct }}%</span>
                        </span>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-slate-400 mt-4 text-center">
                <span class="font-semibold" style="color:#10B981;">{{ $collectionRate }}% kolektabilitas</span> &middot; Rp {{ number_format($totalPaid, 0, ',', '.') }} dari Rp {{ number_format($totalAmount, 0, ',', '.') }}
            </p>
        </div>
    </div>

    <!-- ============ ROW 3: MONTHLY TREND ============ -->
    <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6">
        <h3 class="text-base font-bold text-slate-900">Tren koleksi</h3>
        <p class="text-xs text-slate-500 mt-0.5">6 bulan terakhir.</p>
        <div class="h-80 mt-2">
            <canvas id="monthlyTrendChart"></canvas>
        </div>
    </div>

    <!-- ============ ROW 4: TABLES ============ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Top Customers -->
        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6">
            <h3 class="text-base font-bold text-slate-900">Top 10 customer</h3>
            <p class="text-xs text-slate-500 mt-0.5">Diurutkan dari tagihan terbesar.</p>
            <div class="overflow-x-auto mt-3">
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Customer</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Tagihan</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Terbayar</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Sisa</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($topCustomers as $c)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3">
                                    <div class="font-medium text-slate-900">{{ $c->name }}</div>
                                    <div class="text-xs text-slate-400 tnum">{{ $c->phone }}</div>
                                </td>
                                <td class="px-3 py-3 text-slate-700 tnum">Rp {{ number_format($c->total_amount, 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-emerald-600 font-medium tnum">Rp {{ number_format($c->paid_amount, 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-rose-600 font-medium tnum">Rp {{ number_format(max(0, $c->total_amount - $c->paid_amount - $c->discount_amount), 0, ',', '.') }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium"
                                        @class([
                                            'bg-rose-50 text-rose-600' => $c->payment_status === 'unpaid',
                                            'bg-amber-50 text-amber-600' => $c->payment_status === 'partial',
                                            'bg-emerald-50 text-emerald-600' => $c->payment_status === 'paid',
                                        ])>
                                        @if($c->payment_status === 'unpaid') Belum bayar
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
        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6">
            <h3 class="text-base font-bold text-slate-900">Performa agent</h3>
            <p class="text-xs text-slate-500 mt-0.5">Kolektabilitas tiap agent di periode berjalan.</p>
            <div class="overflow-x-auto mt-3">
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Agent</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Customer</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Tagihan</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Terbayar</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Kolektabilitas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($agentPerformance as $a)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3">
                                    <div class="font-medium text-slate-900">{{ $a['agent_name'] }}</div>
                                    <div class="text-xs text-slate-400 tnum">Ext: {{ $a['agent_extension'] }}</div>
                                </td>
                                <td class="px-3 py-3 text-slate-700 tnum">{{ $a['total_customers'] }}</td>
                                <td class="px-3 py-3 text-slate-700 tnum">Rp {{ number_format($a['total_amount'], 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-emerald-600 font-medium tnum">Rp {{ number_format($a['paid_amount'], 0, ',', '.') }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-20 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                            <div class="h-full rounded-full bg-indigo-500" style="width: {{ $a['collection_rate'] }}%"></div>
                                        </div>
                                        <span class="text-xs text-slate-500 tnum">{{ $a['collection_rate'] }}%</span>
                                    </div>
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
        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6">
            <h3 class="text-base font-bold text-slate-900">Pembayaran terbaru</h3>
            <p class="text-xs text-slate-500 mt-0.5">Transaksi masuk paling akhir.</p>
            <div class="mt-3 divide-y divide-slate-100 max-h-96 overflow-y-auto">
                @foreach($recentPayments as $p)
                    <div class="py-3 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-arrow-down text-xs"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-medium text-slate-900 truncate">{{ $p->name }}</div>
                                    <div class="text-xs text-slate-400 tnum">{{ $p->phone }}</div>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-emerald-600 font-semibold tnum text-sm">Rp {{ number_format($p->paid_amount, 0, ',', '.') }}</div>
                                <div class="text-[11px] text-slate-400 tnum">{{ $p->last_payment_date->format('d M Y H:i') }}</div>
                            </div>
                        </div>
                        @if($p->payment_notes)
                            <div class="mt-2 ml-12 text-[11px] text-slate-400 line-clamp-1">{{ $p->payment_notes }}</div>
                        @endif
                    </div>
                @endforeach
                @if($recentPayments->isEmpty())
                    <div class="text-center py-8 text-slate-400 text-sm">Belum ada pembayaran tercatat</div>
                @endif
            </div>
        </div>

        <!-- Attention Needed -->
        <div class="bg-white rounded-[20px] border border-slate-200 shadow-[0_2px_6px_rgba(16,24,40,0.06)] p-6">
            <h3 class="text-base font-bold text-rose-600">Perlu perhatian</h3>
            <p class="text-xs text-slate-500 mt-0.5">Customer yang belum lunas.</p>
            <div class="mt-3 divide-y divide-slate-100 max-h-96 overflow-y-auto">
                @foreach($attentionCustomers as $c)
                    <div class="py-3 hover:bg-rose-50/60 -mx-2 px-2 rounded-lg">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-9 h-9 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-slate-900 truncate">{{ $c->name }}</div>
                                    <div class="text-xs text-slate-400 tnum">{{ $c->phone }}</div>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-rose-600 font-semibold tnum text-sm">Rp {{ number_format(max(0, $c->total_amount - $c->paid_amount - $c->discount_amount), 0, ',', '.') }}</div>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium"
                                    @class([
                                        'bg-rose-100 text-rose-600' => $c->payment_status === 'unpaid',
                                        'bg-amber-100 text-amber-600' => $c->payment_status === 'partial',
                                    ])>
                                    @if($c->payment_status === 'unpaid') Belum bayar @else Cicilan @endif
                                </span>
                            </div>
                        </div>
                        <div class="mt-1 ml-12 flex items-center gap-3 text-[11px] text-slate-400 tnum">
                            <span>Tagihan: Rp {{ number_format($c->total_amount, 0, ',', '.') }}</span>
                            <span>Terbayar: Rp {{ number_format($c->paid_amount, 0, ',', '.') }}</span>
                            @if($c->discount_amount > 0)
                                <span class="text-emerald-600">Diskon: Rp {{ number_format($c->discount_amount, 0, ',', '.') }}</span>
                            @endif
                        </div>
                        @if($c->payment_notes)
                            <div class="mt-2 ml-12 text-[11px] text-slate-400 line-clamp-1">{{ $c->payment_notes }}</div>
                        @endif
                    </div>
                @endforeach
                @if($attentionCustomers->isEmpty())
                    <div class="text-center py-8 text-emerald-500 text-sm">
                        <i class="fa-solid fa-check-circle text-2xl mb-2"></i><br>
                        Semua customer sudah lunas
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