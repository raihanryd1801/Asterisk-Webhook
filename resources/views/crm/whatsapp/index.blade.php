@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-6 max-w-2xl">
    <!-- Header banner, tema disamakan dengan Inbox WhatsApp / Overview Dashboard -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl px-5 py-4 flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-brand-600 text-white flex items-center justify-center text-lg shadow-sm shrink-0">
            <i class="fa-brands fa-whatsapp"></i>
        </div>
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-brand-700 leading-tight">WhatsApp Saya</h1>
            <p class="text-sm text-brand-600/80 mt-0.5">Hubungkan nomor WhatsApp milik Anda ({{ $ownerLabel }}) untuk blast. Satu sesi per akun.</p>
        </div>
    </div>

    <div id="wa-unreachable" class="bg-red-50 border border-red-200 rounded-2xl p-5 text-sm text-red-700 flex items-start gap-2" style="display: none;">
        <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0"></i>
        <span>Service WhatsApp gateway tidak jalan. Jalankan <code class="font-mono bg-red-100 px-1 rounded">node server.js</code> di folder <code class="font-mono bg-red-100 px-1 rounded">wa-gateway</code> atau hubungi IT.</span>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center relative overflow-hidden">
        <!-- aksen dekoratif -->
        <div class="absolute -top-10 -right-10 w-32 h-32 rounded-full bg-brand-50 opacity-70 pointer-events-none"></div>
        <div class="absolute -bottom-10 -left-10 w-24 h-24 rounded-full bg-brand-50 opacity-50 pointer-events-none"></div>

        <div class="relative flex items-center justify-center gap-2 mb-5">
            <span id="wa-dot" class="w-3 h-3 rounded-full bg-slate-300"></span>
            <span id="wa-status-text" class="text-sm font-bold text-slate-600 tracking-wide">Memeriksa status...</span>
        </div>

        <div id="wa-qr-wrap" class="relative" style="display: none;">
            <p class="text-sm text-slate-600 mb-3">Scan QR ini dengan WhatsApp di HP Anda <strong>(Perangkat Tertaut)</strong>:</p>
            <div class="inline-block p-3 bg-white rounded-2xl border-2 border-brand-100 shadow-sm">
                <img id="wa-qr" src="" alt="QR WhatsApp" class="mx-auto w-64 h-64 rounded-lg">
            </div>
            <p class="text-[11px] text-slate-400 mt-3 flex items-center justify-center gap-1">
                <i class="fa-solid fa-shield-halved"></i> QR berganti otomatis. Jangan share QR ini ke orang lain.
            </p>
        </div>

        <div id="wa-connected-wrap" class="relative" style="display: none;">
            <div class="w-14 h-14 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3 text-xl">
                <i class="fa-solid fa-check"></i>
            </div>
            <p class="text-sm text-slate-600">Terhubung sebagai nomor</p>
            <p id="wa-phone" class="text-2xl font-bold font-mono text-brand-700 my-2"></p>
            <p class="text-xs text-slate-400">Blast dari menu Buckets akan terkirim dari nomor ini.</p>
        </div>

        <div class="relative flex justify-center gap-2 mt-6">
            <button onclick="waRefresh()" class="px-4 py-2 border border-slate-300 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                <i class="fa-solid fa-rotate"></i> Refresh
            </button>
            <button onclick="waDisconnect()" id="wa-disconnect-btn" style="display: none;" class="px-4 py-2 bg-rose-600 text-white rounded-xl text-sm font-medium hover:bg-rose-700 transition-colors shadow-sm">
                <i class="fa-solid fa-link-slash"></i> Putuskan Sesi
            </button>
        </div>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 text-xs text-amber-800 leading-relaxed">
        <p class="font-bold mb-1.5 flex items-center gap-1.5"><i class="fa-solid fa-triangle-exclamation"></i> Catatan penting</p>
        <ul class="list-disc list-inside space-y-1">
            <li>Ini koneksi tidak resmi (scan barcode). Jangan dipakai blast ratusan nomor sekaligus dalam waktu singkat.</li>
            <li>Beri jeda wajar — sistem otomatis memberi jeda 3–7 detik per pesan.</li>
            <li>Kalau nomor ter-logout sendiri / diminta scan ulang, ulangi langkah di halaman ini.</li>
        </ul>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Guard window agar eksekusi ulang script oleh Turbo tidak crash
// "Identifier has already been declared".
window.WAConnect = window.WAConnect || {
    reachable: @json($reachable),
    timer: null,

    csrfToken() {
        const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
        if (m) {
            try { return decodeURIComponent(m[1]); } catch (e) {}
        }
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    },
};

async function waFreshToken() {
    // Token fresh dari sesi aktif — anti basi walau tab lama / habis ganti akun.
    // Sama polanya dengan halaman Inbox yang terbukti bisa kirim.
    try {
        const res = await fetch('{{ route('crm.whatsapp.csrf') }}', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.csrf) return data.csrf;
    } catch (e) {}
    return null;
}

