@extends('layouts.app')

@php
    $paginationData = [
        'current_page' => $customers->currentPage(),
        'last_page' => $customers->lastPage(),
        'per_page' => $customers->perPage(),
        'total' => $customers->total(),
        'from' => $customers->firstItem(),
        'to' => $customers->lastItem(),
        'prev_page_url' => $customers->previousPageUrl(),
        'next_page_url' => $customers->nextPageUrl(),
    ];
@endphp

@section('content')
<div x-data="crmCustomers()">

    <div class="flex-col flex gap-6">
        <div class="bg-brand-50 border border-brand-100 rounded-2xl p-5 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-users text-brand-600"></i> CRM - Customer Management
                </h1>
                <p class="text-sm text-slate-500 mt-0.5">Kelola data customer dan lead.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button
                    @click="recalculateBuckets()"
                    :disabled="recalcLoading"
                    class="bg-slate-600 hover:bg-slate-700 disabled:opacity-50 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2"
                    title="Hitung ulang DPD / Bucket / Risk dari due_date"
                >
                    <i class="fa-solid" :class="recalcLoading ? 'fa-spinner fa-spin' : 'fa-rotate'"></i>
                    <span x-text="recalcLoading ? 'Menghitung...' : 'Recalculate Bucket'"></span>
                </button>
                
    <a
    :href="exportHref()"
    data-turbo="false"
    class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2"
    title="Export data sesuai filter ke Excel"
>
    <i class="fa-solid fa-file-excel"></i> Export
</a>
                <a
    :href="exportHandoverHref()"
    data-turbo="false"
    class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2"
    title="Export data handover (siap + sudah diserahkan) ke Excel"
>
    <i class="fa-solid fa-share-from-square"></i> Export Handover
</a>
                <button
                    @click="openImportModal()"
                    class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2"
                    title="Import data dari Excel"
                >
                    <i class="fa-solid fa-file-import"></i> Import
                </button>
                <button
                    @click="openAutoAssignAgentModal()"
                    class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2"
                    title="Bagi customer otomatis ke beberapa agent (round-robin)"
                >
                    <i class="fa-solid fa-users-gear"></i> Auto Assign Agent
                </button>
                <button
                    @click="openCreateModal()"
                    class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2"
                >
                    <i class="fa-solid fa-plus"></i> Tambah Customer
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <button @click="openMapPanel()" class="w-full p-4 flex items-center justify-between hover:bg-slate-50 transition-colors">
                <span class="text-sm font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-map-location-dot text-brand-600"></i> Peta Sebaran Customer
                    <span class="text-[11px] font-medium text-slate-400 normal-case" id="map-count"></span>
                </span>
                <span class="flex items-center gap-2">
                    <span onclick="event.stopPropagation(); window.syncMapPoints(this)" title="Sinkronkan koordinat alamat yang belum terpetakan (background)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-brand-600 text-white hover:bg-brand-700 transition-colors cursor-pointer">
                        <i class="fa-solid fa-rotate"></i> Sinkronkan
                    </span>
                    <i class="fa-solid text-slate-400 text-xs transition-transform" :class="mapOpen ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </span>
            </button>
            <div x-show="mapOpen" x-cloak>
                <div id="customer-map" class="w-full h-96 z-0"></div>
                <p class="px-4 py-2 text-[11px] text-slate-400 border-t border-slate-100">© OpenStreetMap contributors • Klik marker untuk detail + rute</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row gap-4">
                <div class="flex-1 max-w-md">
                    <input 
                        type="text" 
                        x-model="search" 
                        @keyup.enter="fetchCustomers()"
                        placeholder="Cari nama, telepon, email, company..."
                        class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                    >
                </div>
                <div class="flex gap-2">
                    <select 
                        x-model="statusFilter" 
                        @change="fetchCustomers()"
                        class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                    >
                        <option value="">Semua Status</option>
                        <template x-for="status in statuses" :key="status">
                            <option :value="status" x-text="formatStatus(status)"></option>
                        </template>
                    </select>
                    <select
                        x-model="paymentStatusFilter"
                        @change="fetchCustomers()"
                        class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                    >
                        <option value="">Semua Status Bayar</option>
                        <option value="unpaid">Belum Bayar</option>
                        <option value="partial">Cicilan / Setengah</option>
                        <option value="paid">Lunas</option>
                        <option value="discounted">Diskon Lunas</option>
                    </select>
                    <select
                        x-model="bucketFilter"
                        @change="fetchCustomers()"
                        class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                    >
                        <option value="">Semua Bucket</option>
                        <option value="Current">Current</option>
                        <option value="Bucket 1">Bucket 1</option>
                        <option value="Bucket 2">Bucket 2</option>
                        <option value="Bucket 3">Bucket 3</option>
                        <option value="NPL">NPL</option>
                    </select>
                    <select
                        x-model="agentFilter" 
                        @change="fetchCustomers()"
                        class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                    >
                        <option value="">Semua Agent</option>
                        <template x-for="agent in agents" :key="agent.id">
                            <option :value="agent.id" x-text="agent.name + ' (Ext: ' + agent.extension + ')'"></option>
                        </template>
                    </select>
                    <select
                        x-model="handoverFilter"
                        @change="fetchCustomers()"
                        class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                        title="Filter status handover pihak ketiga"
                    >
                        <option value="">Semua Handover</option>
                        <option value="none">Belum Handover</option>
                        <option value="ready">Siap Handover</option>
                        <option value="handed_over">Sudah Diserahkan</option>
                        <option value="returned">Ditarik Kembali</option>
                    </select>
                    <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 border border-slate-300 rounded-lg px-3 py-2 cursor-pointer whitespace-nowrap" title="Hanya PTP rolling / NPL belum lunas / DPD ≥ 120">
                        <input type="checkbox" x-model="badDebtOnly" @change="fetchCustomers()" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Bad debt saja
                    </label>
                </div>
            </div>

            <div x-show="selectedIds.length > 0" class="px-4 py-3 bg-brand-50 border-b border-brand-100 flex flex-col md:flex-row md:items-center gap-3">
                <span class="text-sm font-medium text-slate-700"><span x-text="selectedIds.length"></span> dipilih</span>
                <select x-model="bulkCollectorId" class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    <option value="">Pilih Debt Collector...</option>
                    <template x-for="a in collectors" :key="a.id">
                        <option :value="a.id" x-text="a.name + (a.type === 'field' ? ' (Lapangan)' : ' (Desk)') + (a.phone ? ' - ' + a.phone : '')"></option>
                    </template>
                </select>
                <button @click="bulkAssign()" :disabled="bulkLoading || !bulkCollectorId" class="bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                    <i class="fa-solid" :class="bulkLoading ? 'fa-spinner fa-spin' : 'fa-bullseye'"></i>
                    <span x-text="bulkLoading ? 'Assign...' : 'Assign Collector'"></span>
                </button>
                <span class="hidden md:inline text-slate-300">|</span>
                <select x-model="bulkAgentId" class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    <option value="">Pilih Agent...</option>
                    <template x-for="a in agents" :key="a.id">
                        <option :value="a.id" x-text="a.name + ' (Ext: ' + a.extension + ')'"></option>
                    </template>
                </select>
                <button @click="bulkAssignAgent()" :disabled="bulkAgentLoading || !bulkAgentId" class="bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2" title="Assign customer terpilih ke 1 agent">
                    <i class="fa-solid" :class="bulkAgentLoading ? 'fa-spinner fa-spin' : 'fa-user-check'"></i>
                    <span x-text="bulkAgentLoading ? 'Assign...' : 'Assign Agent'"></span>
                </button>
                <span class="hidden md:inline text-slate-300">|</span>
                <button @click="markHandoverReady()" :disabled="handoverLoading" class="bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2" title="Tandai case busuk siap dilempar ke pihak ketiga">
                    <i class="fa-solid" :class="handoverLoading ? 'fa-spinner fa-spin' : 'fa-flag'"></i>
                    <span>Siap Handover</span>
                </button>
                <button @click="openHandoverModal()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2" title="Serahkan ke pihak ketiga / agensi">
                    <i class="fa-solid fa-share-from-square"></i>
                    <span>Serahkan</span>
                </button>
                <button @click="handoverRecall()" :disabled="handoverLoading" class="bg-slate-600 hover:bg-slate-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2" title="Tarik kembali case yang sudah diserahkan">
                    <i class="fa-solid fa-rotate-left"></i>
                    <span>Tarik Kembali</span>
                </button>
                <button @click="clearSelection()" class="text-sm text-slate-500 hover:text-slate-700 underline">Batalkan pilihan</button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
    <tr>
        <th class="px-4 py-3 text-left"><input type="checkbox" @change="toggleSelectAll($event)" :checked="customers.length > 0 && selectedIds.length === customers.length" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" title="Pilih semua di halaman ini"></th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Customer</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Nomor Utama</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Kantor</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Darurat</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Gender</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status Bayar</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Jumlah Tagihan</th> <!-- Kolom Baru -->
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Jatuh Tempo</th>
        <template x-if="!hideCollector"><th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Collector</th></template>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Terakhir Bayar</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Assigned Agent</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Last Contact</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
    </tr>
