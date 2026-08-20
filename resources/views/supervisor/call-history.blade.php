@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="callHistoryPage()">
    
    <!-- Header Title -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Call History & Recordings</h1>
            <p class="text-xs text-slate-500 mt-1">Arsip riwayat percakapan telepon lengkap dengan pemutar file rekaman audio.</p>
        </div>
        <button @click="fetchLogs" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition">
            Refresh Data
        </button>
    </div>

    <!-- Tabel Call History -->
    <div class="bg-white shadow-sm rounded-xl p-6 border border-slate-200">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs text-slate-500 uppercase">
                        <th class="p-3">Waktu</th>
                        <th class="p-3">Asal (Src)</th>
                        <th class="p-3">Tujuan (Dst)</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Durasi</th>
                        <th class="p-3">Rekaman Audio</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-sm">
                    <template x-for="log in logs" :key="log.calldate + log.src + Math.random()">
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-3 text-slate-600" x-text="log.calldate"></td>
                            <td class="p-3 font-mono font-bold text-slate-800" x-text="log.src"></td>
                            <td class="p-3 font-mono font-bold text-indigo-600" x-text="log.dst"></td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                      :class="log.disposition === 'ANSWERED' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                      x-text="log.disposition"></span>
                            </td>
                            <td class="p-3 font-mono text-slate-600" x-text="log.duration + 's'"></td>
                            <td class="p-3">
                                <div x-show="log.recordingfile">
                                    <audio controls class="h-8 w-48" preload="none" :src="'/api/supervisor/play-recording?file=' + log.recordingfile"></audio>
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