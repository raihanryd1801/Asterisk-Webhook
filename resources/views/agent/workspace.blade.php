@extends('layouts.app')

@section('title', 'Agent Workspace')

<!-- 🚀 TURBO HOTWIRE CACHE CONTROL -->
@push('head')
<meta name="turbo-cache-control" content="no-cache">
@endpush

@section('content')

<!-- 🚀 HAPUS data-turbo="false" AGAR PINDAH MENU MULUS -->
<div class="w-full space-y-6" x-data="agentWorkspaceData('{{ $extension }}')">
    
    <!-- Header Workspace -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-6 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-headset text-brand-600"></i> Agent Workspace 
                <span class="text-brand-600 font-mono text-xs bg-white px-3 py-1 rounded-full border border-brand-200 shadow-sm">Ext: {{ $extension }}</span>
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Control Panel, Click-to-Call, & Riwayat Catatan Panggilan</p>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- BAGIAN 1: STATUS TOOLBAR (FULL WIDTH BAR)      -->
    <!-- ============================================== -->
    <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:px-6 shadow-sm flex flex-col xl:flex-row xl:items-center justify-between gap-4 relative z-50">
        
        <div class="flex flex-wrap items-center gap-4 sm:gap-6">
            
            <!-- Area Status & Dropdown -->
            <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-full px-4 py-1.5 shadow-inner" x-data="{ statusMenu: false }">
                <span class="text-[11px] font-bold text-slate-500 tracking-widest uppercase">Status</span>
                
                <span class="px-3 py-1 rounded-full text-[11px] font-bold border capitalize bg-white shadow-sm"
                      :class="{
                          'text-emerald-600 border-emerald-200': currentStatus === 'online',
                          'text-amber-600 border-amber-200': currentStatus !== 'online' && currentStatus !== 'offline',
                          'text-slate-500 border-slate-200': currentStatus === 'offline'
                      }" x-text="currentStatus === 'online' ? 'Online' : currentStatus">
                </span>
                
                <!-- Tombol Dropdown -->
                <div class="relative ml-1">
                    <button @click="statusMenu = !statusMenu" @click.outside="statusMenu = false" 
                            class="px-4 py-1.5 rounded-full bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-brand-600 flex items-center gap-2 shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <i class="fa-solid fa-sliders text-slate-400"></i> Ubah Status
                    </button>
                    
                    <!-- Dropdown Menu (Muncul ke bawah) -->
                    <div x-show="statusMenu" x-transition.opacity.duration.200ms style="display: none;" 
                         class="absolute top-full left-0 mt-3 w-52 bg-white border border-slate-200 rounded-xl shadow-xl py-2 z-[99]">
                        <div class="px-4 py-2 border-b border-slate-100 mb-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pilih Ketersediaan</span>
                        </div>
                        <button @click="updateStatus('online'); statusMenu = false" class="w-full text-left px-5 py-2.5 text-sm text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 flex items-center gap-3 transition-colors">
                            <i class="fa-solid fa-circle-check text-emerald-500 w-4"></i> Online (Ready)
                        </button>
                        <button @click="updateStatus('break'); statusMenu = false" class="w-full text-left px-5 py-2.5 text-sm text-slate-700 hover:bg-amber-50 hover:text-amber-700 flex items-center gap-3 transition-colors">
                            <i class="fa-solid fa-mug-hot text-amber-500 w-4"></i> Rest / Break
                        </button>
                        <button @click="updateStatus('lunch'); statusMenu = false" class="w-full text-left px-5 py-2.5 text-sm text-slate-700 hover:bg-amber-50 hover:text-amber-700 flex items-center gap-3 transition-colors">
                            <i class="fa-solid fa-utensils text-amber-500 w-4"></i> Lunch
                        </button>
                        <button @click="updateStatus('prayer'); statusMenu = false" class="w-full text-left px-5 py-2.5 text-sm text-slate-700 hover:bg-amber-50 hover:text-amber-700 flex items-center gap-3 transition-colors">
                            <i class="fa-solid fa-person-praying text-amber-500 w-4"></i> Praying
                        </button>
                    </div>
                </div>
            </div>

            <div class="h-6 w-px bg-slate-200 hidden md:block"></div>

            <!-- Softphone Status -->
            <div class="flex items-center gap-3">
                <span class="text-[11px] font-bold text-slate-500 tracking-widest uppercase">Softphone</span>
                <span class="px-3 py-1.5 rounded-full text-[11px] font-bold border flex items-center gap-1.5 transition-colors shadow-sm bg-white"
                      :class="currentStatus === 'online' ? 'border-emerald-200 text-emerald-600' : 'border-rose-200 text-rose-600'">
                    <i class="fa-solid fa-phone" :class="currentStatus === 'online' ? 'animate-pulse' : ''"></i>
                    <span x-text="currentStatus === 'online' ? 'Ready' : 'Locked'"></span>
                </span>
            </div>

            <div class="h-6 w-px bg-slate-200 hidden md:block"></div>

            <!-- Queue Indicator -->
            <div class="flex items-center gap-3">
                <span class="text-[11px] font-bold text-slate-500 tracking-widest uppercase">Queue</span>
                <span class="px-3 py-1.5 bg-slate-800 rounded-md text-xs font-bold text-white shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-headphones-simple text-[10px]"></i> Manual Dial
                </span>
            </div>
        </div>

        <!-- Info Tambahan di kanan toolbar -->
        <div class="hidden xl:flex items-center text-[11px] text-slate-500 bg-slate-50 border border-slate-100 rounded-lg px-4 py-2">
            <i class="fa-solid fa-circle-info text-brand-500 mr-2"></i> 
            <span>Pastikan status <strong class="text-emerald-600">Online</strong> untuk membuka kunci panggilan.</span>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- BAGIAN 2: DIALER TEPAT DI TENGAH (CENTERED)    -->
    <!-- ============================================== -->
    <div class="flex justify-center w-full relative z-10 mt-6">
        <div class="w-full sm:max-w-sm shrink-0">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 space-y-4 relative overflow-hidden flex flex-col justify-between w-full">
                
                <div class="text-center mb-2">
                    <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-1">Click to Call</h2>
                    <p class="text-[11px] text-slate-400">Dial outbound via SIP softphone MicroSIP.</p>
                </div>

                <!-- 🔒 OVERLAY KUNCI -->
                <template x-if="currentStatus !== 'online'">
                    <div class="absolute inset-0 bg-white/95 backdrop-blur-[4px] z-20 flex flex-col items-center justify-center p-6 text-center rounded-3xl border border-rose-100/50">
                        <div class="w-16 h-16 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mb-4 shadow-sm border border-rose-100">
                            <i class="fa-solid fa-lock text-2xl"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800">Panel Terkunci</h3>
                        <p class="text-xs text-slate-500 mt-2 max-w-[200px] leading-relaxed">
                            Status Anda <strong class="uppercase text-slate-700" x-text="currentStatus"></strong>. <br>Ubah ke <strong class="text-emerald-600 font-semibold">Online</strong> di menu atas.
                        </p>
                    </div>
                </template>

                <div class="space-y-4 w-full">
                    <div class="relative">
                        <input type="text" x-model="targetNumber" placeholder="Nomor tujuan..." class="w-full border border-slate-200 rounded-xl p-4 text-center text-2xl outline-none font-mono bg-slate-50 focus:ring-2 focus:ring-brand-500 pr-12 tracking-widest text-slate-800 shadow-inner">
                        <button @click="targetNumber = targetNumber.slice(0, -1)" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-rose-500 transition-colors">
                            <i class="fa-solid fa-delete-left text-lg"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-3 gap-3 pt-2">
                        <template x-for="num in ['1','2','3','4','5','6','7','8','9','*','0','#']">
                            <button @click="targetNumber += num" class="w-14 h-14 mx-auto rounded-full border border-slate-200 text-slate-700 font-medium hover:bg-slate-100 hover:border-slate-300 active:scale-95 transition-all num text-lg flex items-center justify-center shadow-sm bg-white" x-text="num"></button>
                        </template>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button @click="makeCall()" :disabled="currentStatus !== 'online'" class="flex-1 bg-brand-600 hover:bg-brand-700 disabled:bg-slate-200 disabled:text-slate-400 text-white font-medium text-sm py-3 px-4 rounded-xl transition shadow-md flex items-center justify-center gap-2">
                            <i class="fa-solid fa-phone"></i> Panggil Sekarang
                        </button>
                        <button @click="targetNumber = ''" class="px-5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-sm rounded-xl transition shadow-sm border border-slate-200">
                            Clear
                        </button>
                    </div>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 text-[11px] text-slate-500 flex items-center justify-center gap-2 mt-2">
                    <i class="fa-solid fa-tower-cell text-brand-500"></i>
                    <span x-text="infoMessage"></span>
                </div>
            </div>
        </div>
    </div>

    <hr class="border-slate-200 border-dashed my-8">

    <!-- ============================================== -->
    <!-- BAGIAN 3: RIWAYAT & CATATAN (FULL WIDTH)       -->
    <!-- ============================================== -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden w-full">
        
        <!-- Header & Pencarian -->
        <div class="p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/50">
            <div>
                <h2 class="text-base font-bold text-slate-800">Riwayat Panggilan & Catatan Interaksi</h2>
                <p class="text-xs text-slate-500">Tuliskan alasan atau catatan prospek pada setiap panggilan.</p>
            </div>
            
            <div class="flex items-center gap-2 w-full md:w-auto">
                <input type="text" x-model="filters.search" @keydown.enter="fetchLogs(1)" placeholder="Cari nomor..." class="w-full md:w-48 border border-slate-200 rounded-lg p-2 text-xs outline-none focus:ring-2 focus:ring-brand-500 bg-white shadow-sm">
                <button @click="fetchLogs(1)" class="bg-slate-800 hover:bg-slate-900 text-white px-3 py-2 rounded-lg text-xs font-semibold shadow-sm transition whitespace-nowrap">
                    <i class="fa-solid fa-search"></i> Cari
                </button>
                <button @click="fetchLogs(1)" class="bg-brand-600 hover:bg-brand-700 text-white px-3 py-2 rounded-lg text-xs font-semibold shadow-sm transition whitespace-nowrap" title="Refresh">
                    <i class="fa-solid fa-rotate"></i>
                </button>
            </div>
        </div>

        <div class="p-5 bg-slate-50">
            
            <!-- 🚀 LOADING SPINNER -->
            <template x-if="isLoading">
                <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                    <i class="fa-solid fa-circle-notch fa-spin text-4xl mb-4 text-brand-500"></i>
                    <p class="text-xs font-medium animate-pulse tracking-wide">Memuat riwayat panggilan...</p>
                </div>
            </template>

            <!-- 🚀 PESAN DATA KOSONG -->
            <template x-if="!isLoading && logs.length === 0">
                <div class="text-center py-10 text-slate-400 text-sm border-2 border-dashed border-slate-200 rounded-xl bg-white">
                    <i class="fa-regular fa-folder-open text-3xl text-slate-300 mb-3 block"></i>
                    Belum ada data riwayat panggilan.
                </div>
            </template>

            <!-- 🚀 TAMPILAN DATA -->
            <div class="space-y-3" x-show="!isLoading">
                <template x-for="(log, index) in logs" :key="log.uniqueid || index">
                    <div class="border border-slate-300 rounded-lg bg-white overflow-hidden shadow-sm hover:border-slate-400 transition-colors">
                        
                        <!-- Main Info Row -->
                        <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-4 sm:gap-6 flex-wrap flex-1">
                                <span class="font-bold text-slate-800 text-[15px] min-w-[120px]" x-text="log.dst"></span>
                                
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full border whitespace-nowrap"
                                      :class="{
                                          'bg-emerald-50 text-emerald-600 border-emerald-200': (log.disposition || '').match(/ANSWERED|completed/i),
                                          'bg-amber-50 text-amber-600 border-amber-200': (log.disposition || '').match(/NO ANSWER|busy/i),
                                          'bg-slate-50 text-slate-600 border-slate-200': !(log.disposition || '').match(/ANSWERED|completed|NO ANSWER|busy/i)
                                      }">
                                    <span class="w-1.5 h-1.5 rounded-full" 
                                          :class="{
                                              'bg-emerald-500': (log.disposition || '').match(/ANSWERED|completed/i),
                                              'bg-amber-500': (log.disposition || '').match(/NO ANSWER|busy/i),
                                              'bg-slate-400': !(log.disposition || '').match(/ANSWERED|completed|NO ANSWER|busy/i)
                                          }"></span>
                                    <span x-text="log.disposition"></span>
                                </span>

                                <span class="border border-slate-200 rounded px-2 py-0.5 text-[11px] text-slate-500 font-mono bg-slate-50 shadow-sm"
                                      x-show="log.sip_code" x-text="log.sip_code" title="SIP Code"></span>
                                
                                <span class="text-[12px] text-slate-500 whitespace-nowrap" x-text="log.calldate + (log.billsec > 0 ? ' • ' + log.billsec + ' dtk' : '')"></span>
                            </div>
                            
                            <div class="shrink-0">
                                <button @click="log.showNoteInput = !log.showNoteInput" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-slate-300 rounded-md text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors shadow-sm bg-white">
                                    <i class="fa-regular fa-pen-to-square text-slate-400"></i> Add note
                                </button>
                            </div>
                        </div>

                        <!-- Display Catatan -->
                        <div x-show="log.notes && !log.showNoteInput" class="px-4 pb-3 pt-0 text-[13px] text-slate-600">
                            <span x-text="log.notes"></span>
                        </div>

                        <!-- Dropdown Input (Mode Edit) -->
                        <div x-show="log.showNoteInput" x-transition.opacity class="border-t border-slate-100 bg-slate-50 p-4 flex flex-col sm:flex-row gap-3">
                            <input type="text" 
                                   x-model="log.notes" 
                                   @keydown.enter="saveNote(log)"
                                   class="flex-1 border border-slate-300 rounded-md px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 bg-white shadow-inner" 
                                   placeholder="Tulis alasan atau hasil panggilan di sini...">
                            
                            <button @click="saveNote(log)" 
                                    class="bg-brand-600 hover:bg-brand-700 text-white text-xs px-5 py-2 rounded-md transition-all flex items-center justify-center gap-1.5 shadow-sm shrink-0 font-medium"
                                    :class="{'opacity-50 cursor-not-allowed': log.isSaving}"
                                    :disabled="log.isSaving">
                                <i class="fa-solid" :class="log.isSaving ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                                <span x-text="log.isSaving ? 'Menyimpan...' : 'Simpan'"></span>
                            </button>
                        </div>
                        
                    </div>
                </template>
            </div>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 bg-white border-t flex flex-col sm:flex-row justify-between items-center gap-4">
            <span class="text-[11px] text-slate-500 font-medium">
                Total <strong class="text-slate-700" x-text="pagination.total"></strong> riwayat
            </span>
            <nav class="flex items-center gap-1">
                <button @click="fetchLogs(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="px-2 py-1 rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-50 shadow-sm"><i class="fa-solid fa-chevron-left text-[10px]"></i></button>
                <template x-for="page in getPaginationPages()" :key="page">
                    <button @click="typeof page === 'number' ? fetchLogs(page) : null" class="px-2.5 py-1 rounded-md text-[11px] font-bold transition-all border shadow-sm" :class="page === pagination.current_page ? 'bg-brand-600 text-white border-brand-600' : (page === '...' ? 'border-transparent text-slate-400 cursor-default shadow-none' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50')" x-text="page" :disabled="page === '...'"></button>
                </template>
                <button @click="fetchLogs(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="px-2 py-1 rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-50 shadow-sm"><i class="fa-solid fa-chevron-right text-[10px]"></i></button>
            </nav>
        </div>

    </div>

