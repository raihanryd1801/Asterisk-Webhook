@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="callHistoryPage()">
    
    <!-- Header Title -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-5 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-brand-600"></i> Call History & Recordings
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Arsip riwayat percakapan telepon lengkap dengan pemutar file rekaman audio.</p>
        </div>
        <button @click="fetchLogs" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium shadow-sm transition flex items-center gap-2">
            <i class="fa-solid fa-rotate text-xs"></i> Refresh Data
        </button>
    </div>

    <!-- Tabel Call History -->
    <div class="bg-white shadow-sm rounded-2xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead>
                    <tr class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-100 bg-slate-50/50">
                        <th class="px-6 py-3.5 font-semibold">Waktu</th>
                        <th class="px-6 py-3.5 font-semibold">Asal (Src)</th>
                        <th class="px-6 py-3.5 font-semibold">Tujuan (Dst)</th>
                        <th class="px-6 py-3.5 font-semibold">Status</th>
                        <th class="px-6 py-3.5 font-semibold">Durasi</th>
                        <th class="px-6 py-3.5 font-semibold">Rekaman Audio</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="log in logs" :key="log.calldate + log.src + Math.random()">
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-3.5 text-slate-600 num" x-text="log.calldate"></td>
                            <td class="px-6 py-3.5 font-mono font-medium text-slate-800" x-text="log.src"></td>
                            <td class="px-6 py-3.5 font-mono font-medium text-brand-600" x-text="log.dst"></td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full border"
                                      :class="log.disposition === 'ANSWERED' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-red-50 text-red-600 border-red-100'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="log.disposition === 'ANSWERED' ? 'bg-emerald-500' : 'bg-red-500'"></span>
                                    <span x-text="log.disposition"></span>
                                </span>
                            </td>
                            <td class="px-6 py-3.5 font-mono text-slate-600 num" x-text="log.duration + 's'"></td>
                            <td class="px-6 py-3.5">
                                <div x-show="log.recordingfile" class="flex items-center gap-2">
                                    <audio controls class="h-9 w-48 accent-brand-600" preload="none" :src="'/api/supervisor/play-recording?file=' + log.recordingfile"></audio>
                                </div>
                                <span x-show="!log.recordingfile" class="text-slate-400 text-xs italic">Tidak ada rekaman</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    function callHistoryPage() {
        return {
            logs: [],
            init() {
                this.fetchLogs();
            },
            fetchLogs() {
                fetch('/api/supervisor/call-logs')
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            this.logs = data.data;
                        }
                    });
            }
        }
    }
</script>
@endsection
