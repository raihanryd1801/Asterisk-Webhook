@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="supervisorDashboard()">
    
    <!-- Header Title & Deskripsi -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-5 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-brand-600"></i> Live Agents Monitoring
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Siapa yang sedang bekerja, dan panggilan apa yang sedang aktif saat ini secara real-time.</p>
        </div>
    </div>

    <!-- 🚀 Kotak Statistik Ringkas (Diubah jadi 5 Kolom) -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-user-check"></i></div>
            <div>
                <p class="text-2xl font-bold text-slate-800 num" x-text="stats.online">0</p>
                <p class="text-xs text-slate-500 font-medium">Online Agents</p>
            </div>
        </div>
        
        <!-- 🚀 KOTAK BARU: IN CALL -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-headset"></i></div>
            <div>
                <p class="text-2xl font-bold text-slate-800 num" x-text="stats.incall">0</p>
                <p class="text-xs text-slate-500 font-medium">In Call (Aktif)</p>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-mug-hot"></i></div>
            <div>
                <p class="text-2xl font-bold text-slate-800 num" x-text="stats.break">0</p>
                <p class="text-xs text-slate-500 font-medium">On Break</p>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-user-slash"></i></div>
            <div>
                <p class="text-2xl font-bold text-slate-800 num" x-text="stats.offline">0</p>
                <p class="text-xs text-slate-500 font-medium">Offline</p>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-users"></i></div>
            <div>
                <p class="text-2xl font-bold text-slate-800 num" x-text="agents.length">0</p>
                <p class="text-xs text-slate-500 font-medium">Total Agents</p>
            </div>
        </div>
    </div>

    
    <!-- GRID CARD AGEN -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <template x-for="agent in agents" :key="agent.id">
            <div class="bg-white rounded-2xl shadow-sm border flex flex-col justify-between transition-all duration-300 hover:shadow-lg hover:-translate-y-1 relative overflow-hidden group"
                 :class="{
                     'border-slate-200': !agent.is_calling && agent.status !== 'offline',
                     'border-slate-200/50 bg-slate-50/50 opacity-70': agent.status === 'offline',
                     'border-amber-300 ring-4 ring-amber-500/10': agent.call_status === 'ringing',
                     'border-emerald-300 ring-4 ring-emerald-500/10': agent.call_status === 'connected'
                 }">
                  
                <!-- Animated pulse background for connected -->
                <div x-show="agent.call_status === 'connected'" class="absolute inset-0 bg-gradient-to-b from-emerald-50/50 to-transparent animate-pulse z-0 pointer-events-none"></div>

                <!-- Bagian Atas: Info Agen -->
                <div class="p-5 relative z-10 flex-1">
                    <div class="flex justify-between items-start mb-5">
                        <div class="flex items-center gap-3">
                            <!-- Avatar Cantik -->
                            <div class="relative">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-slate-100 to-slate-200 text-slate-500 flex items-center justify-center text-lg shadow-inner border-2 border-white">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <!-- Status Dot di pojok avatar -->
                                <span class="absolute bottom-0.5 right-0.5 w-3 h-3 rounded-full border-2 border-white" 
                                      :class="{ 'bg-emerald-500': agent.status === 'online', 'bg-amber-500': agent.status === 'prayer', 'bg-yellow-500': agent.status === 'break', 'bg-purple-500': agent.status === 'lunch', 'bg-slate-400': agent.status === 'offline' }">
                                </span>
                            </div>
                            
                            <div>
                                <h3 class="font-bold text-slate-800 text-base leading-tight" x-text="agent.name"></h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs font-mono font-semibold text-brand-600 bg-brand-50 px-2 py-0.5 rounded-md" x-text="'Ext: ' + agent.extension"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Teks (Pojok Kanan Atas) -->
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border shadow-sm"
                            :class="{ 
                                'bg-emerald-50 text-emerald-600 border-emerald-200': agent.status === 'online', 
                                'bg-amber-50 text-amber-600 border-amber-200': agent.status === 'prayer', 
                                'bg-yellow-50 text-yellow-600 border-yellow-200': agent.status === 'break', 
                                'bg-purple-50 text-purple-600 border-purple-200': agent.status === 'lunch', 
                                'bg-slate-50 text-slate-500 border-slate-200': agent.status === 'offline' 
                            }" x-text="agent.status">
                        </span>
                    </div>
                    
                    <!-- Kotak Notifikasi Calling (Desain Baru) -->
                    <div class="p-3.5 rounded-xl border flex items-center justify-between transition-all duration-300" 
                         x-show="agent.is_calling" style="display: none;"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         :class="agent.call_status === 'connected' ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200'">
                        
                        <div class="flex items-center gap-3">
                            <!-- Icon Telepon -->
                            <div class="w-9 h-9 rounded-full flex items-center justify-center shadow-sm"
                                 :class="agent.call_status === 'connected' ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-500'">
                                <i class="fa-solid" :class="agent.call_status === 'connected' ? 'fa-phone-volume' : 'fa-phone-flip animate-bounce'"></i>
                            </div>
                            <!-- Detail Tujuan -->
                            <div>
                                <!-- 🚀 BARIS INI DITAMBAHKAN TIMER DURASI -->
                                <div class="flex items-center gap-2 mb-0.5">
                                    <p class="text-[10px] font-bold uppercase tracking-wider"
                                       :class="agent.call_status === 'connected' ? 'text-emerald-700' : 'text-amber-600'" 
                                       x-text="agent.call_status === 'connected' ? 'In Call' : 'Calling...'"></p>
                                    
                                    <!-- Indikator Durasi 00:00 -->
                                    <span x-show="agent.call_status === 'connected'" 
                                          class="text-[9px] font-mono font-bold bg-white/60 px-1.5 py-0.5 rounded shadow-sm"
                                          :class="agent.call_status === 'connected' ? 'text-emerald-700' : 'text-amber-700'"
                                          x-text="formatDuration(agent.call_duration)">
                                    </span>
                                </div>
                                <p class="text-sm font-bold font-mono text-slate-800 tracking-tight" x-text="agent.current_destination ?? 'No Destination'"></p>
                            </div>
                        </div>

                        <!-- Live Indicator Pulse -->
                        <div class="flex h-2 w-2 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                                  :class="agent.call_status === 'connected' ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2"
                                  :class="agent.call_status === 'connected' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                        </div>
                    </div>
                </div>

                <!-- Bagian Bawah: Action Buttons -->
                <div class="p-3 bg-slate-50 border-t border-slate-100 relative z-10 grid grid-cols-4 gap-1.5"
                     :class="agent.is_calling ? (agent.call_status === 'connected' ? 'bg-emerald-50/50 border-emerald-100' : 'bg-amber-50/50 border-amber-100') : 'bg-slate-50 border-slate-100'">
                    
                    <button @click="triggerSpy(agent.extension, '')" class="flex flex-col items-center justify-center p-2 rounded-lg text-[10px] font-bold transition-all bg-white text-slate-500 hover:bg-blue-50 hover:text-blue-600 border border-slate-200 hover:border-blue-300 shadow-sm active:scale-95" title="Dengarkan percakapan">
                        <i class="fa-solid fa-headphones text-sm mb-1"></i> Listen
                    </button>
                    
                    <button @click="triggerSpy(agent.extension, 'w')" class="flex flex-col items-center justify-center p-2 rounded-lg text-[10px] font-bold transition-all bg-white text-slate-500 hover:bg-purple-50 hover:text-purple-600 border border-slate-200 hover:border-purple-300 shadow-sm active:scale-95" title="Berbisik ke agen">
                        <i class="fa-solid fa-microphone-lines text-sm mb-1"></i> Whisper
                    </button>
                    
                    <button @click="triggerSpy(agent.extension, 'B')" class="flex flex-col items-center justify-center p-2 rounded-lg text-[10px] font-bold transition-all bg-white text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 hover:border-indigo-300 shadow-sm active:scale-95" title="Gabung panggilan">
                        <i class="fa-solid fa-arrows-down-to-people text-sm mb-1"></i> Merge
                    </button>
                    
                    <button @click="takeoverCall(agent.extension)" class="flex flex-col items-center justify-center p-2 rounded-lg text-[10px] font-bold transition-all bg-orange-50 text-orange-600 hover:bg-orange-500 hover:text-white border border-orange-200 hover:border-orange-500 shadow-sm active:scale-95" title="Ambil alih panggilan">
                        <i class="fa-solid fa-people-arrows text-sm mb-1"></i> Takeover
                    </button>

                </div>
            </div>
        </template>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Cegah Turbo melakukan cache pada halaman realtime ini
    if (!document.querySelector('meta[name="turbo-cache-control"]')) {
        let meta = document.createElement('meta');
        meta.name = 'turbo-cache-control';
        meta.content = 'no-cache';
        document.head.appendChild(meta);
    }

    // 🚀 DAFTARKAN KE GLOBAL WINDOW AGAR LANGSUNG DIKENALI OLEH TURBO & ALPINE
    window.supervisorDashboard = function() {
        return {
            agents: [],
            stats: { online: 0, break: 0, offline: 0, incall: 0 },
            timerInterval: null,

            init() {
                setTimeout(() => {
                    this.fetchAgents();
                }, 300);

                // Memulai loop timer durasi setiap 1 detik
                this.timerInterval = setInterval(() => {
                    this.agents.forEach(a => {
                        if (a.call_status === 'connected') {
                            a.call_duration = (a.call_duration || 0) + 1;
                        }
                    });
                }, 1000);

                if (window.Echo) {
                    window.Echo.channel('supervisor.dashboard')
                        .listen('.agent.status.updated', (e) => {
                            if (!e.agent) return;
                            let index = this.agents.findIndex(a => a.id === e.agent.id);
                            if (index !== -1) {
                                this.agents[index].status = e.agent.status;
                                this.sortAgents(); 
                                this.updateStats();
                            }
                        })
                        .listen('.agent.call.activity', (e) => {
                            if (!e.agent) return;
                            let index = this.agents.findIndex(a => String(a.extension) === String(e.agent.extension));

                            if (index !== -1) {
                                if (e.status === 'ended') {
                                    this.agents[index].is_calling = false;
                                    this.agents[index].call_status = null;
                                    this.agents[index].current_destination = null;
                                    this.agents[index].call_duration = 0;
                                } else {
                                    this.agents[index].is_calling = true;
                                    
                                    if (this.agents[index].call_status !== 'connected' && e.status === 'connected') {
                                        this.agents[index].call_duration = 0;
                                    }
                                    
                                    this.agents[index].call_status = e.status;
                                    this.agents[index].current_destination = e.destination;
                                }
                                this.sortAgents(); 
                                this.updateStats(); 
                            }
                        });
                }

                document.addEventListener('turbo:before-visit', () => {
                    let meta = document.querySelector('meta[name="turbo-cache-control"]');
                    if (meta) meta.remove();

                    if (window.Echo?.leaveChannel) {
                        window.Echo.leaveChannel('supervisor.dashboard');
                    }
                    
                    if (this.timerInterval) clearInterval(this.timerInterval);
                }, { once: true });
            },

            // Helper Format Durasi (Detik -> 00:00)
            formatDuration(seconds) {
                if (!seconds) return '00:00';
                let m = Math.floor(seconds / 60).toString().padStart(2, '0');
                let s = (seconds % 60).toString().padStart(2, '0');
                return `${m}:${s}`;
            },

            // Fungsi Mengurutkan Agen (Live Call -> Online -> Offline)
            sortAgents() {
                this.agents.sort((a, b) => {
                    if (a.is_calling && !b.is_calling) return -1;
                    if (!a.is_calling && b.is_calling) return 1;

                    const weight = { online: 1, prayer: 2, break: 2, lunch: 2, offline: 3 };
                    let wA = weight[a.status] || 99;
                    let wB = weight[b.status] || 99;
                    
                    if (wA !== wB) return wA - wB;

                    return (a.name || '').localeCompare(b.name || '');
                });
            },

            fetchAgents() {
                fetch('/dashboard/api/live-agents', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                    .then(res => res.json())
                    .then(data => {
                        let agentList = Array.isArray(data) ? data : (data.agents || []);

                        this.agents = agentList.map(agent => ({
                            ...agent,
                            is_calling: agent.is_calling ?? false,
                            call_status: agent.call_status ?? null,
                            current_destination: agent.current_destination ?? null,
                            call_duration: 0 
                        }));

                        this.sortAgents();
                        this.updateStats();
                    })
                    .catch(err => console.error("Gagal mengambil data agen:", err));
            },
            
            takeoverCall(agentExt, spyExt = null) {
                if(!confirm(`Yakin ingin MENGAMBIL ALIH (Takeover) panggilan pada ekstensi ${agentExt}? Agen akan otomatis terputus.`)) return;

                let payload = { target_channel: 'PJSIP/' + agentExt };
                if (spyExt) payload.spy_ext = spyExt;

                fetch('/dashboard/monitoring/takeover', { 
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload)
                })
                .then(res => res.json().then(data => ({ status: res.status, body: data })))
                .then(resData => {
                    if (resData.status === 400) {
                        let adminExt = prompt(resData.body.message + "\n\nMasukkan nomor ekstensi softphone Anda (Cth: 199):");
                        if (adminExt) this.takeoverCall(agentExt, adminExt); 
                    } else if (resData.body.status === 'success') {
                        alert(resData.body.message);
                    } else {
                        alert('Gagal: ' + (resData.body.message || 'Terjadi kesalahan sistem.'));
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('Terjadi kesalahan jaringan.');
                });
            },

            updateStats() {
                this.stats.online = this.agents.filter(a => a.status === 'online').length;
                this.stats.break = this.agents.filter(a => ['prayer', 'break', 'lunch'].includes(a.status)).length;
                this.stats.offline = this.agents.filter(a => a.status === 'offline').length;
                this.stats.incall = this.agents.filter(a => a.is_calling).length; 
            },

            triggerSpy(agentExt, mode, spyExt = null) {
                let payload = { target_channel: 'PJSIP/' + agentExt, mode: mode };
                if (spyExt) payload.spy_ext = spyExt;

                fetch('/dashboard/api/spy', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload)
                })
                    .then(res => res.json().then(data => ({ status: res.status, body: data })))
                    .then(resData => {
                        if (resData.status === 400) {
                            let adminExt = prompt(resData.body.message + "\n\nMasukkan nomor ekstensi softphone yang sedang Anda gunakan (Cth: 199):");
                            if (adminExt) this.triggerSpy(agentExt, mode, adminExt);
                        } else if (resData.body.status === 'success') {
                            alert(resData.body.message);
                        } else {
                            alert('Gagal: ' + (resData.body.message || 'Terjadi kesalahan sistem.'));
                        }
                    })
                    .catch(err => console.error("Gagal mengeksekusi spy:", err));
            }
        }
    }
</script>
@endsection