async function waFetch(url, opts = {}) {
    const isGet = !opts.method || opts.method.toUpperCase() === 'GET';
    const headers = { 'Accept': 'application/json', ...(opts.headers || {}) };
    if (!isGet) {
        // POST/DELETE selalu pakai token fresh; kalau sesi mati, gagalkan cepat
        // dengan pesan jelas (bukan 419 misterius).
        const token = await waFreshToken();
        if (!token) {
            const err = new Error('SESSION_DEAD');
            err.sessionDead = true;
            throw err;
        }
        headers['X-CSRF-TOKEN'] = token;
    }
    const res = await fetch(url, { ...opts, headers });
    return res.json();
}

function waPaint(state) {
    const dot = document.getElementById('wa-dot');
    if (!dot) return; // dipanggil saat DOM halaman ini belum/sudah tidak ada (mis. timer lama)
    const txt = document.getElementById('wa-status-text');
    const qrWrap = document.getElementById('wa-qr-wrap');
    const okWrap = document.getElementById('wa-connected-wrap');
    const discBtn = document.getElementById('wa-disconnect-btn');

    if (!state) {
        dot.className = 'w-3 h-3 rounded-full bg-slate-300';
        txt.textContent = 'Status tidak diketahui';
        txt.className = 'text-sm font-bold text-slate-600 tracking-wide';
        qrWrap.style.display = 'none';
        okWrap.style.display = 'none';
        discBtn.style.display = 'none';
        return;
    }

    if (state.status === 'connected') {
        dot.className = 'w-3 h-3 rounded-full bg-emerald-500 animate-pulse';
        txt.textContent = 'Terhubung';
        txt.className = 'text-sm font-bold text-emerald-600 tracking-wide';
        document.getElementById('wa-phone').textContent = '+' + (state.phone || '-');
        qrWrap.style.display = 'none';
        okWrap.style.display = 'block';
        discBtn.style.display = 'inline-block';
        clearInterval(window.WAConnect.timer);
    } else {
        dot.className = 'w-3 h-3 rounded-full bg-amber-500 animate-pulse';
        txt.textContent = state.status === 'qr' ? 'Menunggu scan QR...' : 'Menghubungkan...';
        txt.className = 'text-sm font-bold text-amber-600 tracking-wide';
        okWrap.style.display = 'none';
        discBtn.style.display = 'none';
        if (state.qr) {
            document.getElementById('wa-qr').src = state.qr;
            qrWrap.style.display = 'block';
        } else {
            qrWrap.style.display = 'none';
        }
    }
}

async function waRefresh() {
    // Single-flight: DOMContentLoaded + turbo:load bisa terjadi berbarengan
    if (window.WAConnect.started) return;
    window.WAConnect.started = true;
    try {
        // Minta sesi dulu (agar QR dibuat), lalu baca status
        await waFetch('{{ route('crm.whatsapp.connect') }}', { method: 'POST' });
        const data = await waFetch('{{ route('crm.whatsapp.status') }}');
        waPaint(data.session || null);
        // Polling sampai connected / QR baru
        clearInterval(window.WAConnect.timer);
        let tries = 0;
        window.WAConnect.timer = setInterval(async () => {
            if (++tries > 40) { clearInterval(window.WAConnect.timer); return; }
            try {
                const d = await waFetch('{{ route('crm.whatsapp.status') }}');
                waPaint(d.session || null);
                if ((d.session || {}).status === 'connected') clearInterval(window.WAConnect.timer);
            } catch (e) {}
        }, 3000);
    } catch (e) {
        if (e && e.sessionDead) {
            const dot = document.getElementById('wa-dot');
            if (dot) {
                const txt = document.getElementById('wa-status-text');
                dot.className = 'w-3 h-3 rounded-full bg-red-500';
                txt.textContent = 'Sesi login mati — buka halaman login lalu kembali ke sini.';
                txt.className = 'text-sm font-bold text-red-600 tracking-wide';
            }
        } else {
            console.error(e);
        }
    } finally {
        window.WAConnect.started = false;
    }
}

async function waDisconnect() {
    if (!confirm('Putuskan sesi WhatsApp ini? Blast berikutnya perlu scan ulang.')) return;
    try {
        await waFetch('{{ route('crm.whatsapp.disconnect') }}', { method: 'DELETE' });
    } catch (e) {
        if (e && e.sessionDead) {
            alert('Sesi login mati. Login ulang lalu coba lagi.');
            return;
        }
    }
    waRefresh();
}

function waConnectInit() {
    if (!window.WAConnect.reachable) {
        const warn = document.getElementById('wa-unreachable');
        if (warn) warn.style.display = 'block';
    }
    waRefresh();
}

document.addEventListener('DOMContentLoaded', waConnectInit);
// Turbo tidak memicu DOMContentLoaded saat navigasi
document.addEventListener('turbo:load', waConnectInit);
document.addEventListener('turbo:before-cache', () => {
    if (window.WAConnect && window.WAConnect.timer) clearInterval(window.WAConnect.timer);
});
</script>
@endsection