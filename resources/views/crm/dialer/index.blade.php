@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-6" x-data="dialerManager()">
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-phone-volume text-brand-600"></i> Auto-Dialer (PDS)
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Dial otomatis nomor bucket ke agent yang join rotation & standby (ratio per agent).</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-white border border-slate-200 text-slate-600" title="Agent yang sedang join rotation">
                <i class="fa-solid fa-rotate text-brand-600"></i>
                <span x-text="rotation.length"></span>&nbsp;Rotation
            </span>
            <button @click="openCreateModal()" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> Buat Job
            </button>
        </div>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-xs text-amber-800 flex items-start gap-2">
        <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
        <div>
            <strong>Cara kerja:</strong> kaki AGENT ditelepon duluan, kaki customer baru jalan setelah agent angkat — jadi tidak mungkin customer tersambung tanpa agent.
            Job hanya bisa START kalau minimal 1 agent join rotation. Pastikan daemon <code class="bg-amber-100 px-1 rounded font-mono">php artisan pds:work</code> jalan di server.
        </div>
    </div>

    <!-- Rotation -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Agent dalam Rotation</h2>
            <button @click="fetchAll()" class="text-slate-400 hover:text-brand-600 transition-colors" title="Refresh">
                <i class="fa-solid fa-rotate"></i>
            </button>
        </div>
        <div class="p-4">
            <template x-if="rotation.length === 0">
                <div class="text-center py-6 text-slate-400 text-sm border-2 border-dashed border-slate-200 rounded-xl">
                    <i class="fa-solid fa-user-slash text-2xl text-slate-300 mb-2 block"></i>
                    Belum ada agent join rotation. PDS tidak bisa dijalankan.
                </div>
            </template>
            <div class="flex flex-wrap gap-2" x-show="rotation.length > 0">
                <template x-for="m in rotation" :key="m.id">
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium border"
                        :class="(m.agent && m.agent.status === 'online') ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'">
                        <i class="fa-solid fa-headset"></i>
                        <span x-text="(m.agent ? m.agent.name : 'Ext ' + m.extension)"></span>
                        <span class="font-mono" x-text="'(' + m.extension + ')'"></span>
                        <span class="font-bold uppercase" x-text="m.agent ? m.agent.status : '?'"></span>
                    </span>
                </template>
            </div>
        </div>
    </div>

    <!-- Jobs -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Job</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Bucket / Kuota</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Ratio</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Progres</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <template x-for="job in jobs" :key="job.id">
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900" x-text="job.name"></div>
                                <div class="text-[11px] text-slate-400" x-text="'#' + job.id + (job.note ? ' • ' + job.note : '')"></div>
                            </td>
                            <td class="px-4 py-3">
                                <template x-for="(limit, bucket) in (job.buckets_config || {})" :key="bucket">
                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200 mr-1 mb-1" x-text="bucket + ': ' + limit"></span>
                                </template>
                            </td>
                            <td class="px-4 py-3 text-sm font-mono text-slate-700" x-text="'1:' + job.lines_per_agent"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium border" :class="statusClass(job.status)" x-text="statusLabel(job.status)"></span>
                            </td>
                            <td class="px-4 py-3 min-w-[180px]">
                                <template x-if="job.progress_data">
                                    <div>
                                        <div class="text-[11px] font-mono text-slate-600" x-text="progressText(job.progress_data)"></div>
                                        <div class="w-full h-1.5 bg-slate-200 rounded-full mt-1 overflow-hidden">
                                            <div class="h-full bg-brand-600 transition-all" :style="'width: ' + progressPct(job.progress_data) + '%'"></div>
                                        </div>
                                    </div>
                                </template>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <button @click="openDetail(job)" class="text-slate-600 hover:text-slate-800 p-1.5 rounded hover:bg-slate-100 transition-colors" title="Detail antrian">
                                        <i class="fa-solid fa-eye text-sm"></i>
                                    </button>
                                    <template x-if="['draft','paused','stopped'].includes(job.status)">
                                        <button @click="jobAction(job, 'start')" class="text-emerald-600 hover:text-emerald-800 p-1.5 rounded hover:bg-emerald-50 transition-colors" title="Start / Resume">
                                            <i class="fa-solid fa-play text-sm"></i>
                                        </button>
                                    </template>
                                    <template x-if="job.status === 'running'">
                                        <button @click="jobAction(job, 'pause')" class="text-amber-600 hover:text-amber-800 p-1.5 rounded hover:bg-amber-50 transition-colors" title="Pause">
                                            <i class="fa-solid fa-pause text-sm"></i>
                                        </button>
                                    </template>
                                    <template x-if="['running','paused'].includes(job.status)">
                                        <button @click="jobAction(job, 'stop')" class="text-orange-600 hover:text-orange-800 p-1.5 rounded hover:bg-orange-50 transition-colors" title="Stop">
                                            <i class="fa-solid fa-stop text-sm"></i>
                                        </button>
                                    </template>
                                    <template x-if="['completed','stopped'].includes(job.status)">
                                        <button @click="jobAction(job, 'repeat')" class="text-blue-600 hover:text-blue-800 p-1.5 rounded hover:bg-blue-50 transition-colors" title="Ulangi job (reset antrian ke draft)">
                                            <i class="fa-solid fa-rotate-right text-sm"></i>
                                        </button>
                                    </template>
                                    <template x-if="job.status !== 'running'">
                                        <button @click="deleteJob(job.id)" class="text-red-600 hover:text-red-800 p-1.5 rounded hover:bg-red-50 transition-colors" title="Delete">
                                            <i class="fa-solid fa-trash text-sm"></i>
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="jobs.length === 0">
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-slate-500">
                                <i class="fa-solid fa-phone-volume text-3xl mb-2 block text-slate-300"></i>
                                Belum ada dial job. Buat job pertama dari bucket aging.
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

