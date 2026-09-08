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
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">CRM - Customer Management</h1>
                <p class="text-slate-500 mt-1">Kelola data customer dan lead</p>
            </div>
            <button 
                @click="openCreateModal()"
                class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2"
            >
                <i class="fa-solid fa-plus"></i> Tambah Customer
            </button>
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
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Kontak</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status Bayar</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Assigned Agent</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Last Contact</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200" x-ref="tbody">
                        <template x-for="customer in customers" :key="customer.id">
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900" x-text="customer.name"></div>
                                    <div class="text-sm text-slate-500" x-text="customer.company || '-'"></div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-slate-700 font-mono" x-text="customer.phone"></div>
                                    <template x-if="customer.email">
                                        <div class="text-xs text-slate-500" x-text="customer.email"></div>
                                    </template>
                                </td>
                                <td class="px-4 py-3">
                                    <span 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="getStatusClass(customer.status)"
                                        x-text="formatStatus(customer.status)"
                                    ></span>
                                </td>
                                <td class="px-4 py-3">
                                    <span 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="getPaymentStatusClass(customer.payment_status)"
                                        x-text="formatPaymentStatus(customer.payment_status)"
                                    ></span>
                                    <template x-if="customer.total_amount > 0">
                                        <div class="text-[10px] text-slate-500 mt-0.5 font-mono" x-text="'Rp ' + formatCurrency(customer.paid_amount) + ' / Rp ' + formatCurrency(customer.total_amount)"></div>
                                        <div class="w-24 h-1.5 bg-slate-200 rounded-full mt-1 overflow-hidden">
                                            <div class="h-full bg-brand-600 transition-all duration-300" :style="'width: ' + customer.payment_progress + '%'"></div>
                                        </div>
                                    </template>
                                </td>
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
                                <td class="px-4 py-3 text-sm text-slate-500">
                                    <template x-if="customer.last_contacted_at">
                                        <span x-text="formatDate(customer.last_contacted_at)"></span>
                                    </template>
                                    <template x-if="!customer.last_contacted_at">
                                        <span class="text-slate-400">-</span>
                                    </template>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <button 
                                            @click="openEditModal(customer)"
                                            class="text-brand-600 hover:text-brand-800 p-1.5 rounded hover:bg-brand-50 transition-colors"
                                            title="Edit"
                                        >
                                            <i class="fa-solid fa-pen text-sm"></i>
                                        </button>
                                        <button 
                                            @click="openCallHistoryModal(customer)"
                                            class="text-slate-600 hover:text-slate-800 p-1.5 rounded hover:bg-slate-100 transition-colors"
                                            title="Call History"
                                        >
                                            <i class="fa-solid fa-phone text-sm"></i>
                                        </button>
                                        <button 
                                            @click="deleteCustomer(customer.id)"
                                            class="text-red-600 hover:text-red-800 p-1.5 rounded hover:bg-red-50 transition-colors"
                                            title="Delete"
                                        >
                                            <i class="fa-solid fa-trash text-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="customers.length === 0">
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-slate-500">
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
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Catatan Pembayaran</label>
                            <textarea name="payment_notes" x-model="form.payment_notes" rows="2" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="Catatan terkait pembayaran..."></textarea>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Assigned Agent</label>
                        <select name="assigned_agent_id" x-model="form.assigned_agent_id" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                            <option value="">Pilih Agent (Opsional)</option>
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

</div> @endsection

