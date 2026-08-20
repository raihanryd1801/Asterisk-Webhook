@extends('layouts.app')

@section('title', 'Agent Workspace - Ext: ' . $extension)

@section('content')
<div x-data="agentWorkspace(@js($extension))" class="space-y-6 relative max-w-7xl mx-auto">

    <!-- Screen Pop Modal (Panggilan Masuk) -->
    <div x-show="showPopup" x-transition.opacity class="absolute inset-0 bg-slate-900/40 z-50 rounded-2xl flex items-center justify-center backdrop-blur-sm" style="display: none;" @click.outside="closePopup()">
        <div class="bg-white rounded-2xl shadow-2xl p-8 flex flex-col items-center text-center max-w-sm w-full border-t-4 border-emerald-500">
            <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl mb-4 relative">
                <i class="fa-solid fa-phone-volume"></i>
                <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span>
            </div>
            <h3 class="text-lg font-bold text-slate-800">📞 Panggilan Masuk!</h3>
            <div class="bg-slate-50 p-4 rounded-xl space-y-2 my-4 text-sm border border-slate-200 w-full">
                <div class="flex justify-between"><span class="text-slate-500">Dari:</span><span class="font-bold text-brand-600 font-mono" x-text="callInfo.caller || '-'"></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Ke Ext:</span><span class="font-semibold text-slate-700 font-mono" x-text="callInfo.extension || extension"></span></div>
            </div>
            <button @click="closePopup()" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-medium py-2.5 rounded-xl transition-colors">Tutup / Catat</button>
        </div>
    </div>

    <!-- Header Workspace -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-5 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-headset text-brand-600"></i> Agent Workspace <span class="text-brand-600 font-mono font-bold">Ext: {{ $extension }}</span>
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Control Panel & Click-to-Call (MicroSIP Ready)</p>
        </div>
        <div class="flex items-center gap-4">
            <span class="inline-flex items-center gap-2 text-xs font-medium bg-white border border-slate-200 rounded-full px-4 py-2 shadow-sm">
                <span class="text-slate-400">Status:</span>
                <span class="px-2.5 py-0.5 rounded-full text-white text-[10px] font-bold uppercase tracking-wider shadow-sm transition-colors"
                      :class="{
                          'bg-emerald-500': status==='online',
                          'bg-amber-600': status==='prayer',
                          'bg-yellow-500': status==='break',
                          'bg-purple-600': status==='lunch',
                          'bg-slate-500': status==='offline'
                      }"
                      x-text="status"></span>
            </span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Panel Status Kehadiran -->
        <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
            <div>
                <h2 class="font-semibold text-base mb-1 text-slate-800">Ubah Status Kehadiran</h2>
                <p class="text-xs text-slate-400 mb-5">Pilih status ketersediaan Anda saat ini</p>
                <div class="grid grid-cols-2 gap-3">
                    <button @click="changeStatus('online')" class="bg-emerald-600 hover:bg-emerald-700 text-white py-3 px-4 rounded-xl font-semibold text-sm transition shadow-sm flex items-center justify-center gap-2"><i class="fa-solid fa-check text-xs"></i> Online (Ready)</button>
                    <button @click="changeStatus('prayer')" class="bg-amber-600 hover:bg-amber-700 text-white py-3 px-4 rounded-xl font-semibold text-sm transition shadow-sm flex items-center justify-center gap-2"><i class="fa-solid fa-person-praying text-xs"></i> Prayer</button>
                    <button @click="changeStatus('break')" class="bg-yellow-500 hover:bg-yellow-600 text-white py-3 px-4 rounded-xl font-semibold text-sm transition shadow-sm flex items-center justify-center gap-2"><i class="fa-solid fa-mug-hot text-xs"></i> Break</button>
                    <button @click="changeStatus('lunch')" class="bg-purple-600 hover:bg-purple-700 text-white py-3 px-4 rounded-xl font-semibold text-sm transition shadow-sm flex items-center justify-center gap-2"><i class="fa-solid fa-utensils text-xs"></i> Lunch</button>
                    <button @click="changeStatus('offline')" class="bg-slate-600 hover:bg-slate-700 text-white py-3 px-4 rounded-xl font-semibold text-sm transition shadow-sm col-span-2 flex items-center justify-center gap-2"><i class="fa-solid fa-power-off text-xs"></i> Offline</button>
                </div>
            </div>
        </section>

        <!-- Panel Click to Call (Dialpad) -->
        <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
            <div>
                <div class="flex justify-between mb-1">
                    <h2 class="font-semibold text-base text-slate-800">Click to Call</h2>
                </div>
                <p class="text-xs text-slate-400 mb-5">Dial outbound via SIP softphone</p>
                
                <div class="space-y-4 max-w-xs mx-auto">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-center relative group">
                        <input type="text" x-model="phoneNumber" @keydown.enter="makeCall()" placeholder="Nomor tujuan..." class="w-full bg-transparent text-xl font-semibold text-slate-800 num tracking-wide text-center outline-none">
                        <button @click="phoneNumber = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"><i class="fa-solid fa-circle-xmark"></i></button>
                    </div>
                    
                    <!-- Perbaikan Ukuran Tombol Dialpad agar Compact & Proporsional -->
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="n in [1,2,3,4,5,6,7,8,9,'*',0,'#']" :key="n">
                            <button @click="appendDigit(n)" x-text="n" class="w-14 h-14 mx-auto rounded-full border border-slate-200 text-slate-700 font-medium hover:bg-slate-50 active:scale-95 transition-all num text-base flex items-center justify-center shadow-sm"></button>
                        </template>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button @click="makeCall()" class="flex-1 bg-brand-600 hover:bg-brand-700 text-white py-2.5 px-4 rounded-xl font-medium transition shadow-sm flex items-center justify-center gap-2 text-sm">
                            <i class="fa-solid fa-phone"></i> Panggil via MicroSIP
                        </button>
                        <button @click="phoneNumber = ''" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 rounded-xl font-medium transition text-sm">Clear</button>
                    </div>

                    <div class="text-xs bg-slate-50 rounded-xl p-3 border border-slate-200">
                        <div class="flex justify-between"><span class="text-slate-400">Info:</span><span class="font-semibold text-slate-700" x-text="logMessage">Ready</span></div>
                    </div>
                </div>
            </div>
        </section>

    </div>

