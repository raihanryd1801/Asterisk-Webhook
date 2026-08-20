<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Workspace - Ext: {{ $extension }}</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Laravel Vite (Echo / Reverb) -->
    @vite(['resources/js/app.js'])
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-slate-100 min-h-screen flex flex-col" x-data="agentWorkspace(@js($extension))">

    <!-- Header -->
    <header class="bg-white shadow p-4 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">
                Agent Workspace 
                <span class="text-blue-600">Ext: {{ $extension }}</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">Mode Click-to-Call (PABX Integration)</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <span class="text-sm text-slate-500">Status Agent:</span>
                <span class="px-3 py-1 rounded text-white text-xs font-semibold uppercase shadow-sm transition-colors"
                      :class="{'bg-green-500': status==='online', 'bg-yellow-500': status==='break', 'bg-red-500': status==='offline'}"
                      x-text="status"></span>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="p-6 max-w-5xl mx-auto w-full grid grid-cols-1 md:grid-cols-2 gap-6 flex-1">
        
        <!-- Panel Status Kehadiran -->
        <section class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
            <h2 class="font-semibold text-lg mb-4 text-slate-700">Ubah Status Kehadiran</h2>
            <div class="flex flex-wrap gap-2">
                <button @click="changeStatus('online')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition">Online</button>
                <button @click="changeStatus('break')" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded transition">Break</button>
                <button @click="changeStatus('offline')" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded transition">Offline</button>
            </div>
        </section>

        <!-- Panel Click to Call -->
        <section class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
            <h2 class="font-semibold text-lg mb-4 text-slate-700">Panggilan Cepat (Click to Call)</h2>
            
            <div class="space-y-4">
                <!-- Input Nomor Tujuan -->
                <input type="text" 
                       x-model="phoneNumber" 
                       @keydown.enter="makeCall()" 
                       placeholder="Nomor tujuan..." 
                       class="w-full border border-slate-300 p-3 text-center text-2xl rounded-lg font-bold outline-none focus:ring-2 focus:ring-blue-500 text-slate-700 tracking-wider">
                
                <!-- Keypad -->
                <div class="grid grid-cols-3 gap-2">
                    <template x-for="n in [1,2,3,4,5,6,7,8,9,'*',0,'#']" :key="n">
                        <button @click="appendDigit(n)" 
                                x-text="n" 
                                class="bg-slate-50 border border-slate-200 hover:bg-slate-100 active:bg-slate-200 p-3 rounded-lg font-bold text-lg text-slate-600 transition"></button>
                    </template>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-2 pt-2">
                    <button @click="makeCall()" 
                            :disabled="isCalling" 
                            class="flex-1 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white p-3 rounded-lg font-semibold flex items-center justify-center gap-2 transition shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        <span x-text="isCalling ? 'Menyambungkan...' : 'Hubungi Sekarang'"></span>
                    </button>
                    <button @click="phoneNumber = ''" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 rounded-lg font-semibold transition">
                        Clear
                    </button>
                </div>
                
                <!-- Feedback Status Message -->
                <div x-show="callMessage" x-transition class="text-sm text-center font-semibold p-3 rounded-lg" :class="callError ? 'bg-red-50 text-red-600 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'" x-text="callMessage"></div>
            </div>
        </section>
    </main>

    <!-- Screen Pop Modal (Panggilan Masuk via Reverb) -->
    <div x-show="showPopup" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" style="display: none;">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md border-t-8 border-blue-500" @click.outside="closePopup()">
            <div class="flex items-center gap-3 mb-4">
                <div class="bg-blue-100 p-3 rounded-full text-blue-600 animate-pulse">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Panggilan Masuk!</h3>
                    <p class="text-xs text-slate-500">Screen Pop Notification</p>
                </div>
            </div>
            <div class="bg-slate-50 p-4 rounded-lg space-y-2 mb-4 text-sm border border-slate-100">
                <div class="flex justify-between"><span class="text-slate-500">Nomor Penelepon:</span><span class="font-bold text-blue-600" x-text="callInfo.caller || '-'"></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Tujuan Ekstensi:</span><span class="font-semibold text-slate-700" x-text="callInfo.extension || extension"></span></div>
            </div>
            <button @click="closePopup()" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-3 rounded-lg w-full font-semibold transition shadow-md">Tutup / Catat</button>
        </div>
    </div>

    <!-- Alpine.js Logic -->
    <script>
        function agentWorkspace(extension) {
            return {
                extension,
                status: 'offline',
                phoneNumber: '',
                isCalling: false,
                callMessage: '',
                callError: false,
                showPopup: false,
                callInfo: { caller: '', extension: '' },

                init() {
                    this.loadAgentStatus();
                    this.initEcho();
                },

                loadAgentStatus() {
                    fetch(`/api/agent/${encodeURIComponent(this.extension)}`)
                        .then(r => r.json())
                        .then(d => { if(d.status === 'success') this.status = d.data.status ?? 'offline'; })
                        .catch(e => console.error('Gagal memuat status agen', e));
                },

                initEcho() {
                    if (!window.Echo) return;
                    window.Echo.private(`agent.${this.extension}`).listen('.incoming.call', event => {
                        this.callInfo = event.callData ?? { caller: 'Unknown', extension: this.extension };
                        this.showPopup = true;
                        
                        // Bunyikan file audio saat ada screen pop
                        new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3').play().catch(() => {});
                    });
                },

                changeStatus(newStatus) {
                    fetch(`/api/agent/${encodeURIComponent(this.extension)}/status`, {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json', 
                            'X-CSRF-TOKEN': @js(csrf_token()),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ status: newStatus })
                    })
                    .then(r => r.json())
                    .then(d => { 
                        if(d.status === 'success') this.status = newStatus; 
                    })
                    .catch(e => console.error('Gagal mengubah status', e));
                },

                appendDigit(digit) { 
                    this.phoneNumber += String(digit); 
                },

                makeCall() {
                    const number = this.phoneNumber.replace(/[^0-9*#]/g, '');
                    if (!number) {
                        alert('Silakan masukkan nomor tujuan.');
                        return;
                    }

                    this.isCalling = true;
                    this.callError = false;
                    this.callMessage = 'Mengirim instruksi ke server PABX...';

                    // Menembak endpoint /call yang mengeksekusi AgentWorkspaceController@call
                    fetch(`/api/agent/${encodeURIComponent(this.extension)}/call`, {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json', 
                            'X-CSRF-TOKEN': @js(csrf_token()),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ destination: number })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if(data.status === 'success') {
                            this.callError = false;
                            this.callMessage = 'Berhasil! Telepon Ext: ' + this.extension + ' akan berdering. Silakan angkat.';
                            setTimeout(() => this.phoneNumber = '', 3000);
                        } else {
                            this.callError = true;
                            this.callMessage = 'Gagal: ' + (data.message || 'Ditolak oleh Server');
                        }
                    })
                    .catch(err => {
                        this.callError = true;
                        this.callMessage = 'Gagal menghubungi server aplikasi.';
                        console.error('Click to call error:', err);
                    })
                    .finally(() => {
                        this.isCalling = false;
                    });
                },

                closePopup() { 
                    this.showPopup = false; 
                }
            };
        }
    </script>
</body>
</html>