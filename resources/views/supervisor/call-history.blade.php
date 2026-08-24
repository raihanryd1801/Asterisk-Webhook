@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="callHistoryPage()">
    
    <!-- Header Title -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-5 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-brand-600"></i> Call History & Recordings
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Arsip riwayat percakapan telepon lengkap dengan filter pencarian dan pemutar rekaman.</p>
        </div>
        
        <!-- Tombol Aksi Kanan -->
        <div class="flex items-center gap-2 flex-wrap">
            <!-- 🚀 FIX 1: URL Export Excel -->
            <a :href="'/dashboard/api/call-logs/export?' + new URLSearchParams(filters).toString()" 
               target="_blank"
               class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium shadow-sm transition flex items-center gap-2">
                <i class="fa-solid fa-file-excel text-xs"></i> Export Excel
            </a>
            <button @click="fetchLogs(1)" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium shadow-sm transition flex items-center gap-2">
                <i class="fa-solid fa-rotate text-xs"></i> Refresh Data
            </button>
        </div>
    </div>

    <!-- Panel Filter & Pencarian -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div>
            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Dari Tanggal</label>
            <input type="date" x-model="filters.start_date" class="w-full border border-slate-200 rounded-xl p-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 bg-slate-50">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Sampai Tanggal</label>
            <input type="date" x-model="filters.end_date" class="w-full border border-slate-200 rounded-xl p-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 bg-slate-50">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Cari Nomor / Ext</label>
            <input type="text" x-model="filters.search" @keydown.enter="fetchLogs(1)" placeholder="Cth: 0812 / 105..." class="w-full border border-slate-200 rounded-xl p-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50 font-mono">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Filter Agen</label>
            <select x-model="filters.agent_extension" class="w-full border border-slate-200 rounded-xl p-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50">
                <option value="">-- Semua Agen --</option>
                <template x-for="agent in agents" :key="agent.extension">
                    <option :value="agent.extension" x-text="agent.name + ' (Ext: ' + agent.extension + ')'"></option>
                </template>
            </select>
        </div>
        <div class="flex gap-2">
            <button @click="fetchLogs(1)" class="flex-1 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm py-2.5 rounded-xl transition shadow-sm">
                Cari
            </button>
            <button @click="resetFilters()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-sm px-4 py-2.5 rounded-xl transition">
                Reset
            </button>
        </div>
    </div>

    <!-- Tabel Call History -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50/50 border-b border-slate-100">
                    <tr class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                        <th class="px-6 py-4">Waktu</th>
                        <th class="px-6 py-4">Asal (SRC)</th>
                        <th class="px-6 py-4">Tujuan (DST)</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">SIP Error Code</th>
                        <th class="px-6 py-4">Ditutup Oleh</th>
                        <th class="px-6 py-4">Durasi</th>
                        <th class="px-6 py-4">Rekaman</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="(log, index) in logs" :key="index">
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-4 text-slate-600 num text-xs" x-text="log.calldate"></td>
                            <td class="px-6 py-4 font-mono font-medium text-slate-800" x-text="log.src"></td>
                            <td class="px-6 py-4 font-mono text-brand-600" x-text="log.dst"></td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 text-[10px] font-bold px-2 py-1 rounded-full border uppercase"
                                    :class="log.disposition === 'ANSWERED' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-red-50 text-red-600 border-red-100'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="log.disposition === 'ANSWERED' ? 'bg-emerald-500' : 'bg-red-500'"></span>
                                    <span x-text="log.disposition"></span>
                                </span>
                            </td>

                            <td class="px-6 py-4 font-mono text-xs">
                                <span class="px-2 py-1 rounded font-bold text-[11px]"
                                    :class="(!log.sip_code || log.sip_code.startsWith('200')) ? 'bg-slate-100 text-slate-600' : 'bg-rose-50 text-rose-600 border border-rose-100'"
                                    x-text="log.sip_code || '200 OK'">
                                </span>
                            </td>

                            <td class="px-6 py-4 text-xs">
                                <template x-if="log.terminated_by">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-semibold"
                                        :class="log.terminated_by === 'Agent' ? 'bg-sky-50 text-sky-600 border border-sky-100' : 'bg-amber-50 text-amber-600 border border-amber-100'">
                                        <i :class="log.terminated_by === 'Agent' ? 'fa-solid fa-headset' : 'fa-solid fa-phone-volume'" class="text-[9px]"></i>
                                        <span x-text="log.terminated_by === 'Agent' ? 'Agent' : log.terminated_by"></span>
                                    </span>
                                </template>
                                <template x-if="!log.terminated_by">
                                    <span class="text-slate-300 italic">-</span>
                                </template>
                            </td>

                            <td class="px-6 py-4 text-slate-600 font-mono text-xs" x-text="log.billsec + ' dtk'"></td>
                            
                            <!-- Kolom Rekaman -->
                            <td class="px-6 py-4">
                                <template x-if="log.recordingfile">
                                    <div class="flex items-center gap-2">
                                        <template x-if="playingFile !== log.recordingfile">
                                            <div class="flex items-center gap-2">
                                                <button @click="playAudio(log.recordingfile)" 
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-medium shadow-sm transition active:scale-95">
                                                    <i class="fa-solid fa-play text-[10px] text-brand-600"></i> Listen
                                                </button>
                                                <!-- 🚀 FIX 2: URL Download Rekaman -->
                                                <a :href="'/dashboard/api/play-recording?file=' + log.recordingfile" 
                                                   download
                                                   class="inline-flex items-center justify-center w-7 h-7 rounded-full border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 shadow-sm transition active:scale-95" 
                                                   title="Download Rekaman">
                                                    <i class="fa-solid fa-download text-[10px]"></i>
                                                </a>
                                            </div>
                                        </template>

                                        <template x-if="playingFile === log.recordingfile">
                                            <div class="flex items-center gap-2 bg-slate-100 px-3 py-1 rounded-full border border-slate-200">
                                                <button @click="stopAudio()" class="text-slate-700 hover:text-red-600 transition" title="Stop">
                                                    <i class="fa-solid fa-pause text-xs"></i>
                                                </button>
                                                <span class="text-[11px] font-mono text-slate-600" x-text="audioProgress"></span>
                                                <button @click="stopAudio()" class="text-slate-400 hover:text-slate-700 ml-1" title="Tutup">
                                                    <i class="fa-solid fa-xmark text-xs"></i>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!log.recordingfile">
                                    <span class="text-[10px] text-slate-400 italic">No record</span>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 bg-slate-50 border-t flex flex-col sm:flex-row justify-between items-center gap-4">
            <span class="text-[11px] text-slate-500 font-medium">
                Total <strong class="text-slate-700" x-text="pagination.total"></strong> data
            </span>
            
            <nav class="flex items-center gap-1">
                <button @click="fetchLogs(pagination.current_page - 1)" 
                        :disabled="pagination.current_page === 1"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                </button>

                <template x-for="page in getPaginationPages()" :key="page">
                    <button @click="typeof page === 'number' ? fetchLogs(page) : null"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all border"
                            :class="page === pagination.current_page ? 'bg-brand-600 text-white border-brand-600' : (page === '...' ? 'border-transparent text-slate-400 cursor-default' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50') "
                            x-text="page"
                            :disabled="page === '...'">
                    </button>
                </template>

                <button @click="fetchLogs(pagination.current_page + 1)" 
                        :disabled="pagination.current_page === pagination.last_page"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </button>
            </nav>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function callHistoryPage() {
        return {
            logs: [],
            agents: [],
            playingFile: null,
            currentAudio: null,
            audioProgress: '0:00 / 0:00',
            progressInterval: null,
            pagination: { current_page: 1, last_page: 1, total: 0 },
            filters: { start_date: '', end_date: '', search: '', agent_extension: '' },
            
            init() {
                this.fetchAgentsList();
                this.fetchLogs(1);
            },
            
            formatTime(seconds) {
                let m = Math.floor(seconds / 60);
                let s = Math.floor(seconds % 60);
                return m + ':' + (s < 10 ? '0' : '') + s;
            },

            playAudio(filename) {
                if (this.currentAudio) {
                    this.currentAudio.pause();
                    clearInterval(this.progressInterval);
                }

                this.playingFile = filename;
                // 🚀 FIX 3: URL Audio Player
                let audioUrl = '/dashboard/api/play-recording?file=' + filename;
                this.currentAudio = new Audio(audioUrl);
                
                this.currentAudio.play().then(() => {
                    this.currentAudio.onloadedmetadata = () => {
                        let dur = this.currentAudio.duration;
                        this.audioProgress = '0:00 / ' + this.formatTime(dur);
                    };

                    this.progressInterval = setInterval(() => {
                        if (this.currentAudio) {
                            let curr = this.currentAudio.currentTime;
                            let dur = this.currentAudio.duration || 0;
                            this.audioProgress = this.formatTime(curr) + ' / ' + this.formatTime(dur);
                        }
                    }, 500);
                }).catch(err => {
                    alert('Gagal memutar audio: File tidak ditemukan.');
                    this.stopAudio();
                });

                this.currentAudio.onended = () => {
                    this.stopAudio();
                };
            },

            stopAudio() {
                if (this.currentAudio) {
                    this.currentAudio.pause();
                    this.currentAudio = null;
                }
                clearInterval(this.progressInterval);
                this.playingFile = null;
                this.audioProgress = '0:00 / 0:00';
            },

            getPaginationPages() {
                let current = this.pagination.current_page;
                let last = this.pagination.last_page;
                let delta = 2;
                let range = [];
                
                for (let i = 1; i <= last; i++) {
                    if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) {
                        range.push(i);
                    } else if (range[range.length - 1] !== '...') {
                        range.push('...');
                    }
                }
                return range;
            },

            fetchAgentsList() {
                fetch('/dashboard/api/live-agents', { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => { this.agents = data.agents || []; });
            },

            fetchLogs(page) {
                let params = new URLSearchParams({
                    page: page,
                    start_date: this.filters.start_date,
                    end_date: this.filters.end_date,
                    search: this.filters.search,
                    agent_extension: this.filters.agent_extension
                });

                // 🚀 FIX 4: URL Fetch Data
                fetch(`/dashboard/api/call-logs?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(response => {
                    if (response.status === 'success') {
                        this.logs = response.data.data;
                        this.pagination = {
                            current_page: response.data.current_page,
                            last_page: response.data.last_page,
                            total: response.data.total
                        };
                    }
                });
            },
            
            resetFilters() {
                this.stopAudio();
                this.filters = { start_date: '', end_date: '', search: '', agent_extension: '' };
                this.fetchLogs(1);
            }
        }
    }
</script>
@endsection