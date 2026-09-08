@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="callHistoryPage()">
    
    <!-- Header Title -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-5 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-brand-600"></i> Call History & Recordings
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Arsip riwayat percakapan telepon lengkap dengan catatan dari agen dan rekaman.</p>
        </div>
        
        <div class="flex items-center gap-2 flex-wrap">
    
            <!-- Dropdown Pilih Format -->
            <select x-model="exportFormat" class="border border-gray-300 rounded-xl px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="xlsx">Format Excel (.xlsx)</option>
                <option value="csv">Format CSV (.csv)</option>
            </select>

            <!-- Tombol Export Baru dengan state isExporting -->
            <button @click="exportData()" :disabled="isExporting" 
                class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium shadow-sm transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                
                <!-- Icon berubah jadi muter saat isExporting = true -->
                <i class="fa-solid" :class="isExporting ? 'fa-gear fa-spin' : 'fa-file-excel text-xs'"></i> 
                
                <!-- Teks berubah dinamis tergantung format yang dipilih -->
                <span x-text="isExporting ? 'Merakit ' + exportFormat.toUpperCase() + '...' : 'Export Data'"></span>
            </button>

            <!-- Tombol Export ZIP Rekaman -->
<button @click="exportRecordingsZip()" :disabled="isExportingZip" 
    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium shadow-sm transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
    
    <!-- Ikon berputar saat proses zip berjalan -->
    <i class="fa-solid" :class="isExportingZip ? 'fa-gear fa-spin' : 'fa-file-zipper text-xs'"></i> 
    
    <span x-text="isExportingZip ? 'Merakit File ZIP...' : 'Export ZIP Rekaman'"></span>
</button>
            
            <button @click="syncAndRefresh()" :disabled="isSyncing" 
                class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium shadow-sm transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
            
            <i class="fa-solid fa-rotate text-xs" :class="isSyncing ? 'fa-spin' : ''"></i> 
            
            <span x-text="isSyncing ? 'Syncing...' : 'Refresh Data'"></span>
            </button>
        </div>
    </div>

    <!-- Panel Filter & Pencarian -->
<div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 items-end">
    
    <!-- 1. Dari Tanggal -->
    <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Dari Tanggal</label>
        <input type="date" x-model="filters.start_date" class="w-full border border-slate-200 rounded-xl p-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 bg-slate-50">
    </div>
    
    <!-- 2. Sampai Tanggal -->
    <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Sampai Tanggal</label>
        <input type="date" x-model="filters.end_date" class="w-full border border-slate-200 rounded-xl p-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 bg-slate-50">
    </div>
    
    <!-- 3. Filter Agen -->
    <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Filter Agen</label>
        <select x-model="filters.agent_extension" class="w-full border border-slate-200 rounded-xl p-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50">
            <option value="">-- Semua Agen --</option>
            <template x-for="agent in agents" :key="agent.extension">
                <option :value="agent.extension" x-text="agent.name + ' (Ext: ' + agent.extension + ')'"></option>
            </template>
        </select>
    </div>

    <!-- 4. Cari Nomor / Ext -->
    <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Cari Nomor</label>
        <input type="text" x-model="filters.search" @keydown.enter="fetchLogs(1)" placeholder="Cth: 0812 / 105..." class="w-full border border-slate-200 rounded-xl p-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50 font-mono">
    </div>

    <!-- 5. Urutkan -->
    <div>
    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Urutkan</label>
    <select x-model="filters.sort" @change="filters.sort = $event.target.value; fetchLogs(1)" class="w-full border border-slate-200 rounded-xl p-2.5 text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50">
    <option value="newest">Newest first</option>    
    <option value="oldest">Oldest first</option>
        
        <option value="longest">Longest duration</option>
        <option value="shortest">Shortest duration</option>
    </select>
</div>

    <!-- 6. Action Buttons -->
    <div class="flex gap-2">
        <button @click="fetchLogs(1)" class="flex-1 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm py-2.5 rounded-xl transition shadow-sm">
            Cari
        </button>
        <button @click="resetFilters()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-sm px-4 py-2.5 rounded-xl transition">
            Reset
        </button>
    </div>

