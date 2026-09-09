@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-6">
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-5 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-brand-600"></i> Aging Report
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Detail kasus per bucket (DPD).</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('crm.collection.aging.export') }}" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2">
                <i class="fa-solid fa-file-excel"></i> Export Aging
            </a>
            <a href="{{ route('crm.collection.dashboard') }}" class="bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($buckets as $bucket)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200"
                    @class([
                        'bg-blue-50 border-blue-200' => $bucket === 'Current',
                        'bg-green-50 border-green-200' => $bucket === 'Bucket 1',
                        'bg-yellow-50 border-yellow-200' => $bucket === 'Bucket 2',
                        'bg-orange-50 border-orange-200' => $bucket === 'Bucket 3',
                        'bg-red-50 border-red-200' => $bucket === 'NPL',
                    ])>
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-slate-800 flex items-center gap-2"
                            @class([
                                'text-blue-700' => $bucket === 'Current',
                                'text-green-700' => $bucket === 'Bucket 1',
                                'text-yellow-700' => $bucket === 'Bucket 2',
                                'text-orange-700' => $bucket === 'Bucket 3',
                                'text-red-700' => $bucket === 'NPL',
                            ])>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium border"
                                @class([
                                    'bg-blue-100 text-blue-700 border-blue-200' => $bucket === 'Current',
                                    'bg-green-100 text-green-700 border-green-200' => $bucket === 'Bucket 1',
                                    'bg-yellow-100 text-yellow-700 border-yellow-200' => $bucket === 'Bucket 2',
                                    'bg-orange-100 text-orange-700 border-orange-200' => $bucket === 'Bucket 3',
                                    'bg-red-100 text-red-700 border-red-200' => $bucket === 'NPL',
                                ])>
                                {{ $bucket }}
                            </span>
                        </h3>
                        <span class="text-sm font-bold text-slate-700">{{ $data[$bucket]->total() }} cases</span>
                    </div>
                </div>

                <div class="p-4 max-h-96 overflow-y-auto">
                    @if($data[$bucket]->isEmpty())
                        <div class="text-center py-8 text-slate-400 text-sm">Tidak ada kasus</div>
                    @else
                        <div class="space-y-2">
                            @foreach($data[$bucket] as $c)
                                <div class="border border-slate-200 rounded-lg p-3 hover:bg-slate-50 transition-colors">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-slate-900 truncate">{{ $c->name }}</div>
                                            <div class="text-xs text-slate-500 font-mono">{{ $c->phone }}</div>
                                            <div class="mt-1 flex items-center gap-2 text-[11px] text-slate-500">
                                                <span class="font-mono">Rp {{ number_format($c->total_amount, 0, ',', '.') }}</span>
                                                <span class="text-emerald-600 font-mono">Paid: Rp {{ number_format($c->paid_amount, 0, ',', '.') }}</span>
                                                <span class="text-red-600 font-mono">Out: Rp {{ number_format($c->remaining_amount, 0, ',', '.') }}</span>
                                                @if($c->days_past_due > 0)
                                                    <span class="bg-red-50 text-red-700 px-2 py-0.5 rounded text-[10px]">{{ $c->days_past_due }} DPD</span>
                                                @endif
                                            </div>
                                            @if($c->collector)
                                                <div class="mt-1 text-[11px]">
                                                    <span class="bg-brand-50 text-brand-700 px-2 py-0.5 rounded">{{ $c->collector->name }}</span>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="shrink-0">
                                            <button class="px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs rounded-lg transition-colors">Call</button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        @if($data[$bucket]->hasPages())
                            <div class="mt-4 flex justify-center">
                                {{ $data[$bucket]->withQueryString()->links() }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection