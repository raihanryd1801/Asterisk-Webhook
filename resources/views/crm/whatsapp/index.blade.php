@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-6 max-w-2xl">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
            <i class="fa-brands fa-whatsapp text-emerald-600"></i> WhatsApp Saya
        </h1>
        <p class="text-slate-500 mt-1">Hubungkan nomor WhatsApp milik Anda ({{ $ownerLabel }}) untuk blast. Satu sesi per akun.</p>
    </div>

    <div id="wa-unreachable" class="bg-red-50 border border-red-200 rounded-2xl p-5 text-sm text-red-700" style="display: none;">
        Service WhatsApp gateway tidak jalan. Jalankan <code class="font-mono bg-red-100 px-1 rounded">node server.js</code> di folder <code class="font-mono bg-red-100 px-1 rounded">wa-gateway</code> atau hubungi IT.
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 text-center">
        <div class="flex items-center justify-center gap-2 mb-4">
            <span id="wa-dot" class="w-3 h-3 rounded-full bg-slate-300"></span>
            <span id="wa-status-text" class="text-sm font-bold text-slate-600">Memeriksa status...</span>
        </div>

        <div id="wa-qr-wrap" style="display: none;">
            <p class="text-sm text-slate-600 mb-3">Scan QR ini dengan WhatsApp di HP Anda <strong>(Perangkat Tertaut)</strong>:</p>
            <img id="wa-qr" src="" alt="QR WhatsApp" class="mx-auto w-64 h-64 border border-slate-200 rounded-xl">
            <p class="text-[11px] text-slate-400 mt-2">QR berganti otomatis. Jangan share QR ini ke orang lain.</p>
        </div>

        <div id="wa-connected-wrap" style="display: none;">
            <p class="text-sm text-slate-600">Terhubung sebagai nomor</p>
            <p id="wa-phone" class="text-2xl font-bold font-mono text-emerald-700 my-2"></p>
            <p class="text-xs text-slate-400">Blast dari menu Buckets akan terkirim dari nomor ini.</p>
        </div>

        <div class="flex justify-center gap-2 mt-5">
            <button onclick="waRefresh()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">
                <i class="fa-solid fa-rotate"></i> Refresh
            </button>
            <button onclick="waDisconnect()" id="wa-disconnect-btn" style="display: none;" class="px-4 py-2 bg-rose-600 text-white rounded-lg text-sm hover:bg-rose-700 transition-colors">
                Putuskan Sesi
            </button>
        </div>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 text-xs text-amber-800 leading-relaxed">
        <p class="font-bold mb-1"><i class="fa-solid fa-triangle-exclamation"></i> Catatan penting</p>
        <ul class="list-disc list-inside space-y-0.5">
            <li>Ini koneksi tidak resmi (scan barcode). Jangan dipakai blast ratusan nomor sekaligus dalam waktu singkat.</li>
            <li>Beri jeda wajar — sistem otomatis memberi jeda 3–7 detik per pesan.</li>
            <li>Kalau nomor ter-logout sendiri / diminta scan ulang, ulangi langkah di halaman ini.</li>
        </ul>
    </div>
</div>
@endsection

@section('scripts')
<script>
const WA = {
    reachable: @json($reachable),
    timer: null,
};

async function waFetch(url, opts = {}) {
    const res = await fetch(url, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            ...(opts.headers || {}),
        },
        ...opts,
    });
    return res.json();
}

function waPaint(state) {
    const dot = document.getElementById('wa-dot');
    const txt = document.getElementById('wa-status-text');
    const qrWrap = document.getElementById('wa-qr-wrap');
    const okWrap = document.getElementById('wa-connected-wrap');
    const discBtn = document.getElementById('wa-disconnect-btn');

    if (!state) {
        dot.className = 'w-3 h-3 rounded-full bg-slate-300';
        txt.textContent = 'Status tidak diketahui';
        txt.className = 'text-sm font-bold text-slate-600';
        qrWrap.style.display = 'none';
        okWrap.style.display = 'none';
        discBtn.style.display = 'none';
        return;
    }

    if (state.status === 'connected') {
        dot.className = 'w-3 h-3 rounded-full bg-emerald-500 animate-pulse';
        txt.textContent = 'Terhubung';
        txt.className = 'text-sm font-bold text-emerald-600';
        document.getElementById('wa-phone').textContent = '+' + (state.phone || '-');
        qrWrap.style.display = 'none';
        okWrap.style.display = 'block';
        discBtn.style.display = 'inline-block';
        clearInterval(WA.timer);
    } else {
        dot.className = 'w-3 h-3 rounded-full bg-amber-500 animate-pulse';
        txt.textContent = state.status === 'qr' ? 'Menunggu scan QR...' : 'Menghubungkan...';
        txt.className = 'text-sm font-bold text-amber-600';
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
    try {
        // Minta sesi dulu (agar QR dibuat), lalu baca status
        await waFetch('{{ route('crm.whatsapp.connect') }}', { method: 'POST' });
        const data = await waFetch('{{ route('crm.whatsapp.status') }}');
        waPaint(data.session || null);
        // Polling sampai connected / QR baru
        clearInterval(WA.timer);
        let tries = 0;
        WA.timer = setInterval(async () => {
            if (++tries > 40) { clearInterval(WA.timer); return; }
            try {
                const d = await waFetch('{{ route('crm.whatsapp.status') }}');
                waPaint(d.session || null);
                if ((d.session || {}).status === 'connected') clearInterval(WA.timer);
            } catch (e) {}
        }, 3000);
    } catch (e) {
        console.error(e);
    }
}

async function waDisconnect() {
    if (!confirm('Putuskan sesi WhatsApp ini? Blast berikutnya perlu scan ulang.')) return;
    await waFetch('{{ route('crm.whatsapp.disconnect') }}', { method: 'DELETE' });
    waRefresh();
}

document.addEventListener('DOMContentLoaded', () => {
    if (!WA.reachable) {
        document.getElementById('wa-unreachable').style.display = 'block';
    }
    waRefresh();
});
</script>
@endsection