</thead>
                    <tbody class="divide-y divide-slate-200" x-ref="tbody">
    <template x-for="customer in customers" :key="customer.id">
        <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-4 py-3">
                <input type="checkbox" :checked="isSelected(customer.id)" @change="toggleSelect(customer.id)" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            </td>
            <!-- Kolom Customer -->
            <td class="px-4 py-3">
                <div class="font-medium text-slate-900" x-text="customer.name"></div>
                <div class="text-sm text-slate-500" x-text="customer.company || '-'"></div>
                <template x-if="customer.email">
                    <div class="text-xs text-slate-500" x-text="customer.email"></div>
                </template>
                <template x-if="customer.address">
                    <div class="text-xs text-slate-500 truncate max-w-[220px] flex items-center gap-1" :title="customer.address">
                        <i class="fa-solid fa-location-dot text-[10px] text-rose-500 shrink-0"></i>
                        <span class="truncate" x-text="customer.address"></span>
                    </div>
                </template>
            </td>

            <!-- Kolom Nomor Utama -->
            <td class="px-4 py-3">
                <div class="text-sm text-slate-700 font-mono" x-text="customer.phone"></div>
            </td>

            <!-- Kolom Nomor Kantor -->
            <td class="px-4 py-3">
                <template x-if="customer.office_phone">
                    <div class="text-sm text-slate-700 font-mono" x-text="customer.office_phone"></div>
                </template>
                <template x-if="!customer.office_phone">
                    <span class="text-slate-400 text-sm">-</span>
                </template>
            </td>

            <!-- Kolom Nomor Darurat -->
            <td class="px-4 py-3">
                <template x-if="customer.emergency_phone">
                    <div class="text-sm text-slate-700 font-mono" x-text="customer.emergency_phone"></div>
                </template>
                <template x-if="!customer.emergency_phone">
                    <span class="text-slate-400 text-sm">-</span>
                </template>
            </td>

            <!-- Kolom Gender -->
            <td class="px-4 py-3">
                <template x-if="customer.gender">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="customer.gender === 'P' ? 'bg-pink-50 text-pink-700 border border-pink-200' : 'bg-sky-50 text-sky-700 border border-sky-200'"
                        x-text="customer.gender === 'P' ? 'Perempuan' : 'Laki-laki'"></span>
                </template>
                <template x-if="!customer.gender">
                    <span class="text-slate-400 text-sm">-</span>
                </template>
            </td>

            <!-- Kolom Status Lead/Customer -->
            <td class="px-4 py-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="getStatusClass(customer.status)"
                    x-text="formatStatus(customer.status)"></span>
            </td>

            <!-- Kolom 1: Status Bayar Saja -->
            <td class="px-4 py-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                    :class="getPaymentStatusClass(customer.payment_status)"
                    x-text="formatPaymentStatus(customer.payment_status)"></span>
            </td>

            <!-- Kolom 2: Jumlah Tagihan & Progress Bar Saja -->
            <td class="px-4 py-3">
                <template x-if="customer.total_amount > 0">
                    <div>
                        <div class="text-xs font-mono font-medium text-slate-700" x-text="'Rp ' + formatCurrency(customer.paid_amount) + ' / ' + 'Rp ' + formatCurrency(customer.total_amount)"></div>
                        <div class="w-28 h-1.5 bg-slate-200 rounded-full mt-1.5 overflow-hidden">
                            <div class="h-full bg-brand-600 transition-all duration-300" :style="'width: ' + (customer.payment_progress || 0) + '%'"></div>
                        </div>
                    </div>
                </template>
                <template x-if="!customer.total_amount || customer.total_amount == 0">
                    <span class="text-slate-400 text-xs italic">Tanpa Tagihan</span>
                </template>
            </td>

            <!-- Kolom Jatuh Tempo / Bucket -->
            <td class="px-4 py-3">
                <template x-if="customer.due_date">
                    <div class="text-sm text-slate-700 font-mono" x-text="formatDate(customer.due_date)"></div>
                </template>
                <template x-if="!customer.due_date">
                    <span class="text-slate-400 text-xs">-</span>
                </template>
                <template x-if="customer.bucket">
                    <div class="mt-1 flex items-center gap-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border"
                            :class="getBucketClass(customer.bucket)"
                            x-text="customer.bucket"></span>
                        <template x-if="customer.days_past_due > 0">
                            <span class="text-[10px] text-red-600 font-mono" x-text="customer.days_past_due + ' DPD'"></span>
                        </template>
                    </div>
                </template>
            </td>

            <!-- Kolom Collector (disembunyikan bila modul premium collector terkunci) -->
            <template x-if="!hideCollector">
            <td class="px-4 py-3">
                <template x-if="customer.collector">
                    <div class="text-xs text-slate-600" x-text="customer.collector.name + (customer.collector.type === 'field' ? ' (Lapangan)' : ' (Desk)')"></div>
                </template>
                <template x-if="!customer.collector">
                    <span class="text-slate-400 text-xs">-</span>
                </template>
                <template x-if="customer.handover_status && customer.handover_status !== 'none'">
                    <div class="mt-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border"
                            :class="getHandoverClass(customer.handover_status)"
                            x-text="formatHandoverStatus(customer.handover_status)"></span>
                        <template x-if="customer.handover_to">
                            <div class="text-[10px] text-slate-500 truncate max-w-[140px]" :title="customer.handover_to" x-text="'→ ' + customer.handover_to"></div>
                        </template>
                    </div>
                </template>
            </td>
            </template>

            <!-- Kolom Terakhir Bayar -->
            <td class="px-4 py-3 text-sm text-slate-500">
                <template x-if="customer.last_payment_date">
                    <span x-text="formatDate(customer.last_payment_date)"></span>
                </template>
                <template x-if="!customer.last_payment_date">
                    <span class="text-slate-400">-</span>
                </template>
            </td>

            <!-- Kolom Assigned Agent -->
            <td class="px-4 py-3">
                <template x-if="customer.assigned_agent">
                    <div>
                        <div class="text-sm text-slate-700" x-text="customer.assigned_agent.name"></div>
                        <div class="text-xs text-slate-500 font-mono" x-text="'Ext: ' + customer.assigned_agent.extension"></div>
                    </div>
                </template>
                <template x-if="!customer.assigned_agent">
                    <span class="text-slate-400 text-sm">-</span>
                </template>
            </td>

            <!-- Kolom Last Contact -->
            <td class="px-4 py-3 text-sm text-slate-500">
                <template x-if="customer.last_contacted_at">
                    <span x-text="formatDate(customer.last_contacted_at)"></span>
                </template>
                <template x-if="!customer.last_contacted_at">
                    <span class="text-slate-400">-</span>
                </template>
            </td>

            <!-- Kolom Actions -->
            <td class="px-4 py-3">
                <div class="flex items-center gap-1">
                    <template x-if="customer.address">
                        <a :href="'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(customer.address)" target="_blank" rel="noopener" class="text-rose-600 hover:text-rose-800 p-1.5 rounded hover:bg-rose-50 transition-colors" title="Buka alamat di Google Maps">
                            <i class="fa-solid fa-location-dot text-sm"></i>
                        </a>
                    </template>
                    <button @click="openPayModal(customer)" class="text-emerald-600 hover:text-emerald-800 p-1.5 rounded hover:bg-emerald-50 transition-colors" title="Catat Pembayaran / Riwayat">
                        <i class="fa-solid fa-money-bill-wave text-sm"></i>
                    </button>
                    <button @click="openEditModal(customer)" class="text-brand-600 hover:text-brand-800 p-1.5 rounded hover:bg-brand-50 transition-colors" title="Edit">
                        <i class="fa-solid fa-pen text-sm"></i>
                    </button>
                    <button @click="openCallHistoryModal(customer)" class="text-slate-600 hover:text-slate-800 p-1.5 rounded hover:bg-slate-100 transition-colors" title="Call History">
                        <i class="fa-solid fa-phone text-sm"></i>
                    </button>
                    <button @click="deleteCustomer(customer.id)" class="text-red-600 hover:text-red-800 p-1.5 rounded hover:bg-red-50 transition-colors" title="Delete">
                        <i class="fa-solid fa-trash text-sm"></i>
                    </button>
                </div>
            </td>
        </tr>
    </template>
    
    <!-- Pastikan colspan disesuaikan menjadi 15 karena ada penambahan kolom -->
    <template x-if="customers.length === 0">
        <tr>
            <td :colspan="hideCollector ? 14 : 15" class="px-4 py-12 text-center text-slate-500">
                <i class="fa-solid fa-users text-3xl mb-2 block text-slate-300"></i>
                Belum ada data customer
            </td>
        </tr>
    </template>