</div>
@endsection

@section('scripts')
<script>
    function agentWorkspace(extension) {
        return {
            extension, 
            status: 'offline',
            phoneNumber: '',
            logMessage: 'Standby',
            showPopup: false, 
            callInfo: { caller: '', extension: '' },

            init() {
                this.loadAgentStatus();
                this.initEcho();
            },

            loadAgentStatus() {
                fetch(`/api/agent/${encodeURIComponent(this.extension)}`)
                    .then(r => r.json())
                    .then(d => { if(d.status === 'success') this.status = d.data.status ?? 'offline'; });
            },

            initEcho() {
                if (!window.Echo) return;
                window.Echo.private(`agent.${this.extension}`).listen('.incoming.call', event => {
                    this.callInfo = event.callData ?? { caller: '', extension: this.extension };
                    this.showPopup = true;
                    new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3').play().catch(e => console.log('Audio diblokir', e));
                });
            },

            changeStatus(newStatus) {
                fetch(`/api/agent/${encodeURIComponent(this.extension)}/status`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ status: newStatus })
                }).then(r => r.json()).then(d => { if(d.status === 'success') this.status = newStatus; });
            },

            appendDigit(digit) { this.phoneNumber += String(digit); },

            makeCall() {
                const number = this.phoneNumber.replace(/[^0-9*#]/g, '');
                if (!number) return alert('Masukkan nomor tujuan.');

                this.logMessage = 'Memproses panggilan...';

                fetch(`/api/agent/${encodeURIComponent(this.extension)}/call`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ destination: number })
                })
                .then(r => r.json())
                .then(d => {
                    this.logMessage = 'MikroSIP akan berdering...';
                    this.phoneNumber = '';
                })
                .catch(err => {
                    this.logMessage = 'Gagal memanggil';
                    console.error(err);
                });
            },

            closePopup() { this.showPopup = false; }
        };
    }
</script>
@endsection