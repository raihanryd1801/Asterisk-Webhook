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
                    @click="openCreateModal()"
                    class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2"
                >
                    <i class="fa-solid fa-plus"></i> Tambah Customer
                </button>
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
                    <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 border border-slate-300 rounded-lg px-3 py-2 cursor-pointer whitespace-nowrap" title="Hanya PTP broken / NPL belum lunas / DPD ≥ 120">
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
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Kontak</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status Bayar</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Jumlah Tagihan</th> <!-- Kolom Baru -->
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Jatuh Tempo</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Collector</th>
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
            </td>

            <!-- Kolom Kontak -->
            <td class="px-4 py-3">
                <div class="text-sm text-slate-700 font-mono" x-text="customer.phone"></div>
                <template x-if="customer.email">
                    <div class="text-xs text-slate-500" x-text="customer.email"></div>
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

            <!-- Kolom Collector -->
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
    
    <!-- Pastikan colspan disesuaikan menjadi 12 karena ada penambahan kolom -->
    <template x-if="customers.length === 0">
        <tr>
            <td colspan="12" class="px-4 py-12 text-center text-slate-500">
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
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Telepon <span class="text-red-500">*</span></label>
                        <input type="text" name="phone" x-model="form.phone" required class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="08xxxxxxxxxx">
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
                                <input type="number" name="total_amount" x-model="form.total_amount" step="0.01" min="0" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="0">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Sudah Dibayar</label>
                                <input type="number" name="paid_amount" x-model="form.paid_amount" step="0.01" min="0" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="0">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Diskon Pelunasan</label>
                                <input type="number" name="discount_amount" x-model="form.discount_amount" step="0.01" min="0" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="0">
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
                    <p class="text-xs text-slate-500">Format kolom: <span class="font-mono">name, phone, email, company, status, total_amount, paid_amount, discount_amount, payment_status, due_date (YYYY-MM-DD), notes</span>. Baris dengan phone yang sudah ada akan di-update.</p>
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
                        <textarea x-model="handoverForm.handover_notes" rows="3" placeholder="cth: NPL + PTP broken, sudah 3x visit..." class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
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
        indexUrl: '{{ route('crm.customers.index') }}',
        bulkAssignUrl: '{{ url('/dashboard/crm/collection/bulk-assign') }}',
        recalcUrl: '{{ url('/dashboard/crm/collection/recalculate-buckets') }}',
        exportUrl: '{{ url('/dashboard/crm/customers/export') }}',
        importUrl: '{{ url('/dashboard/crm/customers/import') }}',
        handoverReadyUrl: '{{ url('/dashboard/crm/collection/handover-ready') }}',
        handoverSubmitUrl: '{{ url('/dashboard/crm/collection/handover-submit') }}',
        handoverRecallUrl: '{{ url('/dashboard/crm/collection/handover-recall') }}',
        handoverExportUrl: '{{ url('/dashboard/crm/collection/handover/export') }}'
    };
</script>
@endsection