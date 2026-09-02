@extends('layouts.app')

@section('title', 'Overview Dashboard')

@section('content')
<div class="space-y-6">

    <!-- Header Section -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Overview Dashboard</h1>
            <p class="text-sm text-slate-500 mt-0.5">Welcome back, <span class="font-semibold text-brand-600">{{ $roleTitle ?? 'User' }}</span></p>
        </div>
        <div class="bg-white px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
            Today Report
        </div>
    </div>

    <!-- Title & Filter Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Overview</h1>
            <p class="text-sm text-gray-500">How your team is performing.</p>
        </div>

        <!-- 🚀 TOMBOL FILTER AJAX (Bukan Link href lagi) -->
        <div class="mt-4 md:mt-0 flex items-center space-x-1 bg-white p-1 rounded-lg border border-gray-200 shadow-sm" id="filter-buttons">
            <button onclick="switchFilter('today', this)" 
                class="filter-btn px-3 py-1.5 text-xs font-medium rounded-md transition {{ ($range ?? '') === 'today' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                Today
            </button>
            <button onclick="switchFilter('7_days', this)" 
                class="filter-btn px-3 py-1.5 text-xs font-medium rounded-md transition {{ ($range ?? '7_days') === '7_days' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                7 Days
            </button>
            <button onclick="switchFilter('this_month', this)" 
                class="filter-btn px-3 py-1.5 text-xs font-medium rounded-md transition {{ ($range ?? '') === 'this_month' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                This Month
            </button>
            <button onclick="switchFilter('all_time', this)" 
                class="filter-btn px-3 py-1.5 text-xs font-medium rounded-md transition {{ ($range ?? '') === 'all_time' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                All Time
            </button>
        </div>
    </div>

    <!-- Today's Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-phone"></i></div>
            <div>
                <p id="stat-today-calls" class="text-2xl font-bold text-slate-800">{{ $stats['today_calls'] ?? 0 }}</p>
                <p class="text-xs text-slate-500 font-medium">Calls Today</p>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-phone-volume"></i></div>
            <div>
                <p id="stat-today-answered" class="text-2xl font-bold text-emerald-600">{{ $stats['today_answered'] ?? 0 }}</p>
                <p class="text-xs text-slate-500 font-medium">Answered</p>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-phone-slash"></i></div>
            <div>
                <p id="stat-today-unanswered" class="text-2xl font-bold text-red-600">{{ $stats['today_unanswered'] ?? 0 }}</p>
                <p class="text-xs text-slate-500 font-medium">Unanswered</p>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-percent"></i></div>
            <div>
                <p id="stat-today-rate" class="text-2xl font-bold text-blue-600">{{ $stats['today_rate'] ?? 0 }}%</p>
                <p class="text-xs text-slate-500 font-medium">Answer Rate</p>
            </div>
        </div>
    </div>

    <!-- Middle Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Filtered Calls</h2>
                <div id="stat-total-calls" class="text-3xl font-extrabold text-slate-900">{{ number_format($stats['total_calls'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-brand-50 flex items-center justify-center text-brand-600 text-xl">
                <i class="fa-solid fa-headset"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Overall Answer Rate</h2>
                <div id="stat-all-time-rate" class="text-3xl font-extrabold text-slate-900">{{ $stats['all_time_rate'] ?? 0 }}%</div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 text-xl">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
        </div>
    </div>

    <!-- CHARTS SECTION -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: Call Volume (Bar Chart) -->
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="mb-2">
                <h2 class="text-sm font-bold text-slate-800">Call Volume</h2>
                <p id="chart-subtitle" class="text-xs text-slate-500">{{ $chartSubtitle ?? 'Call volume overview.' }}</p>
            </div>
            <div id="callVolumeChart" class="w-full h-[280px]"></div>
        </div>

        <!-- Right: Call Outcomes -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
            <div class="mb-4">
                <h2 class="text-sm font-bold text-slate-800">Call Outcomes</h2>
                <p class="text-xs text-slate-500">How calls ended.</p>
            </div>
            
            <div class="flex items-center gap-4 flex-1">
                <div class="w-1/2 relative flex justify-center">
                    <div id="callOutcomesChart"></div>
                </div>
                
                @php $total = $stats['total_calls'] > 0 ? $stats['total_calls'] : 1; @endphp
                <div class="w-1/2 space-y-2.5">
                    <!-- CANCELLED -->
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> <span class="text-slate-700">Cancelled</span></div>
                        <div><span id="leg-val-0" class="font-bold text-slate-800">{{ $chartOutcomesCounts[0] ?? 0 }}</span> <span id="leg-pct-0" class="text-slate-400 ml-1">{{ round((($chartOutcomesCounts[0] ?? 0) / $total) * 100) }}%</span></div>
                    </div>
                    <!-- NO ANSWER -->
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span> <span class="text-slate-700">No answer</span></div>
                        <div><span id="leg-val-1" class="font-bold text-slate-800">{{ $chartOutcomesCounts[1] ?? 0 }}</span> <span id="leg-pct-1" class="text-slate-400 ml-1">{{ round((($chartOutcomesCounts[1] ?? 0) / $total) * 100) }}%</span></div>
                    </div>
                    <!-- ANSWERED -->
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> <span class="text-slate-700">Answered</span></div>
                        <div><span id="leg-val-2" class="font-bold text-slate-800">{{ $chartOutcomesCounts[2] ?? 0 }}</span> <span id="leg-pct-2" class="text-slate-400 ml-1">{{ round((($chartOutcomesCounts[2] ?? 0) / $total) * 100) }}%</span></div>
                    </div>
                    <!-- BUSY -->
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span> <span class="text-slate-700">Busy</span></div>
                        <div><span id="leg-val-3" class="font-bold text-slate-800">{{ $chartOutcomesCounts[3] ?? 0 }}</span> <span id="leg-pct-3" class="text-slate-400 ml-1">{{ round((($chartOutcomesCounts[3] ?? 0) / $total) * 100) }}%</span></div>
                    </div>
                    <!-- FAILED -->
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> <span class="text-slate-700">Failed</span></div>
                        <div><span id="leg-val-4" class="font-bold text-slate-800">{{ $chartOutcomesCounts[4] ?? 0 }}</span> <span id="leg-pct-4" class="text-slate-400 ml-1">{{ round((($chartOutcomesCounts[4] ?? 0) / $total) * 100) }}%</span></div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-slate-100 text-center">
                <p id="footer-connect-rate" class="text-[11px] text-slate-500">
                    <span class="text-emerald-500 font-bold">● {{ $stats['all_time_rate'] }}%</span> connect rate &middot; {{ number_format($stats['all_answered'], 0, ',', '.') }} of {{ number_format($stats['total_calls'], 0, ',', '.') }} calls
                </p>
            </div>
        </div>

    </div>

    <!-- Period Statistics Summary -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-6 border-b border-slate-100 pb-4">Period Statistics Summary</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div class="space-y-1">
                <p class="text-xs text-slate-400 uppercase">Total Calls</p>
                <p id="period-total-calls" class="text-xl font-bold text-slate-900">{{ number_format($stats['total_calls'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="space-y-1">
                <p class="text-xs text-slate-400 uppercase">Answered</p>
                <p id="period-answered" class="text-xl font-bold text-emerald-600">{{ number_format($stats['all_answered'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="space-y-1">
                <p class="text-xs text-slate-400 uppercase">Unanswered / Failed</p>
                <p id="period-unanswered" class="text-xl font-bold text-red-600">{{ number_format($stats['all_unanswered'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="space-y-1">
                <p class="text-xs text-slate-400 uppercase">Answer Rate</p>
                <p id="period-rate" class="text-xl font-bold text-blue-600">{{ $stats['all_time_rate'] ?? 0 }}%</p>
            </div>
        </div>
    </div>

    <!-- AGENT PERFORMANCE TABLE -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 mt-6 overflow-hidden">
        <div class="p-6 border-b border-slate-100">
            <h2 class="text-lg font-bold text-slate-800">Agent Performance</h2>
            <p class="text-sm text-slate-500 mt-1">Calls handled by each agent in the selected period. Busiest first.</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white border-b border-slate-200">
                        <th class="px-6 py-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Agent</th>
                        <th class="px-6 py-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">Calls</th>
                        <th class="px-6 py-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">Connected</th>
                        <th class="px-6 py-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">Talk Time</th>
                    </tr>
                </thead>
                <tbody id="agent-performance-tbody" class="divide-y divide-slate-100">
                    @forelse($agentPerformance as $perf)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-semibold text-slate-800">{{ $perf['name'] }}</span> 
                            <span class="text-slate-500 ml-1">({{ $perf['extension'] }})</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right font-semibold text-slate-700">
                            {{ number_format($perf['total_calls'], 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <span class="font-semibold text-slate-700">{{ number_format($perf['connected_calls'], 0, ',', '.') }}</span>
                            <span class="text-slate-400 text-xs ml-1.5 font-medium">{{ $perf['percentage'] }}%</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-slate-600">
                            {{ $perf['talk_time'] }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                            <i class="fa-solid fa-folder-open text-3xl mb-3 text-slate-300 block"></i>
                            Tidak ada data panggilan untuk periode ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    // Global variable untuk menyimpan instance grafik dan cache browser
    window.volumeChartInstance = window.volumeChartInstance || null;
    window.outcomeChartInstance = window.outcomeChartInstance || null;
    window.dashboardBrowserCache = window.dashboardBrowserCache || {}; 

    // Helper formatter angka (aman dari Turbo)
    if (typeof window.formatNumber === 'undefined') {
        window.formatNumber = (num) => new Intl.NumberFormat('id-ID').format(num);
    }

    function initCharts() {
        let volumeContainer = document.querySelector("#callVolumeChart");
        let outcomeContainer = document.querySelector("#callOutcomesChart");
        if (!volumeContainer || !outcomeContainer) return;

        if (window.volumeChartInstance) {
            window.volumeChartInstance.destroy();
            window.outcomeChartInstance.destroy();
        }

        // --- 1. Init Call Volume Chart (Animasi Dinyalakan) ---
        let volumeOptions = {
            series: [{ name: 'Calls', data: {!! json_encode($chartVolumeData ?? []) !!} }],
            chart: { 
                type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'inherit',
                animations: { enabled: true, easing: 'easeinout', speed: 800 }
            },
            colors: ['#06b6d4'],
            plotOptions: { bar: { borderRadius: 3, columnWidth: '45%' } },
            dataLabels: { enabled: false },
            stroke: { width: 0 },
            xaxis: {
                categories: {!! json_encode($chartVolumeCategories ?? []) !!},
                axisBorder: { show: false }, axisTicks: { show: false },
                labels: { style: { colors: '#94a3b8', fontSize: '11px' } }
            },
            yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 4, yaxis: { lines: { show: true } } }
        };
        window.volumeChartInstance = new ApexCharts(volumeContainer, volumeOptions);
        window.volumeChartInstance.render();

        // --- 2. Init Call Outcomes Chart ---
        let rawOutcomeData = {!! json_encode($chartOutcomesCounts ?? [0, 0, 0, 0, 0]) !!};
        let allLabels = ['Cancelled', 'No answer', 'Answered', 'Busy', 'Failed'];
        let allColors = ['#3b82f6', '#eab308', '#10b981', '#f97316', '#ef4444'];
        
        let filteredSeries = [], filteredLabels = [], filteredColors = [];
        rawOutcomeData.forEach((val, index) => {
            if (val > 0) {
                filteredSeries.push(val); filteredLabels.push(allLabels[index]); filteredColors.push(allColors[index]);
            }
        });
        if (filteredSeries.length === 0) {
            filteredSeries = [1]; filteredLabels = ['No Data']; filteredColors = ['#cbd5e1'];
        }

        let outcomeOptions = {
            series: filteredSeries, labels: filteredLabels, colors: filteredColors,
            chart: { 
                type: 'donut', height: 220, fontFamily: 'inherit',
                animations: { enabled: true, easing: 'easeinout', speed: 800 }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '75%',
                        labels: {
                            show: true, name: { show: false },
                            value: { show: true, fontSize: '24px', fontWeight: 700, color: '#1e293b', offsetY: 4 },
                            total: {
                                show: true, showAlways: true, label: 'calls', fontSize: '12px', color: '#64748b',
                                formatter: function (w) {
                                    if (w.globals.labels.includes('No Data')) return "0\ncalls";
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0) + "\ncalls"; 
                                }
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: false }, legend: { show: false }, stroke: { width: 0 }
        };
        window.outcomeChartInstance = new ApexCharts(outcomeContainer, outcomeOptions);
        window.outcomeChartInstance.render();
    }

    // ========================================================
    // 🚀 LOGIKA AJAX FETCH INSTAN
    // ========================================================
    async function switchFilter(range, btnElement) {
        
        // 1. Ubah Style Tombol Aktif (Hitam)
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('bg-gray-900', 'text-white');
            btn.classList.add('text-gray-600', 'hover:bg-gray-100');
        });
        btnElement.classList.remove('text-gray-600', 'hover:bg-gray-100');
        btnElement.classList.add('bg-gray-900', 'text-white');

        // 2. Jika Data Sudah Ada di Memori Browser, Render Langsung (0 Detik)
        if (window.dashboardBrowserCache[range]) {
            updateDashboardUI(window.dashboardBrowserCache[range]);
            return;
        }

        // 3. Jika Belum Ada, Fetch ke Backend (Server)
        try {
            let response = await fetch(`{{ route('dashboard.overview') }}?range=${range}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            let data = await response.json();

            // Simpan data di Memori Browser
            window.dashboardBrowserCache[range] = data;
            
            // Render UI Baru
            updateDashboardUI(data);

        } catch (error) {
            console.error("Gagal menarik data:", error);
        }
    }

    function updateDashboardUI(data) {
        // A. Update Teks Statistik Ringkas
        if(document.getElementById('stat-today-calls')) document.getElementById('stat-today-calls').innerText = formatNumber(data.stats.today_calls);
        if(document.getElementById('stat-today-answered')) document.getElementById('stat-today-answered').innerText = formatNumber(data.stats.today_answered);
        if(document.getElementById('stat-today-unanswered')) document.getElementById('stat-today-unanswered').innerText = formatNumber(data.stats.today_unanswered);
        if(document.getElementById('stat-today-rate')) document.getElementById('stat-today-rate').innerText = data.stats.today_rate + '%';
        
        if(document.getElementById('stat-total-calls')) document.getElementById('stat-total-calls').innerText = formatNumber(data.stats.total_calls);
        if(document.getElementById('stat-all-time-rate')) document.getElementById('stat-all-time-rate').innerText = data.stats.all_time_rate + '%';
        
        if(document.getElementById('period-total-calls')) document.getElementById('period-total-calls').innerText = formatNumber(data.stats.total_calls);
        if(document.getElementById('period-answered')) document.getElementById('period-answered').innerText = formatNumber(data.stats.all_answered);
        if(document.getElementById('period-unanswered')) document.getElementById('period-unanswered').innerText = formatNumber(data.stats.all_unanswered);
        if(document.getElementById('period-rate')) document.getElementById('period-rate').innerText = data.stats.all_time_rate + '%';

        // B. Update Chart Volume (Beranimasi)
        if (window.volumeChartInstance) {
            document.getElementById('chart-subtitle').innerText = data.chartSubtitle;
            window.volumeChartInstance.updateOptions({ xaxis: { categories: data.chartVolumeCategories } });
            window.volumeChartInstance.updateSeries([{ name: 'Calls', data: data.chartVolumeData }]);
        }

        // C. Update Donut Chart & Legend
        if (window.outcomeChartInstance) {
            let allLabels = ['Cancelled', 'No answer', 'Answered', 'Busy', 'Failed'];
            let allColors = ['#3b82f6', '#eab308', '#10b981', '#f97316', '#ef4444'];
            let fSeries = [], fLabels = [], fColors = [];
            
            data.chartOutcomesCounts.forEach((val, index) => {
                if (val > 0) { fSeries.push(val); fLabels.push(allLabels[index]); fColors.push(allColors[index]); }
            });
            if (fSeries.length === 0) { fSeries = [1]; fLabels = ['No Data']; fColors = ['#cbd5e1']; }

            window.outcomeChartInstance.updateOptions({ labels: fLabels, colors: fColors });
            window.outcomeChartInstance.updateSeries(fSeries);

            // Update Legend HTML
            let totalOC = data.stats.total_calls > 0 ? data.stats.total_calls : 1;
            for(let i=0; i<5; i++) {
                let val = data.chartOutcomesCounts[i] || 0;
                let pct = Math.round((val / totalOC) * 100);
                if(document.getElementById('leg-val-'+i)) document.getElementById('leg-val-'+i).innerText = formatNumber(val);
                if(document.getElementById('leg-pct-'+i)) document.getElementById('leg-pct-'+i).innerText = pct + '%';
            }
            if(document.getElementById('footer-connect-rate')) {
                document.getElementById('footer-connect-rate').innerHTML = `<span class="text-emerald-500 font-bold">● ${data.stats.all_time_rate}%</span> connect rate &middot; ${formatNumber(data.stats.all_answered)} of ${formatNumber(data.stats.total_calls)} calls`;
            }
        }

        // D. Update Tabel Agent
        let tbody = document.getElementById('agent-performance-tbody');
        if (tbody) {
            tbody.innerHTML = '';
            if (data.agentPerformance.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-slate-500"><i class="fa-solid fa-folder-open text-3xl mb-3 text-slate-300 block"></i> Tidak ada data panggilan untuk periode ini.</td></tr>`;
            } else {
                data.agentPerformance.forEach(agent => {
                    tbody.innerHTML += `
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-semibold text-slate-800">${agent.name}</span> 
                                <span class="text-slate-500 ml-1">(${agent.extension})</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-semibold text-slate-700">${formatNumber(agent.total_calls)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <span class="font-semibold text-slate-700">${formatNumber(agent.connected_calls)}</span>
                                <span class="text-slate-400 text-xs ml-1.5 font-medium">${agent.percentage}%</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-slate-600">${agent.talk_time}</td>
                        </tr>
                    `;
                });
            }
        }
    }

    document.addEventListener("DOMContentLoaded", initCharts);
    document.addEventListener("turbo:load", initCharts);
</script>
@endsection