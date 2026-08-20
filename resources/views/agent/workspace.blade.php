<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Workspace - Ext: {{ $extension }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @vite(['resources/js/app.js'])
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
        </div>
        <div class="flex items-center gap-2">
            <span class="text-sm text-slate-500">Status:</span>
            <span class="px-3 py-1 rounded text-white text-xs font-bold uppercase shadow-sm transition-colors"
                  :class="{
                      'bg-green-500': status==='online',
                      'bg-amber-600': status==='prayer',
                      'bg-yellow-500': status==='break',
                      'bg-purple-600': status==='lunch',
                      'bg-slate-500': status==='offline'
                  }"
                  x-text="status"></span>
        </div>
    </header>

    <!-- Main Content -->
    <main class="p-6 max-w-5xl mx-auto w-full grid grid-cols-1 md:grid-cols-2 gap-6 flex-1">
        
        <!-- Panel Status Kehadiran -->
        <!-- Panel Status Kehadiran -->
<section class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
    <h2 class="font-semibold text-lg mb-4 text-slate-700">Ubah Status Kehadiran</h2>
    <div class="grid grid-cols-2 gap-2">
        <button @click="changeStatus('online')" class="bg-green-600 hover:bg-green-700 text-white py-2 rounded font-bold transition">Online (Ready)</button>
        <button @click="changeStatus('prayer')" class="bg-amber-600 hover:bg-amber-700 text-white py-2 rounded font-bold transition">Prayer</button>
        <button @click="changeStatus('break')" class="bg-yellow-600 hover:bg-yellow-700 text-white py-2 rounded font-bold transition">Break</button>
        <button @click="changeStatus('lunch')" class="bg-purple-600 hover:bg-purple-700 text-white py-2 rounded font-bold transition">Lunch</button>
        
        <!-- Tombol Logout yang benar -->
        <form action="{{ url('/agent/logout') }}" method="POST" class="col-span-2">
            @csrf
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded font-bold transition">
                Logout / Keluar Sesi
            </button>
        </form>
    </div>
</section>

        <!-- Panel Click to Call -->
        <section class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
            <h2 class="font-semibold text-lg mb-4 text-slate-700">Panggilan Cepat</h2>
            <div class="space-y-4">
                <input type="text" x-model="phoneNumber" @keydown.enter="makeCall()" placeholder="Nomor tujuan..." 
                       class="w-full border border-slate-300 p-3 text-center text-2xl rounded-lg font-bold outline-none focus:ring-2 focus:ring-blue-500 text-slate-700 tracking-wider">
                
                <div class="grid grid-cols-3 gap-2">
                    <template x-for="n in [1,2,3,4,5,6,7,8,9,'*',0,'#']" :key="n">
                        <button @click="appendDigit(n)" x-text="n" class="bg-slate-50 border border-slate-200 hover:bg-slate-100 p-3 rounded-lg font-bold text-lg text-slate-600 transition"></button>
                    </template>
                </div>

                <div class="flex gap-2">
                    <button @click="makeCall()" :disabled="isCalling" class="flex-1 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white p-3 rounded-lg font-bold flex items-center justify-center gap-2 transition">
                        <span x-text="isCalling ? '...' : 'Hubungi Sekarang'"></span>
                    </button>
                    <button @click="phoneNumber = ''" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 rounded-lg font-bold transition">Clear</button>
                </div>
            </div>
        </section>
    </main>

    <script>
        function agentWorkspace(extension) {
            return {
                extension,
                status: 'offline',
                phoneNumber: '',
                isCalling: false,

                init() {
                    this.loadAgentStatus();
                },

                loadAgentStatus() {
                    fetch(`/api/agent/${encodeURIComponent(this.extension)}`)
                        .then(r => r.json())
                        .then(d => { if(d.status === 'success') this.status = d.data.status ?? 'offline'; })
                },

                changeStatus(newStatus) {
                    fetch(`/api/agent/${encodeURIComponent(this.extension)}/status`, {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json', 
                            'X-CSRF-TOKEN': @js(csrf_token()) 
                        },
                        body: JSON.stringify({ status: newStatus })
                    })
                    .then(r => r.json())
                    .then(d => { 
                        if(d.status === 'success') this.status = newStatus; 
                    });
                },

                appendDigit(digit) { this.phoneNumber += String(digit); },

                makeCall() {
                    // Logic call sama seperti sebelumnya...
                    this.isCalling = true;
                    fetch(`/api/agent/${encodeURIComponent(this.extension)}/call`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                        body: JSON.stringify({ destination: this.phoneNumber })
                    })
                    .then(r => r.json())
                    .finally(() => { this.isCalling = false; this.phoneNumber = ''; });
                }
            }
        }
    </script>
</body>
</html>