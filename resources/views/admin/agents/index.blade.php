@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="agentManagement()">
    
    <!-- Header Title -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Agent Management</h1>
            <p class="text-xs text-slate-500 mt-1">Kelola data agen, ekstensi, dan grup antrian sistem.</p>
        </div>
        <button @click="openCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm shadow-sm transition">
            + Tambah Agen Baru
        </button>
    </div>

    <!-- Tabel Data Agen -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b">
                <tr class="text-xs font-bold text-slate-500 uppercase">
                    <th class="p-4">Nama Agen</th>
                    <th class="p-4">Extension</th>
                    <th class="p-4">Group</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-sm">
                @foreach($agents as $agent)
                <tr class="hover:bg-slate-50 transition">
                    <td class="p-4 font-semibold text-slate-800">{{ $agent->name }}</td>
                    <td class="p-4 text-slate-600 font-mono">{{ $agent->extension }}</td>
                    <td class="p-4 text-slate-600">{{ $agent->group->name ?? 'Tanpa Grup' }}</td>
                    <td class="p-4">
                        <span class="px-2.5 py-1 text-xs font-bold rounded-full uppercase 
                            {{ $agent->status === 'online' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $agent->status }}
                        </span>
                    </td>
                    <td class="p-4 flex gap-3">
                        <button @click="openEditModal({{ json_encode($agent) }})" class="text-blue-600 hover:underline font-semibold text-xs">Edit</button>
                        <button @click="deleteAgent({{ $agent->id }})" class="text-red-600 hover:underline font-semibold text-xs">Delete</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- MODAL FORM (TAMBAH / EDIT) -->
    <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" style="display: none;">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md" @click.outside="openModal = false">
            <h3 class="text-lg font-bold text-slate-800 mb-4" x-text="isEdit ? 'Edit Data Agen' : 'Tambah Ekstensi Agen Baru'"></h3>
            
            <form @submit.prevent="submitForm()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Nama Agen</label>
                        <input type="text" x-model="form.name" required placeholder="Contoh: Budi Santoso" 
                               class="w-full border border-slate-300 p-2.5 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Nomor Ekstensi</label>
                        <input type="text" x-model="form.extension" required placeholder="Contoh: 105" 
                               class="w-full border border-slate-300 p-2.5 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <!-- Input Password SIP -->
<div>
    <label class="block text-sm font-semibold text-slate-700 mb-1">Password SIP (Opsional)</label>
    <input type="text" x-model="form.secret" placeholder="Kosongkan untuk generate otomatis" 
           class="w-full border border-slate-300 p-2.5 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 font-mono">
    <p class="text-[11px] text-slate-400 mt-1">Jika dikosongkan, sistem akan membuatkan password acak yang aman.</p>
</div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Grup Antrian</label>
                        <select x-model="form.group_id" class="w-full border border-slate-300 p-2.5 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="">-- Pilih Grup (Opsional) --</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-bold text-sm transition" x-text="isEdit ? 'Simpan Perubahan' : 'Simpan Agen'"></button>
                    <button type="button" @click="openModal = false" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2.5 rounded-lg font-bold text-sm transition">Batal</button>
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
        form: {
            id: null,
            name: '',
            extension: '',
            group_id: ''
        },

        openCreateModal() {
    this.isEdit = false;
    this.form = { id: null, name: '', extension: '', group_id: '', secret: '' };
    this.openModal = true;
},

        openEditModal(agent) {
            this.isEdit = true;
            this.form = {
                id: agent.id,
                name: agent.name,
                extension: agent.extension,
                group_id: agent.group_id || ''
            };
            this.openModal = true;
        },

        submitForm() {
            let url = this.isEdit ? `/admin/agents/${this.form.id}` : '/admin/agents/store';
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
                if(data.status === 'success') {
                    alert(data.message);
                    location.reload(); // Refresh halaman untuk melihat data terbaru
                } else {
                    alert('Gagal: ' + (data.message || 'Periksa kembali inputan.'));
                }
            })
            .catch(err => console.error('Error:', err));
        },

        deleteAgent(id) {
            if(!confirm('Apakah Abang yakin ingin menghapus agen ini dari sistem?')) return;

            fetch(`/admin/agents/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Gagal menghapus agen.');
                }
            })
            .catch(err => console.error('Error:', err));
        }
    }
}
</script>
@endsection