</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('agentWorkspaceData', (extension) => ({
            extension: extension,
            currentStatus: 'offline', 
            targetNumber: '',
            infoMessage: 'MikroSIP siap digunakan...',
            logs: [],
            pagination: { current_page: 1, last_page: 1, total: 0 },
            filters: { search: '' },
            statusInterval: null, 
            isLoading: true, // 🚀 TAMBAHKAN STATE LOADING

            init() {
                this.fetchAgentStatus();
                this.statusInterval = setInterval(() => { this.fetchAgentStatus(); }, 5000);
                this.fetchLogs(1);
            },

            destroy() {
                if (this.statusInterval) {
                    clearInterval(this.statusInterval);
                }
            },

            fetchAgentStatus() {
                fetch('/dashboard/api/live-agents', { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    let agentList = Array.isArray(data) ? data : (data.agents || []);
                    let currentAgent = agentList.find(a => String(a.extension) === String(this.extension));
                    if (currentAgent) {
                        this.currentStatus = currentAgent.status;
                    }
                }).catch(err => console.error("Gagal sinkronisasi status"));
            },

            updateStatus(newStatus) {
                fetch(`/dashboard/api/agent/${this.extension}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ status: newStatus })
                })
                .then(res => res.json())
                .then(response => {
                    if (response.status === 'success') {
                        this.currentStatus = newStatus;
                        this.infoMessage = `Status berhasil diubah menjadi ${newStatus}`;
                    }
                });
            },

            makeCall() {
                if (!this.targetNumber) {
                    alert('Masukkan nomor tujuan terlebih dahulu!');
                    return;
                }
                this.infoMessage = 'Menghubungkan panggilan ke MicroSIP...';
                
                fetch(`/dashboard/api/agent/click-to-call`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    },
                    body: JSON.stringify({
                        extension: this.extension,
                        destination: this.targetNumber
                    })
                })
                .then(res => res.json().then(data => ({ ok: res.ok, data })))
                .then(({ ok, data }) => {
                    this.infoMessage = data.message;
                    setTimeout(() => { this.fetchLogs(1); }, 5000);
                })
                .catch(err => {
                    this.infoMessage = 'Gagal terhubung ke server backend.';
                });
            },

            fetchLogs(page) {
                this.isLoading = true; // 🚀 Aktifkan Loading
                
                let params = new URLSearchParams({
                    page: page,
                    search: this.filters.search
                });

                fetch(`/dashboard/api/call-logs?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(response => {
                    if (response.status === 'success') {
                        this.logs = response.data.data.map(log => ({ 
                            ...log, 
                            isSaving: false,
                            showNoteInput: false 
                        }));
                        this.pagination = { current_page: response.data.current_page, last_page: response.data.last_page, total: response.data.total };
                    }
                })
                .finally(() => {
                    this.isLoading = false; // 🚀 Matikan loading saat selesai (berhasil/gagal)
                });
            },
            
            async saveNote(log) {
                if (!log || !log.uniqueid) {
                    alert('Error: Unique ID panggilan ini tidak ditemukan!');
                    return;
                }

                log.isSaving = true;

                try {
                    let response = await fetch(`/dashboard/api/call-logs/${log.uniqueid}/note`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ notes: log.notes })
                    });

                    let rawText = await response.text();
                    
                    let data;
                    try {
                        data = JSON.parse(rawText);
                    } catch (e) {
                        throw new Error("Server mengembalikan HTML/Bukan JSON.");
                    }

                    if (response.ok && data.status === 'success') {
                        log.showNoteInput = false;
                    } else {
                        alert('Gagal dari Server: ' + (data.message || 'Pesan tidak diketahui'));
                    }

                } catch (err) {
                    alert('Gagal menyimpan catatan: ' + err.message);
                } finally {
                    log.isSaving = false; 
                }
            },

            getPaginationPages() {
                let current = this.pagination.current_page; 
                let last = this.pagination.last_page; 
                let delta = 2; 
                let range = [];
                for (let i = 1; i <= last; i++) {
                    if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) { range.push(i); } 
                    else if (range[range.length - 1] !== '...') { range.push('...'); }
                }
                return range;
            }
        }));
    });
</script>
@endsection