@section('scripts')
<script>
    // Cegah Turbo melakukan cache pada halaman ini agar script selalu jalan
    if (!document.querySelector('meta[name="turbo-cache-control"]')) {
        let meta = document.createElement('meta');
        meta.name = 'turbo-cache-control';
        meta.content = 'no-cache';
        document.head.appendChild(meta);
    }

    // 🚀 DAFTARKAN KE GLOBAL WINDOW AGAR LANGSUNG DIKENALI OLEH TURBO & ALPINE
    window.crmCustomers = function () {
    return {
        customers: @json($customers->items()),
        pagination: @json($paginationData),
        agents: @json($agents),
        statuses: @json($statuses),
        search: '',
        statusFilter: '',
        agentFilter: '',
        showModal: false,
        showCallHistoryModal: false,
        modalTitle: '',
        form: { id: '', name: '', phone: '', email: '', company: '', status: 'new', assigned_agent_id: '', notes: '', total_amount: '', paid_amount: '', discount_amount: '', payment_status: 'unpaid', payment_notes: '' },
        selectedCustomer: null,
        paymentStatusFilter: '',
        callHistory: [],
        callHistoryLoading: false,
        submitting: false,

        async fetchCustomers(page = 1) {
            const params = new URLSearchParams();
            if (this.search) params.append('search', this.search);
            if (this.statusFilter) params.append('status', this.statusFilter);
            if (this.paymentStatusFilter) params.append('payment_status', this.paymentStatusFilter);
            if (this.agentFilter) params.append('agent_id', this.agentFilter);
            params.append('page', page);
            
            try {
                const response = await fetch(`{{ route('crm.customers.index') }}?${params}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const data = await response.json();
                this.customers = data.data;
                this.pagination = {
                    current_page: data.current_page,
                    last_page: data.last_page,
                    per_page: data.per_page,
                    total: data.total,
                    from: data.from,
                    to: data.to,
                    prev_page_url: data.prev_page_url,
                    next_page_url: data.next_page_url,
                };
            } catch (e) {
                console.error(e);
            }
        },

        prevPage() {
            if (this.pagination.prev_page_url) this.fetchCustomers(this.pagination.current_page - 1);
        },
        nextPage() {
            if (this.pagination.next_page_url) this.fetchCustomers(this.pagination.current_page + 1);
        },

        openCreateModal() {
            this.modalTitle = 'Tambah Customer';
            this.form = { id: '', name: '', phone: '', email: '', company: '', status: 'new', assigned_agent_id: '', notes: '', total_amount: '', paid_amount: '', discount_amount: '', payment_status: 'unpaid', payment_notes: '' };
            this.showModal = true;
        },

        openEditModal(customer) {
            this.modalTitle = 'Edit Customer';
            this.form = {
                id: customer.id,
                name: customer.name,
                phone: customer.phone,
                email: customer.email || '',
                company: customer.company || '',
                status: customer.status,
                assigned_agent_id: customer.assigned_agent_id || '',
                notes: customer.notes || '',
                total_amount: customer.total_amount || '',
                paid_amount: customer.paid_amount || '',
                discount_amount: customer.discount_amount || '',
                payment_status: customer.payment_status || 'unpaid',
                payment_notes: customer.payment_notes || '',
            };
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            this.form = { id: '', name: '', phone: '', email: '', company: '', status: 'new', assigned_agent_id: '', notes: '', total_amount: '', paid_amount: '', discount_amount: '', payment_status: 'unpaid', payment_notes: '' };
        },

        async submitForm() {
            this.submitting = true;
            const isEdit = !!this.form.id;
            const url = isEdit ? `{{ route('crm.customers.index') }}/${this.form.id}` : '{{ route('crm.customers.index') }}';
            const method = isEdit ? 'PUT' : 'POST';
            
            const formData = new FormData();
            Object.keys(this.form).forEach(key => {
                if (this.form[key] !== '' && this.form[key] !== null) formData.append(key, this.form[key]);
            });
            formData.append('_method', method);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            try {
                const response = await fetch(url, { method: 'POST', body: formData });
                const data = await response.json();
                if (data.status === 'success') {
                    this.closeModal();
                    this.fetchCustomers(this.pagination.current_page);
                } else {
                    alert(data.message || 'Error');
                }
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            } finally {
                this.submitting = false;
            }
        },

        async deleteCustomer(id) {
            if (!confirm('Yakin ingin menghapus customer ini?')) return;
            try {
                const response = await fetch(`{{ route('crm.customers.index') }}/${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ _method: 'DELETE' })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    this.fetchCustomers(this.pagination.current_page);
                } else {
                    alert(data.message || 'Error');
                }
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            }
        },

        async openCallHistoryModal(customer) {
            this.selectedCustomer = customer;
            this.showCallHistoryModal = true;
            this.callHistoryLoading = true;
            this.callHistory = [];
            
            try {
                const response = await fetch(`{{ route('crm.customers.index') }}/${customer.id}/calls`, {
                    headers: { 'Accept': 'application/json' }
                });
                this.callHistory = await response.json();
            } catch (e) {
                console.error(e);
            } finally {
                this.callHistoryLoading = false;
            }
        },

        closeCallHistoryModal() {
            this.showCallHistoryModal = false;
            this.selectedCustomer = null;
            this.callHistory = [];
        },

        formatStatus(status) {
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

        getStatusClass(status) {
            const classes = {
                'new': 'bg-blue-100 text-blue-800',
                'contacted': 'bg-yellow-100 text-yellow-800',
                'qualified': 'bg-purple-100 text-purple-800',
                'proposal': 'bg-indigo-100 text-indigo-800',
                'closed_won': 'bg-green-100 text-green-800',
                'closed_lost': 'bg-red-100 text-red-800',
            };
            return classes[status] || 'bg-slate-100 text-slate-800';
        },

        getDispositionClass(disposition) {
            const classes = {
                'ANSWERED': 'bg-green-100 text-green-800',
                'NO ANSWER': 'bg-yellow-100 text-yellow-800',
                'BUSY': 'bg-orange-100 text-orange-800',
                'FAILED': 'bg-red-100 text-red-800',
                'CANCEL': 'bg-slate-100 text-slate-800',
            };
            return classes[disposition] || 'bg-slate-100 text-slate-800';
        },

        formatDate(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },

        formatDateTime(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        formatDuration(seconds) {
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            if (h > 0) return `${h}h ${m}m ${s}s`;
            if (m > 0) return `${m}m ${s}s`;
            return `${s}s`;
        },

        // 🚀 TIGA FUNGSI TAMBAHAN UNTUK STATUS PEMBAYARAN DAN MATA UANG 🚀
        formatCurrency(value) {
            if (!value) return '0';
            return new Intl.NumberFormat('id-ID').format(value);
        },

        formatPaymentStatus(status) {
            const labels = {
                'unpaid': 'Belum Bayar',
                'partial': 'Cicilan',
                'paid': 'Lunas'
            };
            return labels[status] || status;
        },

        getPaymentStatusClass(status) {
            const classes = {
                'unpaid': 'bg-red-50 text-red-700 border-red-200',
                'partial': 'bg-yellow-50 text-yellow-700 border-yellow-200',
                'paid': 'bg-green-50 text-green-700 border-green-200'
            };
            return classes[status] || 'bg-slate-100 text-slate-800 border-slate-200';
        }
    };
};
</script>

<script>
    // 🚀 Pastikan Alpine re-init setelah Turbo navigation
    document.addEventListener('turbo:load', () => {
        if (window.Alpine) {
            // Inisialisasi ulang elemen yang belum punya Alpine
            document.querySelectorAll('[x-data]').forEach(el => {
                if (!el.__x) {
                    window.Alpine.initTree(el);
                }
            });
        }
    });
</script>
@endsection