<!-- Modal Create -->
<div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="closeModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Buat Dial Job</h3>
                <button @click="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <form @submit.prevent="submitForm()" class="p-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nama Job <span class="text-red-500">*</span></label>
                    <input type="text" x-model="form.name" required placeholder="cth: Pagi - Bucket 1+2" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kuota nomor per bucket <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="b in allBuckets" :key="b">
                            <label class="flex items-center gap-2 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                <span class="flex-1 text-slate-700" x-text="b"></span>
                                <input type="number" min="0" max="5000" x-model.number="form.buckets[b]" class="w-20 border border-slate-300 rounded-lg px-2 py-1 text-sm text-right focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                            </label>
                        </template>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">cth: Bucket 1 = 10, Bucket 2 = 20. Diambil dari bucket (belum lunas, belum handover).</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ratio per agent <span class="text-red-500">*</span></label>
                        <select x-model.number="form.lines_per_agent" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                            <option :value="1">1 : 1</option>
                            <option :value="2">1 : 2</option>
                            <option :value="3">1 : 3</option>
                            <option :value="4">1 : 4</option>
                            <option :value="5">1 : 5</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Max attempt</label>
                        <input type="number" min="1" max="10" x-model.number="form.max_attempts" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Catatan</label>
                    <input type="text" x-model="form.note" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                    <button type="button" @click="closeModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                    <button type="submit" :disabled="submitting" class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm hover:bg-brand-700 transition-colors disabled:opacity-50">
                        <span x-show="!submitting">Buat & Bangun Antrian</span>
                        <span x-show="submitting" class="flex items-center gap-2"><i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail -->
