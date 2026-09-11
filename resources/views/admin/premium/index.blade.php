@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data='premiumManager(@json(\App\Models\FeatureFlag::states()))'>

    <!-- Header Section -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-crown text-amber-500"></i> Premium / Lisensi Fitur
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Buka atau gembok modul premium untuk seluruh akun non-superadmin. Perubahan berlaku maksimal 1 menit.</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border bg-amber-50 text-amber-600 border-amber-200 shadow-sm">
            <i class="fa-solid fa-user-shield text-[11px]"></i> Khusus Superadmin
        </span>
    </div>

    <!-- Toggle Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <template x-for="(label, key) in modules" :key="key">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between gap-3"
                     :class="states[key] ? 'bg-emerald-50/60' : 'bg-slate-50/60'">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center text-lg shrink-0"
                             :class="states[key] ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-200 text-slate-500'">
                            <i class="fa-solid" :class="states[key] ? 'fa-lock-open' : 'fa-lock'"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-800" x-text="label"></p>
                            <p class="text-xs font-medium" :class="states[key] ? 'text-emerald-600' : 'text-slate-400'"
                               x-text="states[key] ? 'TERBUKA untuk semua user' : 'TERGEMBOK (Premium)'"></p>
                        </div>
                    </div>
                    <button @click="toggle(key)" :disabled="loading === key"
                            class="relative w-12 h-7 rounded-full transition-colors shrink-0"
                            :class="states[key] ? 'bg-emerald-500' : 'bg-slate-300'">
                        <span class="absolute top-1 w-5 h-5 rounded-full bg-white shadow transition-all"
                              :class="states[key] ? 'left-6' : 'left-1'"></span>
                    </button>
                </div>
                <div class="p-4 text-xs text-slate-500 leading-relaxed" x-text="descriptions[key]"></div>
            </div>
        </template>
    </div>

    <!-- Info -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 flex items-start gap-3">
        <i class="fa-solid fa-circle-info text-brand-500 mt-0.5"></i>
        <div class="text-sm text-slate-600 leading-relaxed">
            <p class="font-semibold text-slate-800">Cara kerja gembok:</p>
            <ul class="list-disc list-inside mt-1 space-y-0.5">
                <li>Akun <strong>superadmin</strong> selalu bisa membuka semua modul, apa pun posisi toggle.</li>
                <li>Saat toggle <strong>OFF</strong>, admin & supervisor melihat ikon <i class="fa-solid fa-lock text-[10px]"></i> di sidebar dan halaman gembok <em>"Premium Feature — hubungi admin jika ingin menggunakannya"</em> saat membuka menu.</li>
                <li>Saat toggle <strong>ON</strong>, admin & supervisor bisa memakai modul tersebut normal.</li>
            </ul>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
window.premiumManager = function(initialStates) {
    return {
        states: initialStates || { crm: false, collection: false, dialer: false },
        loading: null,
        modules: @json(\App\Models\FeatureFlag::MODULES),
        descriptions: {
            crm: 'Dashboard Agent + Customers + Buckets + WhatsApp Saya: kelola debtor, tagihan, bucket, dan sesi WA.',
            collection: 'Collection Dashboard, PTP Management, Debt Collectors: risiko, janji bayar, dan handover.',
            dialer: 'Auto-Dialer (PDS): dial otomatis bucket ke agent rotation.',
        },

        async toggle(key) {
            const next = !this.states[key];
            const label = this.modules[key] || key;
            if (!confirm((next ? 'BUKA' : 'GEMBOK') + ' modul "' + label + '" untuk non-superadmin?')) return;

            this.loading = key;
            try {
                const res = await fetch('{{ route('premium.toggle') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ key: key, enabled: next }),
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.states = data.states;
                } else {
                    alert(data.message || 'Gagal menyimpan.');
                }
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan jaringan.');
            } finally {
                this.loading = null;
            }
        },
    };
};
</script>
@endsection
