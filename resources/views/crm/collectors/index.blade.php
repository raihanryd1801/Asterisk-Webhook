@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-6" x-data="collectorManager()">
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-user-shield text-brand-600"></i> Debt Collector
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Kelola tim penagih lapangan & desk (terpisah dari agent call center).</p>
        </div>
        <button
            @click="openCreateModal()"
            class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2"
        >
            <i class="fa-solid fa-plus"></i> Tambah Collector
        </button>
    </div>

    <!-- Filter -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col sm:flex-row gap-4">
        <select
            x-model="typeFilter"
            @change="fetchCollectors()"
            class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"
        >
            <option value="">Semua Tipe</option>
            <option value="field">Lapangan</option>
            <option value="desk">Desk (Telepon)</option>
        </select>
        <select
            x-model="statusFilter"
            @change="fetchCollectors()"
            class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"
        >
            <option value="">Semua Status</option>
            <option value="1">Aktif</option>
            <option value="0">Non-Aktif</option>
        </select>
        <div class="flex-1 max-w-md">
            <input
                type="text"
                x-model="search"
                @keyup.enter="fetchCollectors()"
                placeholder="Cari nama, telepon, area..."
                class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"
            >
        </div>
    </div>

    <!-- Tabel -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Collector</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Tipe</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Area</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Case Aktif</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <template x-for="collector in collectors" :key="collector.id">
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900" x-text="collector.name"></div>
                                <div class="text-xs text-slate-500 font-mono" x-text="collector.phone || '-'"></div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                    :class="collector.type === 'field' ? 'bg-orange-50 text-orange-700 border-orange-200' : 'bg-blue-50 text-blue-700 border-blue-200'"
                                    x-text="collector.type === 'field' ? 'Lapangan' : 'Desk (Telepon)'"></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600" x-text="collector.area || '-'"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700" x-text="collector.active_cases ?? 0"></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="collector.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                    x-text="collector.is_active ? 'Aktif' : 'Non-Aktif'"></span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <button @click="openEditModal(collector)" class="text-brand-600 hover:text-brand-800 p-1.5 rounded hover:bg-brand-50 transition-colors" title="Edit">
                                        <i class="fa-solid fa-pen text-sm"></i>
                                    </button>
                                    <button @click="deleteCollector(collector.id)" class="text-red-600 hover:text-red-800 p-1.5 rounded hover:bg-red-50 transition-colors" title="Delete">
                                        <i class="fa-solid fa-trash text-sm"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="collectors.length === 0">
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-slate-500">
                                <i class="fa-solid fa-user-shield text-3xl mb-2 block text-slate-300"></i>
                                Belum ada debt collector
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
                <button @click="prevPage()" :disabled="!pagination.prev_page_url" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50 transition-colors">Previous</button>
                <button @click="nextPage()" :disabled="!pagination.next_page_url" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50 transition-colors">Next</button>
            </div>
        </div>
    </div>

<!-- Modal Create/Edit -->
<div x-show="showModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" @click.outside="closeModal()" style="display: none;" x-cloak>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="closeModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900" x-text="modalTitle"></h3>
                <button @click="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <form @submit.prevent="submitForm()" class="p-4 space-y-4">
                <input type="hidden" x-model="form.id">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nama Collector <span class="text-red-500">*</span></label>
                    <input type="text" x-model="form.name" required placeholder="cth: Budi Lapangan" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Telepon</label>
                        <input type="text" x-model="form.phone" placeholder="08xxxxxxxxxx" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tipe <span class="text-red-500">*</span></label>
                        <select x-model="form.type" required class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                            <option value="field">Lapangan</option>
                            <option value="desk">Desk (Telepon)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Area / Wilayah</label>
                    <input type="text" x-model="form.area" placeholder="cth: Jakarta Timur" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Catatan</label>
                    <textarea x-model="form.notes" rows="2" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent"></textarea>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" x-model="form.is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <label class="text-sm font-medium text-slate-700">Aktif</label>
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
</div>
@endsection

@php
    $collectorPaginationData = [
        'current_page' => $collectors->currentPage(),
        'last_page' => $collectors->lastPage(),
        'per_page' => $collectors->perPage(),
        'total' => $collectors->total(),
        'from' => $collectors->firstItem(),
        'to' => $collectors->lastItem(),
        'prev_page_url' => $collectors->previousPageUrl(),
        'next_page_url' => $collectors->nextPageUrl(),
    ];
@endphp

@section('scripts')
<script>
window.collectorManager = function() {
    return {
        collectors: @json($collectors->items()),
        pagination: @json($collectorPaginationData),
        search: '',
        typeFilter: '',
        statusFilter: '',
        showModal: false,
        modalTitle: '',
        form: { id: '', name: '', phone: '', type: 'field', area: '', notes: '', is_active: true },
        submitting: false,

        async fetchCollectors(page = 1) {
            const params = new URLSearchParams();
            if (this.search) params.append('search', this.search);
            if (this.typeFilter) params.append('type', this.typeFilter);
            if (this.statusFilter !== '') params.append('is_active', this.statusFilter);
            params.append('page', page);

            try {
                const response = await fetch(`{{ route('crm.collectors.index') }}?${params}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const data = await response.json();
                this.collectors = data.data;
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

        prevPage() { if (this.pagination.prev_page_url) this.fetchCollectors(this.pagination.current_page - 1); },
        nextPage() { if (this.pagination.next_page_url) this.fetchCollectors(this.pagination.current_page + 1); },

        blankForm() {
            return { id: '', name: '', phone: '', type: 'field', area: '', notes: '', is_active: true };
        },

        openCreateModal() {
            this.modalTitle = 'Tambah Debt Collector';
            this.form = this.blankForm();
            this.showModal = true;
        },

        openEditModal(collector) {
            this.modalTitle = 'Edit Debt Collector';
            this.form = {
                id: collector.id,
                name: collector.name,
                phone: collector.phone || '',
                type: collector.type || 'field',
                area: collector.area || '',
                notes: collector.notes || '',
                is_active: !!collector.is_active,
            };
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            this.form = this.blankForm();
        },

        async submitForm() {
            this.submitting = true;
            const isEdit = !!this.form.id;
            const baseUrl = `{{ route('crm.collectors.index') }}`;
            const url = isEdit ? `${baseUrl}/${this.form.id}` : baseUrl;
            const method = isEdit ? 'PUT' : 'POST';

            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(this.form)
                });

                const data = await response.json();

                if (response.ok && data.status === 'success') {
                    this.closeModal();
                    this.fetchCollectors(this.pagination.current_page);
                } else {
                    let errorMsg = data.message || 'Terjadi kesalahan validasi.';
                    if (data.errors) {
                        errorMsg += '\n' + Object.values(data.errors).flat().join('\n');
                    }
                    alert(errorMsg);
                }
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan pada jaringan atau server.');
            } finally {
                this.submitting = false;
            }
        },

        async deleteCollector(id) {
            if (!confirm('Yakin ingin menghapus collector ini? Case terkait jadi Tanpa Collector.')) return;
            try {
                const response = await fetch(`{{ route('crm.collectors.index') }}/${id}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ _method: 'DELETE' })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    this.fetchCollectors(this.pagination.current_page);
                } else {
                    alert(data.message || 'Error');
                }
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            }
        },
    };
};
</script>
@endsection