</tbody>
                    
                </table>
            </div>

            <div class="p-4 border-t border-slate-200 flex items-center justify-between" x-show="pagination.total > 0">
                <div class="text-sm text-slate-600">
                    Menampilkan <span class="font-medium" x-text="pagination.from"></span> - 
                    <span class="font-medium" x-text="pagination.to"></span> dari 
                    <span class="font-medium" x-text="pagination.total"></span> data
                </div>
                <div class="flex gap-2">
                    <button 
                        @click="prevPage()" 
                        :disabled="!pagination.prev_page_url"
                        class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50 transition-colors"
                    >
                        Previous
                    </button>
                    <button 
                        @click="nextPage()" 
                        :disabled="!pagination.next_page_url"
                        class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50 transition-colors"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div> <div x-show="showModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" @click.outside="closeModal()" style="display: none;" x-cloak>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="closeModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-4 border-b border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900" x-text="modalTitle"></h3>
                    <button @click="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <form @submit.prevent="submitForm()" class="p-4 space-y-4">
                    <input type="hidden" name="id" x-model="form.id">
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Customer <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="form.name" required class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Utama / Pribadi <span class="text-red-500">*</span></label>
                        <input type="text" name="phone" x-model="form.phone" required class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="08xxxxxxxxxx">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Jenis Kelamin</label>
                            <select name="gender" x-model="form.gender" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                                <option value="">-</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Kantor</label>
                            <input type="text" name="office_phone" x-model="form.office_phone" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="021xxxxxxx">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Emergency Contact</label>
                            <input type="text" name="emergency_phone" x-model="form.emergency_phone" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="08xxxxxxxxxx">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" name="email" x-model="form.email" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Company</label>
                        <input type="text" name="company" x-model="form.company" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Alamat <span class="text-slate-400 font-normal">(untuk kunjungan collector + buka map)</span></label>
                        <textarea name="address" x-model="form.address" rows="2" placeholder="cth: Jl. Slamet Riyadi No. 10, Kanigaran, Kanigaran, Probolinggo — tanpa singkatan Kec./Kel./Kab." class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Titik Manual <span class="text-slate-400 font-normal">(opsional — paling akurat, dari Google Maps)</span></label>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" inputmode="decimal" name="latitude" x-model="form.latitude" placeholder="Latitude, cth: -7.76878" class="w-full border border-slate-300 rounded-lg px-4 py-2 font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                            <input type="text" inputmode="decimal" name="longitude" x-model="form.longitude" placeholder="Longitude, cth: 113.21327" class="w-full border border-slate-300 rounded-lg px-4 py-2 font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Cara ambil: buka alamat di Google Maps → klik kanan titik persisnya → klik koordinat (tersalin) → tempel di sini. Titik manual mengunci pin (tidak diobrak-abrik sinkronisasi otomatis) dan ikut muncul di peta.</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status <span class="text-red-500">*</span></label>
                        <select name="status" x-model="form.status" required class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                            <template x-for="status in statuses" :key="status">
                                <option :value="status" x-text="formatStatus(status)"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Payment Fields -->
                    <div class="border-t border-slate-200 pt-4 mt-2">
                        <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-money-bill-wave text-brand-600"></i> Info Pembayaran
                        </h4>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Total Tagihan</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400 pointer-events-none">Rp</span>
                                    <input type="text" inputmode="numeric" name="total_amount" :value="formatRupiah(form.total_amount)" @input="form.total_amount = parseRupiah($event.target.value); $event.target.value = formatRupiah(form.total_amount)" class="w-full border border-slate-300 rounded-lg pl-9 pr-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent font-mono" placeholder="Rp 0">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Sudah Dibayar</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400 pointer-events-none">Rp</span>
                                    <input type="text" inputmode="numeric" name="paid_amount" :value="formatRupiah(form.paid_amount)" @input="form.paid_amount = parseRupiah($event.target.value); $event.target.value = formatRupiah(form.paid_amount)" class="w-full border border-slate-300 rounded-lg pl-9 pr-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent font-mono" placeholder="Rp 0">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Diskon Pelunasan</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400 pointer-events-none">Rp</span>
                                    <input type="text" inputmode="numeric" name="discount_amount" :value="formatRupiah(form.discount_amount)" @input="form.discount_amount = parseRupiah($event.target.value); $event.target.value = formatRupiah(form.discount_amount)" class="w-full border border-slate-300 rounded-lg pl-9 pr-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent font-mono" placeholder="Rp 0">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Status Pembayaran</label>
                                <select name="payment_status" x-model="form.payment_status" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                                    <option value="unpaid">Belum Bayar</option>
                                    <option value="partial">Cicilan / Setengah</option>
                                    <option value="paid">Lunas</option>
                                    <option value="discounted">Diskon Lunas</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Catatan Pembayaran</label>
                            <textarea name="payment_notes" x-model="form.payment_notes" rows="2" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="Catatan terkait pembayaran..."></textarea>
                        </div>
                    </div>

                    <!-- Collection Fields -->
                    <div class="border-t border-slate-200 pt-4 mt-2">
                        <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-layer-group text-brand-600"></i> Info Collection
                        </h4>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Jatuh Tempo</label>
                                <input type="date" name="due_date" x-model="form.due_date" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                                <p class="text-[11px] text-slate-400 mt-1">Bucket/DPD dihitung otomatis saat simpan.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Risk Level</label>
                                <select name="risk_level" x-model="form.risk_level" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Debt Collector <span class="text-slate-400 font-normal">(Opsional)</span></label>
                                <select name="collector_id" x-model="form.collector_id" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                                    <option value="">Tanpa Debt Collector</option>
                                    <template x-for="a in collectors" :key="a.id">
                                        <option :value="a.id" x-text="a.name + (a.type === 'field' ? ' (Lapangan)' : ' (Desk)') + (a.phone ? ' - ' + a.phone : '')"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Assigned Agent</label>
                        <select name="assigned_agent_id" x-model="form.assigned_agent_id" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                            <option value="">Pilih Agent</option>
                            <template x-for="agent in agents" :key="agent.id">
                                <option :value="agent.id" x-text="agent.name + ' (Ext: ' + agent.extension + ')'"></option>
                            </template>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Catatan</label>
                        <textarea name="notes" x-model="form.notes" rows="3" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <button type="button" @click="closeModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                        <button type="submit" :disabled="submitting" class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm hover:bg-brand-700 transition-colors disabled:opacity-50">
                            <span x-show="!submitting" x-text="form.id ? 'Update' : 'Simpan'"></span>
                            <span x-show="submitting" class="flex items-center gap-2"><i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-show="showCallHistoryModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" @click.outside="closeCallHistoryModal()" style="display: none;" x-cloak>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="closeCallHistoryModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[80vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between p-4 border-b border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Call History - <span x-text="selectedCustomer?.name"></span> (<span x-text="selectedCustomer?.phone"></span>)</h3>
                    <button @click="closeCallHistoryModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto p-4">
                    <template x-if="callHistoryLoading">
                        <div class="flex items-center justify-center h-64">
                            <i class="fa-solid fa-spinner fa-spin text-2xl text-brand-500"></i>
                        </div>
                    </template>
                    <template x-if="!callHistoryLoading && callHistory.length === 0">
                        <div class="text-center py-12 text-slate-500">
                            <i class="fa-solid fa-phone-slash text-3xl mb-2 block text-slate-300"></i>
                            Tidak ada riwayat call untuk customer ini
                        </div>
                    </template>
                    <template x-if="!callHistoryLoading && callHistory.length > 0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Tanggal</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Direction</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">From</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">To</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Duration</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <template x-for="call in callHistory" :key="call.uniqueid">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-3 py-2" x-text="formatDateTime(call.calldate)"></td>
                                            <td class="px-3 py-2">
                                                <span :class="call.src == selectedCustomer?.phone ? 'text-green-600' : 'text-blue-600'" x-text="call.src == selectedCustomer?.phone ? 'Outbound' : 'Inbound'"></span>
                                            </td>
                                            <td class="px-3 py-2 font-mono" x-text="call.src"></td>
                                            <td class="px-3 py-2 font-mono" x-text="call.dst"></td>
                                            <td class="px-3 py-2">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="getDispositionClass(call.disposition)" x-text="call.disposition"></span>
                                            </td>
                                            <td class="px-3 py-2" x-text="formatDuration(call.duration)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showImportModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="closeImportModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-4 border-b border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Import Customers</h3>
                    <button @click="closeImportModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <form @submit.prevent="submitImport()" class="p-4 space-y-4">
                    <p class="text-xs text-slate-500">Format kolom: <span class="font-mono">name, phone, gender (L/P), office_phone, emergency_phone, email, company, address, status, total_amount, paid_amount, discount_amount, payment_status, due_date (YYYY-MM-DD), notes</span>. Baris dengan phone yang sudah ada akan di-update.</p>
                    <input type="file" x-ref="importFile" accept=".xlsx,.xls,.csv" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm" required>
                    <div x-show="importResult" class="text-xs rounded-lg p-3" :class="importResult?.failed > 0 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'" x-text="importResult ? ('Import selesai: ' + importResult.imported + ' baru, ' + importResult.updated + ' update, ' + importResult.failed + ' gagal.') : ''"></div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <button type="button" @click="closeImportModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Tutup</button>
                        <button type="submit" :disabled="importLoading" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm hover:bg-amber-700 transition-colors disabled:opacity-50">
                            <span x-show="!importLoading">Upload & Import</span>
                            <span x-show="importLoading" class="flex items-center gap-2"><i class="fa-solid fa-spinner fa-spin"></i> Mengimpor...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-show="showHandoverModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="closeHandoverModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-4 border-b border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Serahkan ke Pihak Ketiga</h3>
                    <button @click="closeHandoverModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <form @submit.prevent="submitHandover()" class="p-4 space-y-4">
                    <p class="text-xs text-slate-500"><span class="font-bold text-slate-700" x-text="selectedIds.length"></span> case akan diserahkan dan tidak lagi ditagih internal (kecuali ditarik kembali).</p>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Pihak Ketiga / Agensi <span class="text-red-500">*</span></label>
                        <input type="text" x-model="handoverForm.handover_to" required placeholder="cth: PT Tagih Beres" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Serah Terima</label>
                        <input type="date" x-model="handoverForm.handover_date" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Catatan Serah Terima</label>
                        <textarea x-model="handoverForm.handover_notes" rows="3" placeholder="cth: NPL + PTP rolling, sudah 3x visit..." class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <button type="button" @click="closeHandoverModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                        <button type="submit" :disabled="handoverLoading" class="px-4 py-2 bg-orange-600 text-white rounded-lg text-sm hover:bg-orange-700 transition-colors disabled:opacity-50">
                            <span x-show="!handoverLoading">Serahkan Sekarang</span>
                            <span x-show="handoverLoading" class="flex items-center gap-2"><i class="fa-solid fa-spinner fa-spin"></i> Memproses...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-show="showAutoAssignAgentModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="closeAutoAssignAgentModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-4 border-b border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Auto Assign ke Agent</h3>
                    <button @click="closeAutoAssignAgentModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <form @submit.prevent="submitAutoAssignAgent()" class="p-4 space-y-4">
                    <p class="text-xs text-slate-500">Customer dibagi rata (<span class="font-semibold">round-robin</span>) ke agent terpilih. Cocok untuk 1000+ data.</p>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-slate-700">Agent Tujuan <span class="text-red-500">*</span></label>
                            <button type="button" @click="toggleAllAutoAssignAgents()" class="text-xs text-brand-600 hover:text-brand-800 underline" x-text="autoAssignAgentIds.length === agents.length ? 'Batalkan semua' : 'Pilih semua'"></button>
                        </div>
                        <div class="max-h-40 overflow-y-auto border border-slate-200 rounded-lg p-2 space-y-1">
                            <template x-for="a in agents" :key="a.id">
                                <label class="flex items-center gap-2 text-sm text-slate-700 px-2 py-1 rounded hover:bg-slate-50 cursor-pointer">
                                    <input type="checkbox" :value="a.id" x-model="autoAssignAgentIds" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    <span x-text="a.name + ' (Ext: ' + a.extension + ')'"></span>
                                </label>
                            </template>
                            <template x-if="agents.length === 0">
                                <p class="text-xs text-slate-400 p-2">Belum ada agent.</p>
                            </template>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Filter Bucket <span class="text-slate-400 font-normal">(kosongkan = semua)</span></label>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="b in ['Current', 'Bucket 1', 'Bucket 2', 'Bucket 3', 'NPL']" :key="b">
                                <label class="inline-flex items-center gap-1.5 text-xs border rounded-lg px-2.5 py-1.5 cursor-pointer transition-colors"
                                    :class="autoAssignBuckets.includes(b) ? 'bg-brand-50 border-brand-300 text-brand-800' : 'border-slate-300 text-slate-600'">
                                    <input type="checkbox" :value="b" x-model="autoAssignBuckets" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    <span x-text="b"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" x-model="autoAssignOnlyUnassigned" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Hanya yang belum di-assign
                    </label>
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <button type="button" @click="closeAutoAssignAgentModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                        <button type="submit" :disabled="autoAssignLoading || autoAssignAgentIds.length === 0" class="px-4 py-2 bg-teal-600 text-white rounded-lg text-sm hover:bg-teal-700 transition-colors disabled:opacity-50">
                            <span x-show="!autoAssignLoading">Bagi Otomatis</span>
                            <span x-show="autoAssignLoading" class="flex items-center gap-2"><i class="fa-solid fa-spinner fa-spin"></i> Memproses...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-show="showPayModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="closePayModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-4 border-b border-slate-200 sticky top-0 bg-white rounded-t-2xl">
                    <h3 class="text-lg font-semibold text-slate-900">Pembayaran — <span x-text="payCustomer?.name"></span></h3>
                    <button @click="closePayModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <div class="p-4 space-y-4">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-sm flex flex-wrap gap-x-4 gap-y-1">
                        <span class="text-slate-500">Tagihan: <strong class="font-mono text-slate-800" x-text="'Rp ' + formatCurrency(payCustomer?.total_amount || 0)"></strong></span>
                        <span class="text-slate-500">Terbayar: <strong class="font-mono text-emerald-700" x-text="'Rp ' + formatCurrency(payCustomer?.paid_amount || 0)"></strong></span>
                        <span class="text-slate-500">Saldo awal: <strong class="font-mono text-slate-600" x-text="'Rp ' + formatCurrency(payOpening)"></strong></span>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-slate-700 mb-2">Riwayat Transaksi</h4>
                        <template x-if="payLoading">
                            <div class="flex items-center justify-center py-6 text-slate-400"><i class="fa-solid fa-spinner fa-spin text-xl"></i></div>
                        </template>
                        <template x-if="!payLoading && payHistory.length === 0">
                            <p class="text-xs text-slate-400 italic py-2">Belum ada transaksi tercatat. Saldo saat ini adalah saldo awal.</p>
                        </template>
                        <div class="space-y-2 max-h-56 overflow-y-auto" x-show="!payLoading && payHistory.length > 0">
                            <template x-for="p in payHistory" :key="p.id">
                                <div class="flex items-center gap-3 border border-slate-200 rounded-xl p-2.5 text-sm">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-mono font-semibold text-emerald-700" x-text="'Rp ' + formatCurrency(p.amount)"></div>
                                        <div class="text-xs text-slate-500" x-text="formatDate(p.paid_at) + ' • ' + p.method_label + (p.by ? ' • ' + p.by : '')"></div>
                                        <template x-if="p.notes">
                                            <div class="text-xs text-slate-500 truncate" x-text="p.notes"></div>
                                        </template>
                                    </div>
                                    <template x-if="p.proof_url">
                                        <a :href="p.proof_url" target="_blank" rel="noopener" class="text-brand-600 hover:text-brand-800 p-1.5" title="Lihat bukti"><i class="fa-solid fa-receipt"></i></a>
                                    </template>
                                    <button @click="deletePayment(p.id)" class="text-red-400 hover:text-red-600 p-1.5" title="Hapus (koreksi)"><i class="fa-solid fa-trash text-xs"></i></button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <form @submit.prevent="submitPay()" class="border-t border-slate-200 pt-4 space-y-3">
                        <h4 class="text-sm font-semibold text-slate-700">Catat Pembayaran Baru</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Nominal (Rp) <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400 pointer-events-none">Rp</span>
                                    <input type="text" inputmode="numeric" :value="formatRupiah(payForm.amount)" @input="payForm.amount = parseRupiah($event.target.value); $event.target.value = formatRupiah(payForm.amount)" required class="w-full border border-slate-300 rounded-lg pl-9 pr-4 py-2 text-sm font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="Rp 0">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Bayar <span class="text-red-500">*</span></label>
                                <input type="date" x-model="payForm.paid_at" required :max="payToday" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Metode <span class="text-red-500">*</span></label>
                                <select x-model="payForm.method" required class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                                    <option value="transfer">Transfer</option>
                                    <option value="cash">Tunai</option>
                                    <option value="va">Virtual Account</option>
                                    <option value="qris">QRIS</option>
                                    <option value="autodebet">Autodebet</option>
                                    <option value="lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Bukti (jpg/png/pdf)</label>
                                <input type="file" x-ref="payProof" accept=".jpg,.jpeg,.png,.pdf" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Keterangan</label>
                            <textarea x-model="payForm.notes" rows="2" placeholder="cth: cicilan ke-2 via BCA..." class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="closePayModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Tutup</button>
                            <button type="submit" :disabled="paySaving" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors disabled:opacity-50">
                                <span x-show="!paySaving">Simpan Pembayaran</span>
                                <span x-show="paySaving" class="flex items-center gap-2"><i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showResultModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="closeResultModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between p-4 border-b border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900" x-text="resultTitle"></h3>
                    <button @click="closeResultModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <div class="p-4 border-b border-slate-100 text-sm text-slate-600" x-text="resultSummary"></div>
                <div class="flex-1 overflow-y-auto p-4">
                    <template x-if="resultRows.length === 0">
                        <div class="text-center py-8 text-slate-400 text-sm">Tidak ada perubahan.</div>
                    </template>
                    <template x-if="resultRows.length > 0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Customer</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Telepon</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Dari</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Ke</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <template x-for="(row, idx) in resultRows" :key="idx">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-3 py-2 font-medium text-slate-900" x-text="row.name"></td>
                                            <td class="px-3 py-2 font-mono text-slate-600" x-text="row.phone"></td>
                                            <td class="px-3 py-2"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200" x-text="row.from"></span></td>
                                            <td class="px-3 py-2"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200" x-text="row.to"></span></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                    <p x-show="resultTruncated" class="text-[11px] text-slate-400 mt-2">Hanya 200 baris pertama yang ditampilkan.</p>
                </div>
                <div class="p-4 border-t border-slate-200 flex justify-end">
                    <button @click="closeResultModal()" class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm hover:bg-brand-700 transition-colors">Tutup</button>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    // Cegah Turbo melakukan cache pada halaman ini
    if (!document.querySelector('meta[name="turbo-cache-control"]')) {
        let meta = document.createElement('meta');
        meta.name = 'turbo-cache-control';
        meta.content = 'no-cache';
        document.head.appendChild(meta);
    }

    // Oper variabel dari PHP Laravel ke Global Window agar dibaca oleh crm-customers.js
    window.crmCustomerData = {
        customers: @json($customers->items()),
        pagination: @json($paginationData),
        agents: @json($agents),
        statuses: @json($statuses),
        collectors: @json($collectors ?? []),
        hideCollector: @json(\App\Models\FeatureFlag::lockedForCurrentUser('collector')),
        indexUrl: '{{ route('crm.customers.index') }}',
        bulkAssignUrl: '{{ url('/dashboard/crm/collection/bulk-assign-collector') }}',
        bulkAssignAgentUrl: '{{ url('/dashboard/crm/customers/bulk-assign-agent') }}',
        autoAssignAgentUrl: '{{ url('/dashboard/crm/customers/auto-assign-agent') }}',
        paymentsBaseUrl: '{{ url('/dashboard/crm/payments') }}',
        recalcUrl: '{{ url('/dashboard/crm/collection/recalculate-buckets') }}',
        exportUrl: '{{ url('/dashboard/crm/customers/export') }}',
        importUrl: '{{ url('/dashboard/crm/customers/import') }}',
        handoverReadyUrl: '{{ url('/dashboard/crm/collection/handover-ready') }}',
        handoverSubmitUrl: '{{ url('/dashboard/crm/collection/handover-submit') }}',
        handoverRecallUrl: '{{ url('/dashboard/crm/collection/handover-recall') }}',
        handoverExportUrl: '{{ url('/dashboard/crm/collection/handover/export') }}'
    };
