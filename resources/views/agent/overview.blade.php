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

    <!-- Today's Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-phone"></i></div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $stats['today_calls'] }}</p>
                <p class="text-xs text-slate-500 font-medium">Calls Today</p>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-check-circle"></i></div>
            <div>
                <p class="text-2xl font-bold text-emerald-600">{{ $stats['paid'] }}</p>
                <p class="text-xs text-slate-500 font-medium">Paid</p>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-handshake"></i></div>
            <div>
                <p class="text-2xl font-bold text-blue-600">{{ $stats['promised'] }}</p>
                <p class="text-xs text-slate-500 font-medium">Promised</p>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-circle-xmark"></i></div>
            <div>
                <p class="text-2xl font-bold text-red-600">{{ $stats['unsuccessful'] }}</p>
                <p class="text-xs text-slate-500 font-medium">Unsuccessful</p>
            </div>
        </div>
    </div>

    <!-- Middle Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Calls</h2>
                <div class="text-3xl font-extrabold text-slate-900">{{ $stats['total_calls'] }}</div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-brand-50 flex items-center justify-center text-brand-600 text-xl">
                <i class="fa-solid fa-phone-volume"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Success Rate</h2>
                <div class="text-3xl font-extrabold text-slate-900">{{ $stats['success_rate'] }}%</div>
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
                <p class="text-xl font-bold text-slate-900">{{ $stats['total_calls'] }}</p>
            </div>
            <div class="space-y-1">
                <p class="text-xs text-slate-400 uppercase">Paid</p>
                <p class="text-xl font-bold text-emerald-600">{{ $stats['all_time_paid'] }}</p>
            </div>
            <div class="space-y-1">
                <p class="text-xs text-slate-400 uppercase">Promised</p>
                <p class="text-xl font-bold text-blue-600">{{ $stats['all_time_prom'] }}</p>
            </div>
            <div class="space-y-1">
                <p class="text-xs text-slate-400 uppercase">Overall Rate</p>
                <p class="text-xl font-bold text-red-600">{{ $stats['success_rate'] }}%</p>
            </div>
        </div>
    </div>

</div>
@endsection