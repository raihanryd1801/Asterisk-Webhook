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
                <span class="text-[11px] font-bold text-slate-500 tracking-wider uppercase">Queue</span>
                <span class="px-3 py-1.5 rounded-md text-xs font-bold shadow-sm flex items-center gap-2 transition-colors"
                      :class="rotationJoined ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-white'">
                    <i class="fa-solid text-[10px]" :class="rotationJoined ? 'fa-rotate' : 'fa-headphones-simple'"></i>
                    <span x-text="rotationJoined ? 'PDS Rotation' : 'Manual Dial'"></span>
                </span>
                <template x-if="!rotationJoined">
                    <button @click="openRotationModal()" class="px-3 py-1.5 rounded-md bg-white border border-indigo-200 text-[11px] font-bold text-indigo-600 hover:bg-indigo-50 shadow-sm transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-right-to-bracket text-[10px]"></i> Join Rotation
                    </button>
                </template>
                <template x-if="rotationJoined">
                    <button @click="leaveRotation()" class="px-3 py-1.5 rounded-md bg-white border border-slate-200 text-[11px] font-bold text-slate-500 hover:bg-slate-50 hover:text-rose-500 shadow-sm transition-all flex items-center gap-1.5" title="Keluar dari rotation (kembali manual)">
                        <i class="fa-solid fa-right-from-bracket text-[10px]"></i> Leave
                    </button>
                </template>
            </div>
        </div>

        <!-- Info Tambahan di kanan toolbar -->
        <div class="hidden xl:flex items-center text-[11px] text-slate-500 bg-slate-50 border border-slate-100 rounded-lg px-4 py-2">
            <i class="fa-solid fa-circle-info text-brand-500 mr-2"></i> 
            <span>Pastikan status <strong class="text-emerald-600">Online</strong> untuk membuka kunci panggilan.</span>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- BAGIAN 2 & 2.5: DIALER KIRI + CUSTOMER KANAN  -->
    <!-- ============================================== -->
    <div class="grid grid-cols-1 xl:grid-cols-[320px_minmax(0,1fr)] gap-6 items-start w-full relative z-10 mt-4">
        <div class="w-full max-w-xs mx-auto xl:mx-0 xl:sticky xl:top-4 shrink-0">
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 space-y-3 relative overflow-hidden flex flex-col justify-between w-full">

                <div class="text-center">
                    <h2 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Click to Call</h2>
                </div>

                <!-- 🔒 OVERLAY KUNCI -->
                <template x-if="currentStatus !== 'online'">
                    <div class="absolute inset-0 bg-white/95 backdrop-blur-[4px] z-20 flex flex-col items-center justify-center p-4 text-center rounded-2xl border border-rose-100/50">
                        <div class="w-11 h-11 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mb-2 shadow-sm border border-rose-100">
                            <i class="fa-solid fa-lock text-lg"></i>
                        </div>
                        <h3 class="text-xs font-bold text-slate-800">Panel Terkunci</h3>
                        <p class="text-[11px] text-slate-500 mt-1 max-w-[200px] leading-relaxed">
                            Status <strong class="uppercase text-slate-700" x-text="currentStatus"></strong> — ubah ke <strong class="text-emerald-600 font-semibold">Online</strong> di menu atas.
                        </p>
                    </div>
                </template>

                <div class="space-y-3 w-full">
                    <div class="relative">
                        <input type="text" x-model="targetNumber" placeholder="Nomor tujuan..." class="w-full border border-slate-200 rounded-xl p-2.5 text-center text-xl outline-none font-mono bg-slate-50 focus:ring-2 focus:ring-brand-500 pr-10 tracking-widest text-slate-800 shadow-inner">
                        <button @click="targetNumber = targetNumber.slice(0, -1)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-rose-500 transition-colors">
                            <i class="fa-solid fa-delete-left"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="num in ['1','2','3','4','5','6','7','8','9','*','0','#']">
                            <button @click="targetNumber += num" class="w-11 h-11 mx-auto rounded-full border border-slate-200 text-slate-700 font-medium hover:bg-slate-100 hover:border-slate-300 active:scale-95 transition-all num text-base flex items-center justify-center shadow-sm bg-white" x-text="num"></button>
                        </template>
                    </div>

                    <div class="flex gap-2">
                        <button @click="makeCall()" :disabled="currentStatus !== 'online'" class="flex-1 bg-brand-600 hover:bg-brand-700 disabled:bg-slate-200 disabled:text-slate-400 text-white font-medium text-sm py-2 px-4 rounded-xl transition shadow-md flex items-center justify-center gap-2">
                            <i class="fa-solid fa-phone"></i> Panggil Sekarang
                        </button>
                        <button @click="targetNumber = ''" class="px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-sm rounded-xl transition shadow-sm border border-slate-200">
                            Clear
                        </button>
                    </div>
                </div>

                <div class="px-2 py-1.5 bg-slate-50 rounded-lg border border-slate-100 text-[10px] text-slate-500 flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-tower-cell text-brand-500"></i>
                    <span x-text="infoMessage"></span>
                </div>
            </div>
        </div>

    <!-- ============================================== -->
    <!-- BAGIAN 2.5: CUSTOMER ASSIGNED (CLICK TO CALL)  -->
    <!-- ============================================== -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden w-full min-w-0">
        <div class="p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/50">
            <div>
                <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-users text-brand-600"></i> Customer Assigned
                </h2>
                <p class="text-xs text-slate-500">Daftar customer yang ditugaskan ke Anda</p>
            </div>
            <button @click="fetchAssignedCustomers()" class="bg-brand-600 hover:bg-brand-700 text-white px-3 py-2 rounded-lg text-xs font-semibold shadow-sm transition whitespace-nowrap" title="Refresh">
                <i class="fa-solid fa-rotate"></i>
            </button>
        </div>

        <div class="p-5 bg-slate-50 xl:max-h-[calc(100vh-260px)] xl:overflow-y-auto">
            <!-- LOADING -->
            <template x-if="isLoadingCustomers">
                <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                    <i class="fa-solid fa-circle-notch fa-spin text-3xl mb-3 text-brand-500"></i>
                    <p class="text-xs font-medium animate-pulse tracking-wide">Memuat customer assigned...</p>
                </div>
            </template>

            <!-- EMPTY STATE -->
            <template x-if="!isLoadingCustomers && assignedCustomers.length === 0">
                <div class="text-center py-8 text-slate-400 text-sm border-2 border-dashed border-slate-200 rounded-xl bg-white">
                    <i class="fa-regular fa-user-plus text-3xl text-slate-300 mb-3 block"></i>
                    Belum ada customer yang ditugaskan ke Anda.
                </div>
            </template>

            <!-- CUSTOMER LIST -->
            <div class="space-y-2" x-show="!isLoadingCustomers && assignedCustomers.length > 0">
                <template x-for="(customer, index) in assignedCustomers" :key="customer.id">
                    <div class="border border-slate-200 rounded-lg bg-white overflow-hidden shadow-sm hover:border-brand-300 hover:shadow-md transition-all duration-200">
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-slate-800 truncate" x-text="customer.name"></span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border"
                                        :class="getCustomerStatusClass(customer.status)"
                                        x-text="formatCustomerStatus(customer.status)"></span>
                                </div>
                                <div class="flex items-center gap-3 mt-1.5 text-xs text-slate-500 flex-wrap">
                                    <span class="font-mono flex items-center gap-1" x-text="customer.phone"></span>
                                    <template x-if="customer.email">
                                        <span class="flex items-center gap-1" x-text="customer.email"></span>
                                    </template>
                                    <template x-if="customer.company">
                                        <span class="flex items-center gap-1 text-slate-400" x-text="customer.company"></span>
                                    </template>
                                </div>
                                <template x-if="customer.notes">
                                    <div class="mt-2 text-[11px] text-slate-500 bg-slate-50 p-2 rounded border border-slate-100 line-clamp-2" x-text="customer.notes"></div>
                                </template>
                                
                                <!-- Payment Info -->
                                <template x-if="customer.total_amount > 0">
                                    <div class="mt-2 p-2 bg-slate-50 rounded border border-slate-100">
                                        <div class="flex items-center gap-2 text-[11px]">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border"
                                                :class="getPaymentStatusClass(customer.payment_status)"
                                                x-text="formatPaymentStatus(customer.payment_status)"></span>
                                            <span class="text-slate-600 font-mono" x-text="'Rp ' + formatCurrency(customer.paid_amount) + ' / Rp ' + formatCurrency(customer.total_amount)"></span>
                                            <template x-if="customer.discount_amount > 0">
                                                <span class="text-emerald-600 font-mono" x-text="'Diskon: Rp ' + formatCurrency(customer.discount_amount)"></span>
                                            </template>
                                        </div>
                                        <div class="w-full h-1.5 bg-slate-200 rounded-full mt-1 overflow-hidden">
                                            <div class="h-full bg-brand-600 transition-all duration-300" :style="'width: ' + getPaymentProgress(customer) + '%'"></div>
                                        </div>
                                    </div>
                                </template>

                                <!-- PTP Badge (kalau sudah ada janji) -->
                                <template x-if="customer.promise_to_pay">
                                    <div class="mt-2 p-2 rounded border text-[11px] flex items-center gap-2"
                                        :class="ptpBadgeClass(customer.promise_to_pay)">
                                        <i class="fa-solid fa-handshake"></i>
                                        <span class="font-mono" x-text="'Rp ' + formatCurrency(customer.promise_to_pay.amount)"></span>
                                        <span x-text="formatPtpDate(customer.promise_to_pay.date)"></span>
                                        <span class="font-semibold uppercase" x-text="ptpStatusLabel(customer.promise_to_pay)"></span>
                                    </div>
                                </template>
                            </div>

                            <div class="shrink-0 flex items-center gap-2">
                                <button @click="openPtpModal(customer)"
                                    class="bg-amber-500 hover:bg-amber-600 text-white text-xs px-3 py-2 rounded-lg transition shadow-sm flex items-center gap-1.5 font-medium whitespace-nowrap"
                                    :title="'Buat PTP untuk ' + customer.name">
                                    <i class="fa-solid fa-handshake"></i> PTP
                                </button>
                                <button @click="callCustomer(customer.phone, customer.name)"
                                    :disabled="currentStatus !== 'online'"
                                    class="bg-brand-600 hover:bg-brand-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:hover:bg-slate-200 text-white text-xs px-3 py-2 rounded-lg transition shadow-sm flex items-center gap-1.5 font-medium whitespace-nowrap"
                                    :title="currentStatus !== 'online' ? 'Status harus Online untuk menelepon' : 'Panggil ' + customer.name">
                                    <i class="fa-solid fa-phone"></i> Call
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
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

    <!-- ============================================== -->
    <!-- MODAL JOIN ROTATION PDS                        -->
    <!-- ============================================== -->
    <div x-show="showRotationModal" x-transition.opacity style="display: none;" class="fixed inset-0 z-[100] overflow-y-auto" x-cloak>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="closeRotationModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-4 border-b border-slate-200">
                    <h3 class="text-base font-bold text-slate-800">Join PDS Rotation</h3>
                    <button @click="closeRotationModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <div class="p-4 space-y-3 text-sm text-slate-600 leading-relaxed">
                    <p>Dengan join rotation, Anda menyatakan <strong class="text-slate-800">siap menerima panggilan otomatis</strong> dari Auto-Dialer setiap kali standby (Online + idle + MicroSIP terdaftar).</p>
                    <ul class="list-disc list-inside space-y-1 text-[13px]">
                        <li>MicroSIP Anda akan <strong>berdering otomatis</strong> — angkat seperti biasa, lalu sistem menyambungkan ke customer.</li>
                        <li>Aktifkan <strong>auto-answer di MicroSIP</strong> agar benar-benar otomatis ngangkat.</li>
                        <li>Customer <strong>tidak mungkin tersambung tanpa agent</strong>: kaki telepon Anda selalu didial duluan.</li>
                        <li>Bisa keluar kapan saja via tombol <strong>Leave</strong> (kembali ke Manual Call).</li>
                    </ul>
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <button @click="closeRotationModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                        <button @click="joinRotation()" :disabled="rotationLoading" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 flex items-center gap-2">
                            <i class="fa-solid" :class="rotationLoading ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                            <span x-text="rotationLoading ? 'Memproses...' : 'Saya Siap, Join'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- MODAL BUAT PTP                                 -->
    <!-- ============================================== -->
    <div x-show="showPtpModal" x-transition.opacity style="display: none;" class="fixed inset-0 z-[100] overflow-y-auto" x-cloak>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="closePtpModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-4 border-b border-slate-200">
                    <h3 class="text-base font-bold text-slate-800">Buat PTP — <span x-text="ptpCustomer?.name"></span></h3>
                    <button @click="closePtpModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <form @submit.prevent="submitPtp()" class="p-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nominal Janji Bayar (Rp) <span class="text-red-500">*</span></label>
                        <input type="number" x-model="ptpForm.amount" min="1" step="1" required placeholder="cth: 2000000"
                            class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Janji <span class="text-red-500">*</span></label>
                        <input type="date" x-model="ptpForm.date" required :min="ptpMinDate"
                            class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Catatan</label>
                        <textarea x-model="ptpForm.note" rows="3" placeholder="cth: gajian tanggal 10, janji transfer..."
                            class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <button type="button" @click="closePtpModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                        <button type="submit" :disabled="ptpSaving" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 flex items-center gap-2">
                            <i class="fa-solid" :class="ptpSaving ? 'fa-spinner fa-spin' : 'fa-handshake'"></i>
                            <span x-text="ptpSaving ? 'Menyimpan...' : 'Simpan PTP'"></span>
                        </button>
                    </div>
                </form>
            </div>
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
            activeCall: null,
            wasCalling: false,
            logs: [],
            pagination: { current_page: 1, last_page: 1, total: 0 },
            filters: { search: '' },
            statusInterval: null, 
            isLoading: true,
            // 🚀 CUSTOMER ASSIGNED
            assignedCustomers: [],
            isLoadingCustomers: true,
            // 🚀 BUAT PTP
            showPtpModal: false,
            ptpCustomer: null,
            ptpForm: { amount: '', date: '', note: '' },
            ptpSaving: false,
            ptpMinDate: new Date().toISOString().split('T')[0],
            // 🚀 PDS ROTATION
            rotationJoined: false,
            rotationLoading: false,
            showRotationModal: false,

            init() {
                this.fetchAgentStatus();
                this.statusInterval = setInterval(() => { this.fetchAgentStatus(); }, 5000);
                this.fetchLogs(1);
                this.fetchAssignedCustomers();
                this.fetchRotationStatus();
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
                        // 🚀 State call live dari ami:listen (sumber yang sama dengan live monitoring)
                        this.activeCall = currentAgent.is_calling
                            ? { status: currentAgent.call_status, destination: currentAgent.current_destination }
                            : null;
                        this.refreshInfoFromCall();
                    }
                }).catch(err => console.error("Gagal sinkronisasi status"));
            },

            // Samakan tulisan status web dengan live monitoring (ringing -> connected)
            refreshInfoFromCall() {
                if (this.activeCall) {
                    this.wasCalling = true;
                    if (this.activeCall.status === 'connected') {
                        this.infoMessage = `Terhubung dengan ${this.activeCall.destination || ''} — bicara via MicroSIP.`;
                    } else if (this.activeCall.status === 'ringing') {
                        this.infoMessage = `Berdering... menghubungi ${this.activeCall.destination || ''}.`;
                    } else if (this.activeCall.status) {
                        this.infoMessage = `Status panggilan: ${this.activeCall.status} ${this.activeCall.destination || ''}`.trim();
                    }
                } else if (this.wasCalling) {
                    // Call baru saja selesai -> kembalikan ke default
                    this.wasCalling = false;
                    this.infoMessage = 'MikroSIP siap digunakan...';
                }
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
                    // Refresh state call lebih cepat setelah originate (max 3x percobaan)
                    [1500, 3500, 6000].forEach(ms => setTimeout(() => { this.fetchAgentStatus(); }, ms));
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
            },

            // 🚀 CUSTOMER ASSIGNED METHODS
            fetchAssignedCustomers() {
                this.isLoadingCustomers = true;
                fetch(`/dashboard/crm/agent/${this.extension}/customers`, { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(response => {
                    if (response.status === 'success') {
                        this.assignedCustomers = response.data;
                    }
                })
                .catch(err => console.error('Gagal memuat customer assigned:', err))
                .finally(() => {
                    this.isLoadingCustomers = false;
                });
            },

            callCustomer(phone, name) {
                if (this.currentStatus !== 'online') {
                    this.infoMessage = 'Status harus Online untuk menelepon';
                    return;
                }
                this.targetNumber = phone;
                this.infoMessage = `Menghubungkan ke ${name} (${phone})...`;
                this.makeCall();
            },

            formatCustomerStatus(status) {
                const labels = {
                    'new': 'New',
                    'contacted': 'Contacted',
                    'qualified': 'Qualified',
                    'proposal': 'Proposal',
                    'closed_won': 'Closed Won',
                    'closed_lost': 'Closed Lost',
                };
                return labels[status] || status;
            },

            getCustomerStatusClass(status) {
                const classes = {
                    'new': 'bg-blue-50 text-blue-700 border-blue-200',
                    'contacted': 'bg-yellow-50 text-yellow-700 border-yellow-200',
                    'qualified': 'bg-purple-50 text-purple-700 border-purple-200',
                    'proposal': 'bg-indigo-50 text-indigo-700 border-indigo-200',
                    'closed_won': 'bg-green-50 text-green-700 border-green-200',
                    'closed_lost': 'bg-red-50 text-red-700 border-red-200',
                };
                return classes[status] || 'bg-slate-50 text-slate-700 border-slate-200';
            },

            async fetchRotationStatus() {
                try {
                    const response = await fetch(`/dashboard/crm/dialer/rotation/status`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await response.json();
                    if (data.status === 'success') {
                        this.rotationJoined = !!data.joined;
                    }
                } catch (err) {
                    console.error('Gagal cek rotation:', err);
                }
            },

            openRotationModal() { this.showRotationModal = true; },
            closeRotationModal() { this.showRotationModal = false; },

            async joinRotation() {
                this.rotationLoading = true;
                try {
                    const response = await fetch(`/dashboard/crm/dialer/rotation/join`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                            'Accept': 'application/json',
                        },
                    });
                    const data = await response.json();
                    if (response.ok && data.status === 'success') {
                        this.rotationJoined = true;
                        this.closeRotationModal();
                        this.infoMessage = data.message;
                    } else {
                        alert(data.message || 'Gagal join rotation');
                    }
                } catch (err) {
                    alert('Gagal join rotation: ' + err.message);
                } finally {
                    this.rotationLoading = false;
                }
            },

            async leaveRotation() {
                if (!confirm('Keluar dari rotation PDS? (kembali ke Manual Call)')) return;
                try {
                    const response = await fetch(`/dashboard/crm/dialer/rotation/leave`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                            'Accept': 'application/json',
                        },
                    });
                    const data = await response.json();
                    if (response.ok && data.status === 'success') {
                        this.rotationJoined = false;
                        this.infoMessage = data.message;
                    } else {
                        alert(data.message || 'Gagal leave rotation');
                    }
                } catch (err) {
                    alert('Gagal leave rotation: ' + err.message);
                }
            },

            formatPaymentStatus(status) {
                const labels = {
                    'unpaid': 'Belum Bayar',
                    'partial': 'Cicilan',
                    'paid': 'Lunas',
                    'discounted': 'Diskon Lunas',
                };
                return labels[status] || status;
            },

            getPaymentStatusClass(status) {
                const classes = {
                    'unpaid': 'bg-red-50 text-red-700 border-red-200',
                    'partial': 'bg-yellow-50 text-yellow-700 border-yellow-200',
                    'paid': 'bg-green-50 text-green-700 border-green-200',
                    'discounted': 'bg-purple-50 text-purple-700 border-purple-200',
                };
                return classes[status] || 'bg-slate-50 text-slate-700 border-slate-200';
            },

            openPtpModal(customer) {
                this.ptpCustomer = customer;
                const existing = customer.promise_to_pay || {};
                this.ptpForm = {
                    amount: existing.amount || '',
                    date: existing.date || '',
                    note: existing.note || '',
                };
                this.showPtpModal = true;
            },

            closePtpModal() {
                this.showPtpModal = false;
                this.ptpCustomer = null;
                this.ptpForm = { amount: '', date: '', note: '' };
            },

            async submitPtp() {
                if (!this.ptpCustomer) return;
                if (!this.ptpForm.amount || !this.ptpForm.date) {
                    alert('Nominal dan tanggal janji wajib diisi!');
                    return;
                }
                this.ptpSaving = true;
                try {
                    const response = await fetch(`/dashboard/crm/agent/${this.extension}/customers/${this.ptpCustomer.id}/ptp`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            ptp_amount: this.ptpForm.amount,
                            ptp_date: this.ptpForm.date,
                            ptp_note: this.ptpForm.note,
                        }),
                    });
                    const data = await response.json();
                    if (response.ok && data.status === 'success') {
                        this.closePtpModal();
                        this.fetchAssignedCustomers();
                    } else {
                        alert(data.message || 'Gagal menyimpan PTP');
                    }
                } catch (err) {
                    alert('Gagal menyimpan PTP: ' + err.message);
                } finally {
                    this.ptpSaving = false;
                }
            },

            ptpStatusLabel(ptp) {
                if (!ptp) return '';
                if (ptp.status === 'kept') return 'Ditepati';
                if (ptp.status === 'broken') return 'Gagal';
                const today = new Date().toISOString().split('T')[0];
                return (ptp.date && ptp.date < today) ? 'Overdue' : 'Aktif';
            },

            ptpBadgeClass(ptp) {
                if (!ptp) return 'bg-slate-50 text-slate-600 border-slate-200';
                if (ptp.status === 'kept') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
                if (ptp.status === 'broken') return 'bg-rose-50 text-rose-700 border-rose-200';
                const today = new Date().toISOString().split('T')[0];
                return (ptp.date && ptp.date < today)
                    ? 'bg-red-50 text-red-700 border-red-200'
                    : 'bg-amber-50 text-amber-700 border-amber-200';
            },

            formatPtpDate(dateStr) {
                if (!dateStr) return '';
                const d = new Date(dateStr);
                return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
            },

            formatCurrency(amount) {
                return new Intl.NumberFormat('id-ID').format(amount || 0);
            },

            getPaymentProgress(customer) {
                if (!customer.total_amount || customer.total_amount <= 0) return 0;
                const paid = (customer.paid_amount || 0) + (customer.discount_amount || 0);
                return Math.min(100, Math.round((paid / customer.total_amount) * 100));
            }
        }));
    });
</script>
@endsection