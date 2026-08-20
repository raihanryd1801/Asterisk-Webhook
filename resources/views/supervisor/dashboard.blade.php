@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="supervisorDashboard()">
    
    <!-- Header Title & Deskripsi -->
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Live Agents</h1>
        <p class="text-xs text-slate-500 mt-1">Siapa yang sedang bekerja, dan panggilan apa yang sedang aktif saat ini.</p>
    </div>

    <!-- Kotak Statistik Ringkas -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Online Agents</p>
            <p class="text-3xl font-black text-green-600 mt-2" x-text="stats.online">0</p>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">On Break</p>
            <p class="text-3xl font-black text-yellow-600 mt-2" x-text="stats.break">0</p>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Offline</p>
            <p class="text-3xl font-black text-red-600 mt-2" x-text="stats.offline">0</p>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Agents</p>
            <p class="text-3xl font-black text-slate-800 mt-2" x-text="agents.length">0</p>
        </div>
    </div>

    <!-- GRID CARD AGEN -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <template x-for="agent in agents" :key="agent.id">
            <!-- 🚀 KARTU AGEN: Warnanya berubah dinamis mengikuti agent.call_status -->
            <div class="rounded-xl shadow-sm border p-5 flex flex-col justify-between transition duration-300 hover:shadow-md"
                 :class="{
                     'bg-white border-slate-200': !agent.is_calling,
                     'border-amber-400 ring-2 ring-amber-100 bg-amber-50/30': agent.call_status === 'ringing',
                     'border-emerald-400 ring-2 ring-emerald-100 bg-emerald-50/30': agent.call_status === 'connected'
                 }">
                <div>
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm" x-text="agent.name"></h3>
                            <p class="text-xs font-mono font-bold text-indigo-600" x-text="'Ext: ' + agent.extension"></p>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
      :class="{ 
          'bg-green-100 text-green-700': agent.status === 'online', 
          'bg-amber-100 text-amber-700': agent.status === 'prayer', 
          'bg-yellow-100 text-yellow-700': agent.status === 'break', 
          'bg-purple-100 text-purple-700': agent.status === 'lunch', 
          'bg-red-100 text-red-700': agent.status === 'offline' 
      }"
      x-text="agent.status"></span>
                    </div>
                    
                    <!-- Kotak Notifikasi Calling: Teks dan Warna berubah mengikuti Ringing / Connected -->
                    <div class="mt-4 p-3 rounded-lg border shadow-sm transition-colors" 
                         x-show="agent.is_calling" style="display: none;"
                         :class="agent.call_status === 'connected' ? 'bg-emerald-50 border-emerald-200' : 'bg-white border-amber-200'">
                        
                        <div class="flex justify-between items-center text-[10px] mb-1">
                            <span class="font-bold uppercase tracking-wider"
                                  :class="agent.call_status === 'connected' ? 'text-emerald-700' : 'text-amber-600'"
                                  x-text="agent.call_status === 'connected' ? 'Sedang Bicara' : 'Calling...'"></span>
                            <span class="font-semibold animate-pulse"
                                  :class="agent.call_status === 'connected' ? 'text-emerald-600' : 'text-red-500'">● live</span>
                        </div>
                        <p class="text-xs font-bold font-mono text-slate-700" x-text="agent.current_destination ?? 'No Destination'"></p>
                    </div>
                </div>

                <div class="mt-6 pt-3 border-t grid grid-cols-3 gap-2"
                     :class="agent.is_calling ? (agent.call_status === 'connected' ? 'border-emerald-200' : 'border-amber-200') : 'border-slate-100'">
                    <button @click="triggerSpy(agent.extension, '')" class="bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 py-1.5 px-2 rounded-lg text-xs font-semibold flex flex-col items-center gap-1 transition shadow-sm">Listen Agent</button>
                    <button @click="triggerSpy(agent.extension, 'w')" class="bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 py-1.5 px-2 rounded-lg text-xs font-semibold flex flex-col items-center gap-1 transition shadow-sm">Whisper to Agent</button>
                    <button @click="triggerSpy(agent.extension, 'B')" class="bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 py-1.5 px-2 rounded-lg text-xs font-semibold flex flex-col items-center gap-1 transition shadow-sm">Merge Call</button>
                </div>
            </div>
        </template>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

<script>
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: '{{ env("REVERB_APP_KEY") }}',
        wsHost: window.location.hostname,
        wsPort: 8080,
        wssPort: 8080,
        forceTLS: false,
        enabledTransports: ['ws', 'wss'],
    });

    function supervisorDashboard() {
        return {
            agents: [],
            stats: { online: 0, break: 0, offline: 0 },
            
            init() {
                // Beri jeda 300ms agar session stabil setelah login redirect
                setTimeout(() => {
                    this.fetchAgents();
                }, 300);

                // Gunakan SATU channel untuk semua event real-time Reverb
                window.Echo.channel('supervisor.dashboard')
                    
                    // 1. Listener Status (Online/Break/Offline)
                    .listen('.agent.status.updated', (e) => {
                        console.log("🔥 STATUS UPDATE MASUK:", e);
                        let index = this.agents.findIndex(a => a.id === e.agent.id);
                        if(index !== -1) {
                            this.agents[index].status = e.agent.status;
                            this.updateStats();
                        }
                    })

                    // 2. Listener Call Activity (Ringing / Connected / Ended)
                    .listen('.agent.call.activity', (e) => {
                        console.log("🔥 EVENT CALL ACTIVITY MASUK:", e);
                        let index = this.agents.findIndex(a => a.extension == e.agent.extension);
                        
                        if(index !== -1) {
                            if (e.status === 'ended') {
                                this.agents[index] = {
                                    ...this.agents[index],
                                    is_calling: false,
                                    call_status: null,
                                    current_destination: null
                                };
                            } else {
                                this.agents[index] = {
                                    ...this.agents[index],
                                    is_calling: true,
                                    call_status: e.status, // 'ringing' atau 'connected'
                                    current_destination: e.destination
                                };
                            }
                        }
                    });
            },

            fetchAgents() {
                // Disesuaikan dengan rute bersih kita: /supervisor/agents (tanpa /api/)
                fetch('/supervisor/agents', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(res => res.json())
                .then(data => {
                    // Mengantisipasi apakah response berupa array langsung atau object wrapper
                    let agentList = Array.isArray(data) ? data : (data.agents || []);
                    
                    this.agents = agentList.map(agent => ({
                        ...agent,
                        is_calling: false,
                        call_status: null,
                        current_destination: null
                    }));
                    this.updateStats();
                })
                .catch(err => console.error("Gagal mengambil data agen:", err));
            },

            updateStats() {
                this.stats.online = this.agents.filter(a => a.status === 'online').length;
                
                // Gabungkan Prayer, Break, dan Lunch ke kategori 'Break / Non-Ready'
                this.stats.break = this.agents.filter(a => ['prayer', 'break', 'lunch'].includes(a.status)).length;
                
                this.stats.offline = this.agents.filter(a => a.status === 'offline').length;
            },

            triggerSpy(agentExt, mode) {
                fetch('/supervisor/spy', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json', 
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') 
                    },
                    body: JSON.stringify({ supervisor_ext: '201', target_channel: `PJSIP/${agentExt}`, mode: mode })
                })
                .then(res => res.json())
                .then(data => alert(data.message))
                .catch(err => console.error("Gagal mengeksekusi spy:", err));
            }
        }
    }
</script>
@endsection