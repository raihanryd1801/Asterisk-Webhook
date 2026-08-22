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

        <!-- 🚀 TOMBOL FILTER WAKTU (DENGAN TURBO ACTION REPLACE) -->
        <div class="mt-4 md:mt-0 flex items-center space-x-1 bg-white p-1 rounded-lg border border-gray-200 shadow-sm">
            <a href="{{ url('/agent/overview?range=today') }}" 
               data-turbo-action="replace"
               class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ ($range ?? '') === 'today' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
               Today
            </a>
            <a href="{{ url('/agent/overview?range=7_days') }}" 
               data-turbo-action="replace"
               class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ ($range ?? '') === '7_days' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
               7 Days
            </a>
            <a href="{{ url('/agent/overview?range=this_month') }}" 
               data-turbo-action="replace"
               class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ ($range ?? 'this_month') === 'this_month' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
               This Month
            </a>
            <a href="{{ url('/agent/overview?range=all_time') }}" 
               data-turbo-action="replace"
               class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ ($range ?? '') === 'all_time' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
               All Time
            </a>
        </div>
    </div>

    <!-- Today's Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-phone"></i></div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $stats['today_calls'] ?? 0 }}</p>
                <p class="text-xs text-slate-500 font-medium">Calls Today</p>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-check-circle"></i></div>
            <div>
                <p class="text-2xl font-bold text-emerald-600">{{ $stats['paid'] ?? 0 }}</p>
                <p class="text-xs text-slate-500 font-medium">Paid</p>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-handshake"></i></div>
            <div>
                <p class="text-2xl font-bold text-blue-600">{{ $stats['promised'] ?? 0 }}</p>
                <p class="text-xs text-slate-500 font-medium">Promised</p>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-circle-xmark"></i></div>
            <div>
                <p class="text-2xl font-bold text-red-600">{{ $stats['unsuccessful'] ?? 0 }}</p>
                <p class="text-xs text-slate-500 font-medium">Unsuccessful</p>
            </div>
        </div>
    </div>

    <!-- Middle Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Calls</h2>
                <div class="text-3xl font-extrabold text-slate-900">{{ $stats['total_calls'] ?? 0 }}</div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-brand-50 flex items-center justify-center text-brand-600 text-xl">
                <i class="fa-solid fa-phone-volume"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Success Rate</h2>
                <div class="text-3xl font-extrabold text-slate-900">{{ $stats['success_rate'] ?? 0 }}%</div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 text-xl">
                <i class="fa-solid fa-chart-line"></i>
            </div>
        </div>
    </div>

    <!-- All-Time Stats Card -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-6 border-b border-slate-100 pb-4">All-Time Statistics</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div class="space-y-1">
                <p class="text-xs text-slate-400 uppercase">Total Calls</p>
                <p class="text-xl font-bold text-slate-900">{{ $stats['total_calls'] ?? 0 }}</p>
            </div>
            <div class="space-y-1">
                <p class="text-xs text-slate-400 uppercase">Paid</p>
                <p class="text-xl font-bold text-emerald-600">{{ $stats['all_time_paid'] ?? 0 }}</p>
            </div>
            <div class="space-y-1">
                <p class="text-xs text-slate-400 uppercase">Promised</p>
                <p class="text-xl font-bold text-blue-600">{{ $stats['all_time_prom'] ?? 0 }}</p>
            </div>
            <div class="space-y-1">
                <p class="text-xs text-slate-400 uppercase">Overall Rate</p>
                <p class="text-xl font-bold text-red-600">{{ $stats['success_rate'] ?? 0 }}%</p>
            </div>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- AGENT PERFORMANCE TABLE -->
    <!-- ============================================== -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 mt-6 overflow-hidden">
        
        <!-- Header Tabel -->
        <div class="p-6 border-b border-slate-100">
            <h2 class="text-lg font-bold text-slate-800">Agent Performance</h2>
            <p class="text-sm text-slate-500 mt-1">Calls handled by each agent in the selected period. Busiest first.</p>
        </div>
        
        <!-- Isi Tabel -->
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
                <tbody class="divide-y divide-slate-100">
                    
                    @forelse($agentPerformance as $perf)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <!-- Kolom Nama & Ekstensi -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-semibold text-slate-800">{{ $perf['name'] }}</span> 
                            <span class="text-slate-500 ml-1">({{ $perf['extension'] }})</span>
                        </td>
                        
                        <!-- Kolom Total Calls -->
                        <td class="px-6 py-4 whitespace-nowrap text-right font-semibold text-slate-700">
                            {{ number_format($perf['total_calls'], 0, ',', '.') }}
                        </td>
                        
                        <!-- Kolom Connected & Persentase -->
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <span class="font-semibold text-slate-700">{{ number_format($perf['connected_calls'], 0, ',', '.') }}</span>
                            <span class="text-slate-400 text-xs ml-1.5 font-medium">{{ $perf['percentage'] }}%</span>
                        </td>
                        
                        <!-- Kolom Talk Time -->
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