<div x-show="showDetail" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="closeDetail()"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[85vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between p-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Antrian — <span x-text="detailJob?.name"></span></h3>
                <button @click="closeDetail()" class="text-slate-400 hover:text-slate-600 transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <template x-if="detailLoading">
                    <div class="flex items-center justify-center py-12"><i class="fa-solid fa-spinner fa-spin text-2xl text-brand-500"></i></div>
                </template>
                <template x-if="!detailLoading && detailItems.length === 0">
                    <div class="text-center py-8 text-slate-400 text-sm">Antrian kosong.</div>
                </template>
                <div class="overflow-x-auto" x-show="!detailLoading && detailItems.length > 0">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Customer</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Nomor</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Bucket</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Attempt</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Agent</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <template x-for="item in detailItems" :key="item.id">
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2" x-text="item.customer ? item.customer.name : '-'"></td>
                                    <td class="px-3 py-2 font-mono" x-text="item.phone"></td>
                                    <td class="px-3 py-2" x-text="item.bucket || '-'"></td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium border" :class="itemStatusClass(item.status)" x-text="item.status"></span>
                                    </td>
                                    <td class="px-3 py-2 font-mono" x-text="item.attempts"></td>
                                    <td class="px-3 py-2 font-mono" x-text="item.agent_extension || '-'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@php
    $dialPaginationData = [
        'current_page' => $jobs->currentPage(),
        'last_page' => $jobs->lastPage(),
        'per_page' => $jobs->perPage(),
        'total' => $jobs->total(),
        'from' => $jobs->firstItem(),
        'to' => $jobs->lastItem(),
        'prev_page_url' => $jobs->previousPageUrl(),
        'next_page_url' => $jobs->nextPageUrl(),
    ];
@endphp

@section('scripts')
<script>
window.dialerManager = function() {
    return {
        jobs: @json($jobs->items()),
        pagination: @json($dialPaginationData),
        rotation: [],
        allBuckets: @json($buckets),
        showModal: false,
        submitting: false,
        form: { name: '', buckets: {}, lines_per_agent: 2, max_attempts: 3, note: '' },
        showDetail: false,
        detailJob: null,
        detailItems: [],
        detailLoading: false,
        pollTimer: null,

        init() {
            this.fetchAll();
            this.pollTimer = setInterval(() => { this.fetchAll(true); }, 5000);
        },

        destroy() {
            if (this.pollTimer) clearInterval(this.pollTimer);
        },

        async fetchAll(silent = false) {
            try {
                const [jobsRes, rotRes] = await Promise.all([
                    fetch(`{{ route('crm.dialer.index') }}?page=${this.pagination.current_page || 1}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    }),
                    fetch(`{{ route('crm.dialer.rotation') }}`, {
                        headers: { 'Accept': 'application/json' }
                    }),
                ]);
                const jobsData = await jobsRes.json();
                this.jobs = jobsData.data;
                this.pagination = {
                    current_page: jobsData.current_page,
                    last_page: jobsData.last_page,
                    per_page: jobsData.per_page,
                    total: jobsData.total,
                    from: jobsData.from,
                    to: jobsData.to,
                    prev_page_url: jobsData.prev_page_url,
                    next_page_url: jobsData.next_page_url,
                };
                const rotData = await rotRes.json();
                if (rotData.status === 'success') this.rotation = rotData.data;
            } catch (e) {
                if (!silent) console.error(e);
            }
        },

        prevPage() { if (this.pagination.prev_page_url) { this.pagination.current_page--; this.fetchAll(); } },
        nextPage() { if (this.pagination.next_page_url) { this.pagination.current_page++; this.fetchAll(); } },

        openCreateModal() {
            const buckets = {};
            this.allBuckets.forEach(b => { buckets[b] = 0; });
            this.form = { name: '', buckets: buckets, lines_per_agent: 2, max_attempts: 3, note: '' };
            this.showModal = true;
        },

        closeModal() { this.showModal = false; },

        async submitForm() {
            if (!this.form.name.trim()) { alert('Nama job wajib diisi'); return; }
            this.submitting = true;
            try {
                const response = await fetch(`{{ route('crm.dialer.store') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        name: this.form.name,
                        buckets: this.form.buckets,
                        lines_per_agent: this.form.lines_per_agent,
                        max_attempts: this.form.max_attempts,
                        note: this.form.note || null,
                    })
                });
                const data = await response.json();
                if (response.ok && data.status === 'success') {
                    this.closeModal();
                    this.fetchAll();
                    alert(data.message);
                } else {
                    alert(data.message || 'Gagal membuat job');
                }
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan jaringan/server.');
            } finally {
                this.submitting = false;
            }
        },

        async jobAction(job, action) {
            const labels = { start: 'START', pause: 'PAUSE', stop: 'STOP', repeat: 'ULANGI' };
            if (!confirm(`${labels[action]} job "${job.name}"?`)) return;
            try {
                const response = await fetch(`{{ url('/dashboard/crm/dialer/jobs') }}/${job.id}/${action}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                });
                const data = await response.json();
                alert(data.message || (data.status === 'success' ? 'OK' : 'Gagal'));
                this.fetchAll();
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan jaringan/server.');
            }
        },

        async deleteJob(id) {
            if (!confirm('Hapus job ini beserta seluruh antriannya?')) return;
            try {
                const response = await fetch(`{{ url('/dashboard/crm/dialer/jobs') }}/${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ _method: 'DELETE' })
                });
                const data = await response.json();
                alert(data.message || (data.status === 'success' ? 'OK' : 'Gagal'));
                this.fetchAll();
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan jaringan/server.');
            }
        },

        async openDetail(job) {
            this.detailJob = job;
            this.detailItems = [];
            this.detailLoading = true;
            this.showDetail = true;
            try {
                const response = await fetch(`{{ url('/dashboard/crm/dialer/jobs') }}/${job.id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (data.status === 'success') {
                    this.detailItems = data.items.data || [];
                    if (data.progress && job.progress_data) job.progress_data = data.progress;
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.detailLoading = false;
            }
        },

        closeDetail() {
            this.showDetail = false;
            this.detailJob = null;
            this.detailItems = [];
        },

        statusLabel(s) {
            return { draft: 'Draft', running: 'Running', paused: 'Paused', completed: 'Completed', stopped: 'Stopped' }[s] || s;
        },

        statusClass(s) {
            return {
                draft: 'bg-slate-100 text-slate-600 border-slate-200',
                running: 'bg-emerald-100 text-emerald-700 border-emerald-200',
                paused: 'bg-amber-100 text-amber-700 border-amber-200',
                completed: 'bg-blue-100 text-blue-700 border-blue-200',
                stopped: 'bg-red-100 text-red-700 border-red-200',
            }[s] || 'bg-slate-100 text-slate-600 border-slate-200';
        },

        progressText(p) {
            return `Q:${p.queued} • D:${p.dialing} • OK:${p.done} • X:${p.failed + p.skipped}`;
        },

        progressPct(p) {
            if (!p.total) return 0;
            return Math.round(((p.done + p.failed + p.skipped) / p.total) * 100);
        },

        itemStatusClass(s) {
            return {
                queued: 'bg-slate-100 text-slate-600 border-slate-200',
                dialing: 'bg-amber-100 text-amber-700 border-amber-200',
                done: 'bg-emerald-100 text-emerald-700 border-emerald-200',
                failed: 'bg-red-100 text-red-700 border-red-200',
                skipped: 'bg-slate-100 text-slate-400 border-slate-200',
            }[s] || 'bg-slate-100 text-slate-600 border-slate-200';
        },
    };
};
</script>
@endsection
