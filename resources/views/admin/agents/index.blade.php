@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="agentManagement()">
    
    <!-- Header Section -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-users-gear text-brand-600"></i> Agent & Supervisor Management
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Kelola data agen, supervisor, ekstensi, dan grup antrian sistem.</p>
        </div>
        <button @click="openCreateModal()" class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2.5 rounded-xl font-medium text-sm shadow-sm transition flex items-center gap-2">
            <i class="fa-solid fa-plus text-xs"></i> Tambah Pengguna Baru
        </button>
    </div>

    <!-- Tabel Data -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50/50 border-b border-slate-100">
                    <tr class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                        <th class="px-6 py-4">Nama</th>
                        <th class="px-6 py-4">Extension</th>
                        <th class="px-6 py-4">Jabatan</th>
                        <th class="px-6 py-4">Supervisor (Tim)</th> <!-- 🚀 Kolom disesuaikan -->
                        <th class="px-6 py-4">Status App</th>
                        <th class="px-6 py-4">Akses Panggilan</th>
                        <th class="px-6 py-4">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @foreach($agents as $agent)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="px-6 py-4 font-semibold text-slate-800">{{ $agent->name }}</td>
                        <td class="px-6 py-4 font-mono text-slate-600 font-medium">{{ $agent->extension }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ ($agent->role ?? 'agent') === 'supervisor' ? 'bg-purple-50 text-purple-600 border-purple-100' : 'bg-brand-50 text-brand-600 border-brand-100' }}">
                                {{ $agent->role ?? 'Agent' }}
                            </span>
                        </td>
                        
                        <!-- 🚀 TAMPILKAN BANYAK SUPERVISOR DI TABEL -->
                        <td class="px-6 py-4 text-slate-600 text-xs">
                            @if($agent->supervisors->isNotEmpty())
                                <div class="flex flex-col gap-0.5">
                                    @foreach($agent->supervisors as $spv)
                                        <span class="font-medium text-slate-700">• {{ $spv->name }} <span class="text-slate-400">({{ $spv->extension }})</span></span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-slate-400">— Tanpa SPV —</span>
                            @endif
                        </td>

                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-bold rounded-full border uppercase {{ $agent->status === 'online' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-slate-50 text-slate-500 border-slate-100' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $agent->status === 'online' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                {{ $agent->status }}
                            </span>
                        </td>
                        
                        <td class="px-6 py-4">
                            @if(($agent->context ?? 'from-internal') === 'blokir-total')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-bold rounded-full border bg-red-50 text-red-600 border-red-100 uppercase">
                                    <i class="fa-solid fa-phone-slash text-[9px]"></i> Disabled
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-bold rounded-full border bg-emerald-50 text-emerald-600 border-emerald-100 uppercase">
                                    <i class="fa-solid fa-phone text-[9px]"></i> Active
                                </span>
                            @endif
                        </td>

                        <td class="px-6 py-4 flex items-center gap-3">
                            <button @click="openEditModal({{ json_encode($agent->load('supervisors')) }})" class="text-brand-600 hover:text-brand-700 font-medium text-xs transition">Edit</button>
                            
                            <button @click="deleteAgent({{ $agent->id }})" 
                                    :disabled="deletingId === {{ $agent->id }}" 
                                    class="text-red-500 hover:text-red-600 font-medium text-xs transition disabled:opacity-50 flex items-center gap-1.5">
                                <i class="fa-solid fa-spinner fa-spin text-[10px]" x-show="deletingId === {{ $agent->id }}" x-cloak></i>
                                <span x-text="deletingId === {{ $agent->id }} ? 'Menghapus...' : 'Delete'"></span>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL FORM -->
    <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" style="display: none;" x-cloak>
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md max-h-[90vh] overflow-y-auto" @click.outside="openModal = false">
            <h3 class="text-lg font-bold text-slate-800 mb-6" x-text="isEdit ? 'Edit Data Pengguna' : 'Tambah Pengguna Baru'"></h3>
            
            <form @submit.prevent="submitForm()" class="space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Nama Lengkap</label>
                    <input type="text" x-model="form.name" required placeholder="Contoh: Budi Santoso" class="w-full border border-slate-200 p-2.5 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Nomor Ekstensi</label>
                    <input type="text" x-model="form.extension" required placeholder="Contoh: 105" :readonly="isEdit" class="w-full border border-slate-200 p-2.5 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50 font-mono">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Jabatan / Role</label>
                    <select x-model="form.role" class="w-full border border-slate-200 p-2.5 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50" required>
                        <option value="agent">Agent (CS)</option>
                        <option value="supervisor">Supervisor</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Password SIP (Secret)</label>
                    <div class="relative">
                        <input :type="showSecret ? 'text' : 'password'" x-model="form.secret" placeholder="Kosongkan untuk generate otomatis" class="w-full border border-slate-200 p-2.5 pr-10 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50 font-mono">
                        <button @click="showSecret = !showSecret" type="button" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer">
                            <i :class="showSecret ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'" class="pointer-events-none text-sm"></i>
                        </button>
                    </div>
                </div>

                <!-- AKSES PANGGILAN -->
                <div x-show="isEdit">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Akses Panggilan (FreePBX)</label>
                    <div class="flex p-1 bg-slate-100 rounded-xl border border-slate-200">
                        <button type="button" 
                                @click="form.context = 'from-internal'"
                                class="flex-1 py-2 rounded-lg text-xs font-bold transition-all duration-200 flex items-center justify-center gap-1.5"
                                :class="form.context === 'from-internal' ? 'bg-white shadow-sm text-emerald-600' : 'text-slate-500 hover:text-slate-700'">
                            <i class="fa-solid fa-phone"></i> Enable
                        </button>
                        <button type="button" 
                                @click="form.context = 'blokir-total'"
                                class="flex-1 py-2 rounded-lg text-xs font-bold transition-all duration-200 flex items-center justify-center gap-1.5"
                                :class="form.context === 'blokir-total' ? 'bg-white shadow-sm text-red-600' : 'text-slate-500 hover:text-slate-700'">
                            <i class="fa-solid fa-phone-slash"></i> Disable
                        </button>
                    </div>
                    <p class="text-[10px] mt-1.5 font-medium" 
                       :class="form.context === 'blokir-total' ? 'text-red-500' : 'text-slate-400'" 
                       x-text="form.context === 'blokir-total' ? '* Agen ini diblokir total dan tidak bisa melakukan/menerima panggilan.' : '* Agen aktif (from-internal).'"></p>
                </div>

                <!-- 🚀 MULTIPLE SUPERVISOR SELECT INPUT -->
                <!-- 🚀 GANTI SELECT BIASA MENJADI DAFTAR CHECKBOX -->
<div>
    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-2">Assign ke Supervisor (Pilih Banyak)</label>
    
    <div class="border border-slate-200 rounded-xl p-3 bg-slate-50 space-y-2 max-h-48 overflow-y-auto">
        @foreach($supervisors as $spv)
            <label class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-white transition cursor-pointer select-none">
                <!-- Checkbox mendeteksi apakah ID SPV ada di dalam array form.supervisor_ids -->
                <input type="checkbox" 
                       value="{{ $spv->id }}" 
                       x-model="form.supervisor_ids"
                       class="w-4 h-4 text-brand-600 rounded border-slate-300 focus:ring-brand-500">
                <span class="text-sm font-medium text-slate-700">
                    {{ $spv->name }} <span class="text-xs text-slate-400 font-mono">(Ext: {{ $spv->extension }})</span>
                </span>
            </label>
        @endforeach
    </div>
    <p class="text-[10px] text-slate-400 mt-1.5">* Centang nama Supervisor yang ingin diberikan akses monitoring ke agen ini.</p>
</div>

                <div class="flex gap-2 pt-4">
                    <button type="submit" 
                            :disabled="isLoading" 
                            class="flex-1 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white py-2.5 rounded-xl font-medium text-sm transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-spinner fa-spin text-xs" x-show="isLoading" x-cloak></i>
                        <span x-text="isLoading ? 'Memproses...' : (isEdit ? 'Simpan Perubahan' : 'Simpan Data')"></span>
                    </button>
                    <button type="button" @click="openModal = false" :disabled="isLoading" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl font-medium text-sm transition disabled:opacity-50">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function agentManagement() {
    return {
        openModal: false,
        isEdit: false,
        isLoading: false,
        deletingId: null,
        showSecret: false,
        
        // 🚀 Ubah supervisor_id menjadi supervisor_ids berupa Array ([])
        form: { 
            id: null, 
            name: '', 
            extension: '', 
            secret: '', 
            role: 'agent', 
            supervisor_ids: [], 
            context: 'from-internal' 
        },

        openCreateModal() {
            this.isEdit = false;
            this.showSecret = false;
            this.form = { 
                id: null, 
                name: '', 
                extension: '', 
                secret: '', 
                role: 'agent', 
                supervisor_ids: [], 
                context: 'from-internal' 
            };
            this.openModal = true;
        },

        openEditModal(agent) {
            this.isEdit = true;
            this.showSecret = false;
            
            // 🚀 Mapping data relasi supervisors menjadi array of ID (contoh: [1, 3])
            let spvIds = agent.supervisors ? agent.supervisors.map(s => s.id) : [];

            this.form = {
                id: agent.id,
                name: agent.name,
                extension: agent.extension,
                secret: agent.secret || '', 
                role: agent.role || 'agent',
                supervisor_ids: spvIds,
                context: agent.context || 'from-internal'
            };
            this.openModal = true;
        },

        submitForm() {
            this.isLoading = true;
            let url = this.isEdit ? `/dashboard/agents/${this.form.id}` : '/dashboard/agents/store';
            let method = this.isEdit ? 'PUT' : 'POST';

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(this.form)
            })
            .then(res => res.json())
            .then(data => {
                this.isLoading = false;
                if(data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Gagal: ' + (data.message || 'Periksa kembali inputan.'));
                }
            })
            .catch(err => {
                this.isLoading = false;
                console.error('Error:', err);
            });
        },

        deleteAgent(id) {
            if(!confirm('Apakah Abang yakin ingin menghapus pengguna ini dari sistem?')) return;
            
            this.deletingId = id;

            fetch(`/dashboard/agents/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(async res => {
                let data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Terjadi kesalahan pada server');
                return data;
            })
            .then(data => {
                this.deletingId = null;
                if(data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Gagal: ' + (data.message || 'Gagal menghapus pengguna.'));
                }
            })
            .catch(err => {
                this.deletingId = null;
                console.error('Error:', err);
                alert('Error Sistem: ' + err.message); 
            });
        }
    }
}
</script>
@endsection