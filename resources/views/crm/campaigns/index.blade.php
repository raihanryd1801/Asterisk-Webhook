@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-6" x-data="campaignManager()">
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-bullseye text-brand-600"></i> Campaign Management
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Kelola strategi koleksi per bucket & tipe.</p>
        </div>
        <button 
            @click="openCreateModal()"
            class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2"
        >
            <i class="fa-solid fa-plus"></i> Tambah Campaign
        </button>
    </div>

    <!-- Filter -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col sm:flex-row gap-4">
        <select 
            x-model="typeFilter" 
            @change="fetchCampaigns()"
            class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"
        >
            <option value="">Semua Tipe</option>
            <template x-for="type in types" :key="type">
                <option :value="type" x-text="formatType(type)"></option>
            </template>
        </select>
        <select 
            x-model="statusFilter" 
            @change="fetchCampaigns()"
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
                @keyup.enter="fetchCampaigns()"
                placeholder="Cari nama/code campaign..."
                class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"
            >
        </div>
    </div>

    <!-- Campaign List -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Campaign</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Tipe</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Target Bucket</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Channels</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Schedule</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Period</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200" x-ref="tbody">
                    <template x-for="campaign in campaigns" :key="campaign.id">
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900" x-text="campaign.name"></div>
                                <div class="text-sm text-slate-500 font-mono" x-text="campaign.code"></div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium border"
                                    :class="getTypeClass(campaign.type)"
                                    x-text="formatType(campaign.type)"></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <template x-if="campaign.target_buckets && campaign.target_buckets.length > 0">
                                    <template x-for="bucket in campaign.target_buckets" :key="bucket">
                                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium mr-1 mb-1 border"
                                            :class="getBucketClass(bucket)"
                                            x-text="bucket"></span>
                                    </template>
                                </template>
                                <template x-if="!campaign.target_buckets || campaign.target_buckets.length === 0">
                                    <span class="text-slate-400 text-xs">-</span>
                                </template>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <template x-if="campaign.channels && campaign.channels.length > 0">
                                    <template x-for="ch in campaign.channels" :key="ch">
                                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-700 mr-1 mb-1" x-text="ch.toUpperCase()"></span>
                                    </template>
                                </template>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <div class="flex items-center gap-1.5 text-[11px]">
                                    <i class="fa-solid fa-clock text-slate-400"></i>
                                    <span x-text="campaign.start_time + ' - ' + campaign.end_time"></span>
                                </div>
                                <div class="flex items-center gap-1.5 text-[11px] text-slate-400">
                                    <i class="fa-solid fa-calendar-days"></i>
                                    <span x-text="formatScheduleDays(campaign.schedule_days)"></span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="campaign.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                    x-text="campaign.is_active ? 'Aktif' : 'Non-Aktif'"></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-500">
                                <template x-if="campaign.start_date || campaign.end_date">
                                    <div x-text="campaign.start_date ? formatDate(campaign.start_date) : '---'"></div>
                                    <div class="text-[10px]">s/d</div>
                                    <div x-text="campaign.end_date ? formatDate(campaign.end_date) : '---'"></div>
                                </template>
                                <template x-if="!campaign.start_date && !campaign.end_date">
                                    <span class="text-slate-400">Always</span>
                                </template>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <button @click="autoAssignOne(campaign)" class="text-indigo-600 hover:text-indigo-800 p-1.5 rounded hover:bg-indigo-50 transition-colors" title="Auto-assign case sesuai bucket ke campaign ini">
                                        <i class="fa-solid fa-wand-magic-sparkles text-sm"></i>
                                    </button>
                                    <button @click="openBlastModal(campaign)" class="text-emerald-600 hover:text-emerald-800 p-1.5 rounded hover:bg-emerald-50 transition-colors" title="Blast WA/SMS">
                                        <i class="fa-solid fa-paper-plane text-sm"></i>
                                    </button>
                                    <button @click="openEditModal(campaign)" class="text-brand-600 hover:text-brand-800 p-1.5 rounded hover:bg-brand-50 transition-colors" title="Edit">
                                        <i class="fa-solid fa-pen text-sm"></i>
                                    </button>
                                    <button @click="deleteCampaign(campaign.id)" class="text-red-600 hover:text-red-800 p-1.5 rounded hover:bg-red-50 transition-colors" title="Delete">
                                        <i class="fa-solid fa-trash text-sm"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="campaigns.length === 0">
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-slate-500">
                                <i class="fa-solid fa-bullseye text-3xl mb-2 block text-slate-300"></i>
                                Belum ada campaign
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
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900" x-text="modalTitle"></h3>
                <button @click="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <form @submit.prevent="submitForm()" class="p-4 space-y-4">
                <input type="hidden" name="id" x-model="form.id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Campaign <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="form.name" required class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Code <span class="text-red-500">*</span></label>
                        <input type="text" name="code" x-model="form.code" required class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="EARLY-001">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tipe <span class="text-red-500">*</span></label>
                        <select name="type" x-model="form.type" required class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                            <template x-for="type in types" :key="type">
                                <option :value="type" x-text="formatType(type)"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Max Attempts/Day</label>
                        <input type="number" name="max_attempts_per_day" x-model="form.max_attempts_per_day" min="1" max="10" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" value="3">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Start Time</label>
                        <input type="time" name="start_time" x-model="form.start_time" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" value="08:00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">End Time</label>
                        <input type="time" name="end_time" x-model="form.end_time" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" value="17:00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" x-model="form.start_date" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">End Date</label>
                        <input type="date" name="end_date" x-model="form.end_date" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Target Buckets</label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="bucket in allBuckets" :key="bucket">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" :value="bucket" x-model="form.target_buckets" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span class="text-sm" x-text="bucket"></span>
                            </label>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Channels</label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="ch in allChannels" :key="ch">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" :value="ch" x-model="form.channels" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span class="text-sm uppercase" x-text="ch"></span>
                            </label>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Schedule Days</label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="day in allDays" :key="day.value">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" :value="day.value" x-model="form.schedule_days" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span class="text-sm" x-text="day.label"></span>
                            </label>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Script Template</label>
                    <textarea name="script_template" x-model="form.script_template" rows="3" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="Script pembicaraan untuk collector..."></textarea>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" x-model="form.is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="1">
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

