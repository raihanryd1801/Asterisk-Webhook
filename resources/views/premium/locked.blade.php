@extends('layouts.app')

@section('content')
<div class="relative min-h-[70vh] rounded-2xl overflow-hidden">
    {{-- Background skeleton blur ala halaman terkunci --}}
    <div class="absolute inset-0 p-6 select-none pointer-events-none" aria-hidden="true">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 blur-[3px] opacity-60">
            @for($i = 0; $i < 6; $i++)
                <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-3">
                    <div class="h-3 w-1/3 bg-slate-200 rounded"></div>
                    <div class="h-6 w-2/3 bg-slate-100 rounded"></div>
                    <div class="h-3 w-full bg-slate-100 rounded"></div>
                    <div class="h-3 w-5/6 bg-slate-100 rounded"></div>
                </div>
            @endfor
        </div>
        <div class="absolute inset-0 bg-slate-100/40"></div>
    </div>

    {{-- Kartu gembok di tengah --}}
    <div class="relative flex items-center justify-center min-h-[70vh] p-6">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-sm w-full p-8 text-center">
            <div class="mx-auto w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 mb-4">
                <i class="fa-solid fa-lock text-lg"></i>
            </div>
            <h2 class="text-lg font-bold text-slate-900">{{ $feature }} is a Premium feature</h2>
            <p class="text-sm text-slate-500 mt-2 leading-relaxed">
                Premium Feature — hubungi admin jika ingin menggunakannya.
            </p>
            <a href="{{ url('/dashboard/overview') }}" class="mt-6 inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium px-5 py-2.5 rounded-xl transition-colors">
                <i class="fa-solid fa-arrow-left text-xs"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
