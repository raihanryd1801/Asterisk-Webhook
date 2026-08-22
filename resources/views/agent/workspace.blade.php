@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="agentWorkspaceData('{{ $extension }}')">
    
    <!-- Header Workspace -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-6 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-headset text-brand-600"></i> Agent Workspace 
                <span class="text-brand-600 font-mono text-xs bg-white px-3 py-1 rounded-full border border-brand-200 shadow-sm">Ext: {{ $extension }}</span>
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Control Panel & Click-to-Call (MicroSIP Ready)</p>
        </div>
        <div>
            <span class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-full uppercase tracking-wider border shadow-sm bg-white"
                  :class="{
                      'text-emerald-600 border-emerald-200': currentStatus === 'online',
                      'text-amber-600 border-amber-200': currentStatus === 'prayer' || currentStatus === 'break' || currentStatus === 'lunch',
                      'text-slate-500 border-slate-200': currentStatus === 'offline'
                  }">
                <span class="w-2 h-2 rounded-full inline-block"
                      :class="{
                          'bg-emerald-500 animate-pulse': currentStatus === 'online',
                          'bg-amber-500': currentStatus === 'prayer' || currentStatus === 'break' || currentStatus === 'lunch',
                          'bg-slate-400': currentStatus === 'offline'
                      }"></span>
                <span x-text="'Status: ' + currentStatus"></span>
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Kolom 1: Ubah Status Kehadiran -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
            <div>
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Ubah Status Kehadiran</h2>
                <p class="text-xs text-slate-500 mb-5">Pilih status ketersediaan Anda saat ini untuk mengaktifkan atau mengunci panel panggilan.</p>
                
                <div class="grid grid-cols-1 gap-2.5">
                    <button @click="updateStatus('online')" 
                            class="p-3.5 rounded-xl font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition flex items-center justify-between px-5 shadow-sm active:scale-[0.98]">
                        <span class="flex items-center gap-2.5"><i class="fa-solid fa-circle-check text-xs"></i> Online (Ready)</span>
                        <i class="fa-solid fa-chevron-right text-[10px] opacity-60"></i>
                    </button>
                    <button @click="updateStatus('prayer')" 
                            class="p-3.5 rounded-xl font-semibold text-white bg-amber-600 hover:bg-amber-700 transition flex items-center justify-between px-5 shadow-sm active:scale-[0.98]">
                        <span class="flex items-center gap-2.5"><i class="fa-solid fa-person-praying text-xs"></i> Prayer</span>
                        <i class="fa-solid fa-chevron-right text-[10px] opacity-60"></i>
                    </button>
                    <button @click="updateStatus('break')" 
                            class="p-3.5 rounded-xl font-semibold text-white bg-yellow-500 hover:bg-yellow-600 transition flex items-center justify-between px-5 shadow-sm active:scale-[0.98]">
                        <span class="flex items-center gap-2.5"><i class="fa-solid fa-mug-hot text-xs"></i> Break</span>
                        <i class="fa-solid fa-chevron-right text-[10px] opacity-60"></i>
                    </button>
                    <button @click="updateStatus('lunch')" 
                            class="p-3.5 rounded-xl font-semibold text-white bg-purple-600 hover:bg-purple-700 transition flex items-center justify-between px-5 shadow-sm active:scale-[0.98]">
                        <span class="flex items-center gap-2.5"><i class="fa-solid fa-utensils text-xs"></i> Lunch</span>
                        <i class="fa-solid fa-chevron-right text-[10px] opacity-60"></i>
                    </button>
                    <button @click="updateStatus('offline')" 
                            class="p-3.5 rounded-xl font-semibold text-white bg-slate-700 hover:bg-slate-800 transition flex items-center justify-between px-5 shadow-sm active:scale-[0.98]">
                        <span class="flex items-center gap-2.5"><i class="fa-solid fa-power-off text-xs"></i> Offline</span>
                        <i class="fa-solid fa-chevron-right text-[10px] opacity-60"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Kolom 2: Click to Call (Dilindungi Overlay Kunci jika Tidak Online) -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4 relative overflow-hidden flex flex-col justify-between">
            <div>
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Click to Call</h2>
                <p class="text-xs text-slate-500 mb-4">Dial outbound via SIP softphone MicroSIP.</p>
            </div>

            <!-- 🔒 OVERLAY PERINGATAN JIKA STATUS BUKAN ONLINE -->
            <template x-if="currentStatus !== 'online'">
                <div class="absolute inset-0 bg-white/95 backdrop-blur-[3px] z-20 flex flex-col items-center justify-center p-6 text-center rounded-2xl">
                    <div class="w-14 h-14 rounded-full bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center mb-3 shadow-sm">
                        <i class="fa-solid fa-lock text-xl"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-800">Fitur Panggilan Dikunci</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-xs leading-relaxed">
                        Anda sedang dalam status <strong class="uppercase text-slate-700" x-text="currentStatus"></strong>. Harap ubah status ke <strong class="text-emerald-600 font-semibold">Online (Ready)</strong> di samping untuk mulai menelepon.
                    </p>
                </div>
            </template>

            <div class="space-y-4 max-w-xs mx-auto w-full">
                <!-- Input Nomor Tujuan -->
                <div class="relative">
                    <input type="text" x-model="targetNumber" placeholder="Nomor tujuan..." class="w-full border border-slate-200 rounded-xl p-3.5 text-center text-xl outline-none font-mono bg-slate-50 focus:ring-2 focus:ring-brand-500 pr-10 tracking-wider">
                    <button @click="targetNumber = targetNumber.slice(0, -1)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-delete-left"></i>
                    </button>
                </div>

                <!-- Keypad Angka Proporsional -->
                <div class="grid grid-cols-3 gap-2.5">
                    <template x-for="num in ['1','2','3','4','5','6','7','8','9','*','0','#']">
                        <button @click="targetNumber += num" class="w-14 h-14 mx-auto rounded-full border border-slate-200 text-slate-700 font-medium hover:bg-slate-50 active:scale-95 transition-all num text-base flex items-center justify-center shadow-sm" x-text="num"></button>
                    </template>
                </div>

                <!-- Tombol Eksekusi Panggil -->
                <div class="flex gap-2 pt-2">
                    <button @click="makeCall()" :disabled="currentStatus !== 'online'" class="flex-1 bg-brand-600 hover:bg-brand-700 disabled:bg-slate-200 disabled:text-slate-400 text-white font-medium text-sm py-2.5 px-4 rounded-xl transition shadow-sm flex items-center justify-center gap-2">
                        <i class="fa-solid fa-phone text-xs"></i> Panggil
                    </button>
                    <button @click="targetNumber = ''" class="px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-sm rounded-xl transition">
                        Clear
                    </button>
                </div>
            </div>

            <!-- Kotak Info Status -->
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 text-xs text-slate-500 flex items-center gap-2 mt-4">
                <i class="fa-solid fa-circle-info text-brand-600 shrink-0"></i>
                <span x-text="infoMessage"></span>
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
    function agentWorkspaceData(extension) {
        return {
            extension: extension,
            currentStatus: 'offline', 
            targetNumber: '',
            infoMessage: 'MikroSIP siap digunakan...',

            init() {
                this.fetchAgentStatus();
                setInterval(() => {
                    this.fetchAgentStatus();
                }, 5000);
            },

            fetchAgentStatus() {
                fetch('/supervisor/agents', { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    if (data.agents) {
                        let currentAgent = data.agents.find(a => a.extension == this.extension);
                        if (currentAgent) {
                            this.currentStatus = currentAgent.status;
                        }
                    }
                }).catch(err => console.error("Gagal sinkronisasi status"));
            },

            updateStatus(newStatus) {
                fetch(`/supervisor/agent/${this.extension}/status`, {
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
                
                fetch(`/supervisor/agent/click-to-call`, {
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
                })
                .catch(err => {
                    this.infoMessage = 'Gagal terhubung ke server backend.';
                });
            }
        }
    }
</script>
@endsection