<!-- Modal Blast WA/SMS -->
<div x-show="showBlastModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="closeBlastModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Blast <span x-text="blastCampaign?.name"></span></h3>
                <button @click="closeBlastModal()" class="text-slate-400 hover:text-slate-600 transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <form @submit.prevent="submitBlast()" class="p-4 space-y-4">
                <p class="text-xs text-slate-500">Target: case di campaign ini dengan status <b>unpaid/partial</b>. Tercatat sebagai log lokal — hubungkan gateway WA/SMS untuk kirim nyata.</p>
                <div class="text-sm font-medium text-slate-700">Estimasi target: <span class="font-bold" x-text="blastTargets"></span> nomor</div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Channel</label>
                    <select x-model="blastChannel" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                        <option value="wa">WhatsApp</option>
                        <option value="sms">SMS</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Pesan</label>
                    <textarea x-model="blastMessage" rows="4" required maxlength="1000" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="Yth Bpk/Ibu {nama}, tagihan Rp{tagihan} jatuh tempo {due_date}..."></textarea>
                    <p class="text-[11px] text-slate-400 mt-1">Gunakan nama campaign & nominal — personalisasi otomatis belum aktif, pesan terkirim apa adanya.</p>
                </div>
                <template x-if="blastHistory.length > 0">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase mb-2">Riwayat blast campaign ini</p>
                        <template x-for="h in blastHistory" :key="h.id">
                            <div class="text-xs border border-slate-200 rounded-lg p-2 mb-1 flex justify-between gap-2">
                                <span class="uppercase font-bold" x-text="h.channel"></span>
                                <span x-text="h.sent + '/' + h.total_target + ' target'"></span>
                                <span class="text-slate-400" x-text="h.created_at"></span>
                            </div>
                        </template>
                    </div>
                </template>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                    <button type="button" @click="closeBlastModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                    <button type="submit" :disabled="blastLoading" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors disabled:opacity-50">
                        <span x-show="!blastLoading">Catat Blast</span>
                        <span x-show="blastLoading" class="flex items-center gap-2"><i class="fa-solid fa-spinner fa-spin"></i> Memproses...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