</div>

    <!-- Tabel Call History dengan Wrapper Relative -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
    
    <!-- 🚀 ANIMASI LOADING OVERLAY (Muncul saat isLoading = true) -->
    <div x-show="isLoading" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="absolute inset-0 bg-white/70 backdrop-blur-[1px] z-20 flex flex-col items-center justify-center">
        
        <div class="flex items-center gap-3 bg-slate-900 text-white px-4 py-3 rounded-2xl shadow-xl text-xs font-semibold tracking-wide">
            <i class="fa-solid fa-circle-notch fa-spin text-cyan-400 text-sm"></i>
            <span>Memuat data panggilan...</span>
        </div>
    </div>

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
                    <th class="px-6 py-4 w-72">Catatan Agen</th>
                    <th class="px-6 py-4">Rekaman</th>
                </tr>
            </thead>
            <!-- Efek redup tipis pada tbody saat loading -->
            <tbody class="divide-y divide-slate-100 transition-opacity duration-200" :class="{ 'opacity-25': isLoading }">
                <template x-for="(log, index) in logs" :key="log.uniqueid || index">
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
                        <td class="px-6 py-4 text-xs">
                            <span x-text="log.notes || '-'" :class="log.notes ? 'text-slate-700 font-medium' : 'text-slate-400 italic'"></span>
                        </td>
                        <td class="px-6 py-4">
                            <!-- Kolom Audio (biarkan tetap seperti sebelumnya) -->
                            <template x-if="log.recordingfile">
                                <div class="flex items-center gap-2">
                                    <template x-if="playingFile !== log.recordingfile">
                                        <div class="flex items-center gap-2">
                                            <button @click="playAudio(log.recordingfile)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-medium shadow-sm transition active:scale-95">
                                                <i class="fa-solid fa-play text-[10px] text-brand-600"></i> Listen
                                            </button>
                                            <a :href="'/dashboard/api/play-recording?file=' + log.recordingfile" download class="inline-flex items-center justify-center w-7 h-7 rounded-full border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 shadow-sm transition active:scale-95" title="Download Rekaman">
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

    <!-- Paginasi -->
    <!-- ... (bagian paginasi biarkan tetap sama) ... -->


        <!-- 🚀 PAGINATION YANG BARU (Simple Paginate) -->
        <!-- Paginasi Lengkap ala Enterprise -->
        <div class="px-6 py-4 bg-slate-50 border-t flex flex-col xl:flex-row justify-between items-center gap-4">
    
    <!-- Info Total Data -->
    <span class="text-xs text-slate-500 font-medium">
        Menampilkan halaman <strong class="text-slate-700" x-text="pagination.current_page"></strong> dari <strong class="text-slate-700" x-text="pagination.last_page"></strong> (Total <strong class="text-slate-700" x-text="pagination.total"></strong> data)
    </span>
    
    <div class="flex items-center flex-wrap gap-4">
        <!-- Tombol Navigasi Halaman -->
        <nav class="flex items-center gap-1">
            
            <!-- Tombol First (<<) -->
            <button @click="fetchLogs(1)" 
                    :disabled="pagination.current_page === 1"
                    class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs transition" title="Halaman Pertama">
                <i class="fa-solid fa-angles-left text-[10px]"></i>
            </button>

            <!-- Tombol Prev (<) -->
            <button @click="fetchLogs(pagination.current_page - 1)" 
                    :disabled="pagination.current_page === 1"
                    class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs transition" title="Sebelumnya">
                <i class="fa-solid fa-chevron-left text-[10px]"></i>
            </button>

            <!-- Looping Angka Halaman & Titik-titik (...) -->
            <template x-for="page in getPaginationPages()" :key="page">
                <button @click="typeof page === 'number' ? fetchLogs(page) : null"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all border"
                        :class="page === pagination.current_page ? 'bg-cyan-600 text-white border-cyan-600 shadow-sm' : (page === '...' ? 'border-transparent text-slate-400 cursor-default' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50')"
                        x-text="page"
                        :disabled="page === '...'">
                </button>
            </template>

            <!-- Tombol Next (>) -->
            <button @click="fetchLogs(pagination.current_page + 1)" 
                    :disabled="pagination.current_page === pagination.last_page"
                    class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs transition" title="Berikutnya">
                <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </button>

            <!-- Tombol Last (>>) -->
            <button @click="fetchLogs(pagination.last_page)" 
                    :disabled="pagination.current_page === pagination.last_page"
                    class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs transition" title="Halaman Terakhir">
                <i class="fa-solid fa-angles-right text-[10px]"></i>
            </button>
        </nav>

        <!-- Fitur "Go to Page" -->
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-500">Go to</span>
            <input type="number" 
                   x-model="gotoPage" 
                   @keydown.enter="jumpToPage()"
                   min="1" 
                   :max="pagination.last_page"
                   placeholder="Halaman..." 
                   class="w-16 border border-slate-200 rounded-lg px-2 py-1 text-xs text-center outline-none focus:ring-2 focus:ring-cyan-500 bg-white font-mono">
            <button @click="jumpToPage()" 
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1 rounded-lg text-xs font-semibold transition border border-slate-200">
                Go
            </button>
        </div>
    </div>
</div>
    </div>
</div>
@endsection