</script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Peta sebaran customer (Leaflet + OSM, tanpa API key). Guard window agar
// eksekusi ulang oleh Turbo tidak crash/duplikat map.
window._custMapEsc = window._custMapEsc || function (s) {
    return String(s ?? '').replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
};
window.custMapReload = window.custMapReload || function () {
    try { if (window._custMap) window._custMap.remove(); } catch (e) {}
    window._custMap = null;
    window._custMarkers = [];
    var el = document.getElementById('customer-map');
    if (el) el.innerHTML = '';
    window.custMapInit(true);
};
window.custMapLoadPoints = window.custMapLoadPoints || function () {
    var map = window._custMap;
    if (!map) return;
    fetch('{{ route('crm.customers.map-points') }}', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var pts = (d && d.data) || [];
            var cnt = document.getElementById('map-count');
            if (cnt) cnt.textContent = pts.length ? pts.length + ' titik' : 'belum ada titik';
            (window._custMarkers || []).forEach(function (m) { try { map.removeLayer(m); } catch (e) {} });
            window._custMarkers = [];
            if (!pts.length) return;
            var bounds = [];
            var esc = window._custMapEsc;
            pts.forEach(function (p) {
                var lat = parseFloat(p.latitude), lng = parseFloat(p.longitude);
                if (isNaN(lat) || isNaN(lng)) return;
                var m = L.marker([lat, lng]).addTo(map);
                window._custMarkers.push(m);
                var gmaps = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(lat + ',' + lng);
                m.bindPopup('<strong>' + esc(p.name || '-') + '</strong><br>' +
                    esc(p.address || '') + '<br>' +
                    '<span>' + esc(p.phone || '') + (p.bucket ? ' • ' + esc(p.bucket) : '') + '</span><br>' +
                    (p.geocode_label ? '<span style="color:#94a3b8;font-size:11px">📍 ' + esc(p.geocode_label === 'Manual' ? 'Titik manual (Google Maps)' : p.geocode_label.split(',').slice(0, 3).join(',')) + '</span><br>' : '') +
                    '<a href="' + gmaps + '" target="_blank" rel="noopener">Rute →</a>');
                bounds.push([lat, lng]);
            });
            if (bounds.length) map.fitBounds(bounds, { padding: [30, 30] });
        })
        .catch(function () {});
};
window.syncMapPoints = window.syncMapPoints || async function (btn) {
    if (!confirm('Sinkronkan koordinat untuk alamat yang belum terpetakan? Berjalan di background.')) return;
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menjadwalkan...';
    try {
        const tokenRes = await fetch('{{ route('crm.whatsapp.csrf') }}', { headers: { 'Accept': 'application/json' } });
        const tokenData = await tokenRes.json().catch(() => ({}));
        const res = await fetch('{{ route('crm.customers.geocode-sync') }}', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': tokenData.csrf || (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
            },
        });
        const data = await res.json();
        alert(data.message || data.status);
        // Poll peta 4x tiap 30 detik agar hasil background terlihat tanpa reload.
        // (±20 alamat ≈ 25-60 detik via Nominatim 1,2 dtk/query).
        let tries = 0;
        const poll = setInterval(function () {
            tries++;
            window.custMapLoadPoints();
            if (tries >= 4) clearInterval(poll);
        }, 30000);
    } catch (e) {
        alert('Gagal menjadwalkan sinkronisasi.');
    } finally {
        btn.innerHTML = orig;
    }
};
window.custMapInit = window.custMapInit || function (force) {
    var el = document.getElementById('customer-map');
    if (!el || typeof L === 'undefined') return;
    // Sama seperti peta tracking: Turbo mengganti <body> tapi window hidup —
    // map lama menempel ke container yang sudah dibuang. Buang & buat ulang.
    if (window._custMap && window._custMap.getContainer() !== el) {
        try { window._custMap.remove(); } catch (e) {}
        window._custMap = null;
        window._custMarkers = [];
    }
    // Jangan init saat panel hidden (ukurannya 0) kecuali dipaksa dari openMapPanel.
    if (!force && el.offsetParent === null) return;
    try {
        if (window._custMap) {
            // Panel dibuka-tutup: refresh ukuran (wajib karena init saat hidden)
            setTimeout(function () {
                try { window._custMap.invalidateSize(); } catch (e) {}
                window.custMapLoadPoints();
            }, 80);
            return;
        }
        var map = L.map('customer-map').setView([-2.5, 118], 5);
        window._custMap = map;
        window._custMarkers = [];
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        setTimeout(function () { try { map.invalidateSize(); } catch (e) {} }, 80);
        window.custMapLoadPoints();
    } catch (e) {}
};
document.addEventListener('DOMContentLoaded', function () { window.custMapInit(); });
document.addEventListener('turbo:load', function () { window.custMapInit(); });
</script>
@endsection