@endsection

@php
    $campaignPaginationData = [
        'current_page' => $campaigns->currentPage(),
        'last_page' => $campaigns->lastPage(),
        'per_page' => $campaigns->perPage(),
        'total' => $campaigns->total(),
        'from' => $campaigns->firstItem(),
        'to' => $campaigns->lastItem(),
        'prev_page_url' => $campaigns->previousPageUrl(),
        'next_page_url' => $campaigns->nextPageUrl(),
    ];
@endphp

@section('scripts')
<script>
window.campaignManager = function() {
    return {
        campaigns: @json($campaigns->items()),
        pagination: @json($campaignPaginationData),
        types: @json($types),
        search: '',
        typeFilter: '',
        statusFilter: '',
        showModal: false,
        modalTitle: '',
        form: { 
            id: '', name: '', code: '', description: '', type: 'early', 
            target_buckets: [], channels: ['call', 'sms'], 
            max_attempts_per_day: 3, start_time: '08:00', end_time: '17:00', 
            schedule_days: [1,2,3,4,5], script_template: '', is_active: true,
            start_date: '', end_date: ''
        },
        submitting: false,
        showBlastModal: false,
        blastCampaign: null,
        blastTargets: 0,
        blastHistory: [],
        blastChannel: 'wa',
        blastMessage: '',
        blastLoading: false,
        allBuckets: ['Bucket 1', 'Bucket 2', 'Bucket 3', 'NPL'],
        allChannels: ['call', 'sms', 'visit', 'email', 'wa'],
        allDays: [
            {value: 1, label: 'Sen'}, {value: 2, label: 'Sel'}, {value: 3, label: 'Rab'},
            {value: 4, label: 'Kam'}, {value: 5, label: 'Jum'}, {value: 6, label: 'Sab'}, {value: 7, label: 'Min'}
        ],

        async fetchCampaigns(page = 1) {
            const params = new URLSearchParams();
            if (this.search) params.append('search', this.search);
            if (this.typeFilter) params.append('type', this.typeFilter);
            if (this.statusFilter !== '') params.append('is_active', this.statusFilter);
            params.append('page', page);
            
            try {
                const response = await fetch(`{{ route('crm.campaigns.index') }}?${params}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const data = await response.json();
                this.campaigns = data.data;
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

        prevPage() { if (this.pagination.prev_page_url) this.fetchCampaigns(this.pagination.current_page - 1); },
        nextPage() { if (this.pagination.next_page_url) this.fetchCampaigns(this.pagination.current_page + 1); },

        openCreateModal() {
            this.modalTitle = 'Tambah Campaign';
            this.form = { id: '', name: '', code: '', description: '', type: 'early', target_buckets: [], channels: ['call', 'sms'], max_attempts_per_day: 3, start_time: '08:00', end_time: '17:00', schedule_days: [1,2,3,4,5], script_template: '', is_active: true, start_date: '', end_date: '' };
            this.showModal = true;
        },

        openEditModal(campaign) {
            this.modalTitle = 'Edit Campaign';
            this.form = {
                id: campaign.id,
                name: campaign.name,
                code: campaign.code,
                description: campaign.description || '',
                type: campaign.type,
                target_buckets: campaign.target_buckets || [],
                channels: campaign.channels || ['call', 'sms'],
                max_attempts_per_day: campaign.max_attempts_per_day || 3,
                start_time: campaign.start_time ? campaign.start_time.substring(0,5) : '08:00',
                end_time: campaign.end_time ? campaign.end_time.substring(0,5) : '17:00',
                schedule_days: campaign.schedule_days || [1,2,3,4,5],
                script_template: campaign.script_template || '',
                is_active: campaign.is_active,
                start_date: campaign.start_date ? campaign.start_date.split('T')[0] : '',
                end_date: campaign.end_date ? campaign.end_date.split('T')[0] : '',
            };
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            this.form = { id: '', name: '', code: '', description: '', type: 'early', target_buckets: [], channels: ['call', 'sms'], max_attempts_per_day: 3, start_time: '08:00', end_time: '17:00', schedule_days: [1,2,3,4,5], script_template: '', is_active: true, start_date: '', end_date: '' };
        },

        async submitForm() {
            this.submitting = true;
            const isEdit = !!this.form.id;
            
            // Format URL Template Literal yang aman dari error PHP
            const baseUrl = `{{ route('crm.campaigns.index') }}`;
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
                
                if (response.ok && (data.status === 'success' || data.success)) {
                    this.closeModal();
                    this.fetchCampaigns(this.pagination.current_page);
                    alert(data.message || 'Campaign berhasil disimpan!');
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

        async deleteCampaign(id) {
            if (!confirm('Yakin ingin menghapus campaign ini?')) return;
            try {
                const response = await fetch(`{{ route('crm.campaigns.index') }}/${id}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ _method: 'DELETE' })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    this.fetchCampaigns(this.pagination.current_page);
                } else {
                    alert(data.message || 'Error');
                }
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            }
        },

        async autoAssignOne(campaign) {
            if (!confirm(`Auto-assign case bucket [${(campaign.target_buckets || []).join(', ') || '-'}] ke ${campaign.name}? Collector kosong dibagi rata.`)) return;
            try {
                const response = await fetch(`{{ url('/dashboard/crm/collection/auto-assign') }}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ campaign_id: campaign.id, only_unassigned: true, distribute_collectors: true })
                });
                const data = await response.json();
                alert(data.message || 'Selesai');
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            }
        },

        async openBlastModal(campaign) {
            this.blastCampaign = campaign;
            this.blastChannel = 'wa';
            this.blastMessage = '';
            this.blastTargets = 0;
            this.blastHistory = [];
            this.showBlastModal = true;
            try {
                const response = await fetch(`{{ route('crm.campaigns.index') }}/${campaign.id}/blast`, { headers: { 'Accept': 'application/json' } });
                const data = await response.json();
                if (data.status === 'success') {
                    this.blastTargets = data.targets;
                    this.blastHistory = data.history || [];
                }
            } catch (e) {
                console.error(e);
            }
        },

        closeBlastModal() {
            this.showBlastModal = false;
            this.blastCampaign = null;
        },

        async submitBlast() {
            if (!this.blastCampaign || !this.blastMessage.trim()) {
                alert('Isi pesan dulu');
                return;
            }
            this.blastLoading = true;
            try {
                const response = await fetch(`{{ route('crm.campaigns.index') }}/${this.blastCampaign.id}/blast`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ channel: this.blastChannel, message: this.blastMessage })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    alert(data.message);
                    this.closeBlastModal();
                } else {
                    alert(data.message || 'Error');
                }
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            } finally {
                this.blastLoading = false;
            }
        },

        formatType(type) {
            const labels = { 'early': 'Early Collection', 'late': 'Late Collection', 'legal': 'Legal', 'recovery': 'Recovery' };
            return labels[type] || type;
        },

        getTypeClass(type) {
            const classes = { 'early': 'bg-blue-50 text-blue-700 border-blue-200', 'late': 'bg-amber-50 text-amber-700 border-amber-200', 'legal': 'bg-purple-50 text-purple-700 border-purple-200', 'recovery': 'bg-red-50 text-red-700 border-red-200' };
            return classes[type] || 'bg-slate-50 text-slate-700 border-slate-200';
        },

        getBucketClass(bucket) {
            const classes = { 'Bucket 1': 'bg-green-50 text-green-700 border-green-200', 'Bucket 2': 'bg-yellow-50 text-yellow-700 border-yellow-200', 'Bucket 3': 'bg-orange-50 text-orange-700 border-orange-200', 'NPL': 'bg-red-50 text-red-700 border-red-200' };
            return classes[bucket] || 'bg-slate-50 text-slate-700 border-slate-200';
        },

        formatScheduleDays(days) {
            const labels = { 1: 'Sen', 2: 'Sel', 3: 'Rab', 4: 'Kam', 5: 'Jum', 6: 'Sab', 7: 'Min' };
            if (!days || !days.length) return 'Mon-Fri';
            return days.map(d => labels[d]).join(', ');
        },

        formatDate(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        }
    };
};
</script>
@endsection