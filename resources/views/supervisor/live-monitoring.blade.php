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

    <!-- Kotak Statistik Ringkas -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-user-check"></i></div>
            <div>
                <p class="text-2xl font-bold text-slate-800 num" x-text="stats.online">0</p>
                <p class="text-xs text-slate-500 font-medium">Online Agents</p>
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
    <!-- GRID CARD AGEN -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <template x-for="agent in agents" :key="agent.id">
            <div class="bg-white rounded-2xl shadow-sm border p-5 flex flex-col justify-between transition-all duration-300 hover:shadow-md relative overflow-hidden"
                 :class="{
                     'border-slate-200': !agent.is_calling && agent.status !== 'offline',
                     'border-slate-200/60 bg-slate-50/60 opacity-60': agent.status === 'offline',
                     'border-amber-400 ring-2 ring-amber-500/20 bg-amber-50/10': agent.call_status === 'ringing',
                     'border-emerald-400 ring-2 ring-emerald-500/20 bg-emerald-50/10': agent.call_status === 'connected'
                 }">
                  
                <!-- Animated pulse background for connected -->
                <div x-show="agent.call_status === 'connected'" class="absolute inset-0 bg-emerald-400/5 animate-pulse z-0 pointer-events-none"></div>

                <div class="relative z-10">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-sm font-bold shadow-sm border border-slate-200">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-800 text-sm" x-text="agent.name"></h3>
                                <p class="text-xs font-mono font-medium text-slate-500" x-text="'Ext: ' + agent.extension"></p>
                            </div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border"
                            :class="{ 
                                'bg-emerald-50 text-emerald-600 border-emerald-200': agent.status === 'online', 
                                'bg-amber-50 text-amber-600 border-amber-200': agent.status === 'prayer', 
                                'bg-yellow-50 text-yellow-600 border-yellow-200': agent.status === 'break', 
                                'bg-purple-50 text-purple-600 border-purple-200': agent.status === 'lunch', 
                                'bg-slate-50 text-slate-500 border-slate-200': agent.status === 'offline' 
                            }">
                            <span class="w-1.5 h-1.5 rounded-full inline-block mr-1" 
                                  :class="{ 'bg-emerald-500': agent.status === 'online', 'bg-amber-500': agent.status === 'prayer', 'bg-yellow-500': agent.status === 'break', 'bg-purple-500': agent.status === 'lunch', 'bg-slate-400': agent.status === 'offline' }"></span>
                            <span x-text="agent.status"></span>
                        </span>
                    </div>
                    
                    <!-- Kotak Notifikasi Calling -->
                    <div class="mt-4 p-3.5 rounded-xl border shadow-sm transition-all duration-300" 
                         x-show="agent.is_calling" style="display: none;"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         :class="agent.call_status === 'connected' ? 'bg-emerald-50/80 border-emerald-200' : 'bg-white border-amber-200'">
                        
                        <div class="flex justify-between items-center text-[10px] mb-1.5">
                            <span class="font-bold uppercase tracking-wider flex items-center gap-1.5"
                                  :class="agent.call_status === 'connected' ? 'text-emerald-700' : 'text-amber-600'">
                                  <i class="fa-solid" :class="agent.call_status === 'connected' ? 'fa-phone-volume' : 'fa-phone-flip animate-bounce'"></i>
                                  <span x-text="agent.call_status === 'connected' ? 'Sedang Bicara' : 'Calling...'"></span>
                            </span>
                            <span class="font-semibold px-1.5 py-0.5 rounded text-[9px] uppercase tracking-wide"
                                  :class="agent.call_status === 'connected' ? 'bg-emerald-200/50 text-emerald-700 animate-pulse' : 'bg-red-100 text-red-600 animate-pulse'">● live</span>
                        </div>
                        <p class="text-sm font-bold font-mono text-slate-800 tracking-tight" x-text="agent.current_destination ?? 'No Destination'"></p>
                    </div>
                </div>

                <!-- Tombol Intervensi ChanSpy -->
                <div class="mt-5 pt-4 border-t relative z-10 grid grid-cols-3 gap-2"
                     :class="agent.is_calling ? (agent.call_status === 'connected' ? 'border-emerald-200' : 'border-amber-200') : 'border-slate-100'">
                    <button @click="triggerSpy(agent.extension, '')" class="bg-white hover:bg-brand-50 border border-slate-200 hover:border-brand-200 text-slate-600 hover:text-brand-600 py-2 px-1 rounded-xl text-[11px] font-semibold flex flex-col items-center gap-1.5 transition-all shadow-sm active:scale-95" title="Dengarkan percakapan">
                        <i class="fa-solid fa-headphones"></i> Listen
                    </button>
                    <button @click="triggerSpy(agent.extension, 'w')" class="bg-white hover:bg-brand-50 border border-slate-200 hover:border-brand-200 text-slate-600 hover:text-brand-600 py-2 px-1 rounded-xl text-[11px] font-semibold flex flex-col items-center gap-1.5 transition-all shadow-sm active:scale-95" title="Berbisik ke agen">
                        <i class="fa-solid fa-microphone-lines"></i> Whisper
                    </button>
                    <button @click="triggerSpy(agent.extension, 'B')" class="bg-white hover:bg-brand-50 border border-slate-200 hover:border-brand-200 text-slate-600 hover:text-brand-600 py-2 px-1 rounded-xl text-[11px] font-semibold flex flex-col items-center gap-1.5 transition-all shadow-sm active:scale-95" title="Gabung panggilan">
                        <i class="fa-solid fa-arrows-down-to-people"></i> Merge
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function supervisorDashboard() {
        return {
            agents: [],
            stats: { online: 0, break: 0, offline: 0 },
            
            init() {
                setTimeout(() => {
                    this.fetchAgents();
                }, 300);

                // Memakai window.Echo global yang sudah aman di app.blade.php
                if (window.Echo) {
                    window.Echo.channel('supervisor.dashboard')
    .listen('.agent.status.updated', (e) => {
        if (!e.agent) return;
        let index = this.agents.findIndex(a => a.id === e.agent.id);
        if (index !== -1) {
            this.agents[index].status = e.agent.status;
            this.updateStats();
        }
    })
    .listen('.agent.call.activity', (e) => {
        if (!e.agent) return;
        let index = this.agents.findIndex(a => String(a.extension) === String(e.agent.extension));
        
        if (index !== -1) {
            // Gunakan Object.assign agar reaktifitas Alpine.js tidak merusak scope 'agent' di HTML
            if (e.status === 'ended') {
                this.agents[index].is_calling = false;
                this.agents[index].call_status = null;
                this.agents[index].current_destination = null;
            } else {
                this.agents[index].is_calling = true;
                this.agents[index].call_status = e.status;
                this.agents[index].current_destination = e.destination;
            }
        }
    });
                }
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
                        current_destination: agent.current_destination ?? null
                    }));
                    
                    this.updateStats();
                })
                .catch(err => console.error("Gagal mengambil data agen:", err));
            },

            updateStats() {
                this.stats.online = this.agents.filter(a => a.status === 'online').length;
                this.stats.break = this.agents.filter(a => ['prayer', 'break', 'lunch'].includes(a.status)).length;
                this.stats.offline = this.agents.filter(a => a.status === 'offline').length;
            },

            triggerSpy(agentExt, mode, spyExt = null) {
                let payload = { 
                    target_channel: 'PJSIP/' + agentExt, 
                    mode: mode 
                };
                
                if (spyExt) {
                    payload.spy_ext = spyExt;
                }

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
                        
                        if (adminExt) {
                            this.triggerSpy(agentExt, mode, adminExt);
                        }
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
