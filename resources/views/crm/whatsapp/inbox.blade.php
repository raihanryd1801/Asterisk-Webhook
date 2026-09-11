@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-4 h-[calc(100vh-140px)] min-h-[540px]">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
            <i class="fa-solid fa-inbox text-brand-600"></i> Inbox WhatsApp
        </h1>
        <p class="text-slate-500 mt-1">Balasan customer ke nomor Anda. Klik percakapan untuk membaca & membalas.</p>
    </div>

    <div class="flex flex-1 gap-4 overflow-hidden">
        <!-- Daftar percakapan -->
        <div class="w-full sm:w-80 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col" id="wa-conv-list">
            <div class="p-3 border-b border-slate-200">
                <input id="wa-search" type="text" placeholder="Cari nama / nomor..."
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
            </div>
            <div class="flex-1 overflow-y-auto" id="wa-convs"></div>
        </div>

        <!-- Thread -->
        <div class="flex-1 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hidden sm:flex flex-col" id="wa-thread-pane">
            <div class="p-4 border-b border-slate-200 flex items-center justify-between gap-2" id="wa-thread-head" style="display: none;">
                <div class="min-w-0">
                    <p class="font-bold text-slate-900 truncate" id="wa-thread-name"></p>
                    <p class="text-xs text-slate-500 font-mono" id="wa-thread-phone"></p>
                    <p class="text-xs text-emerald-700 font-mono" id="wa-thread-customer" style="display: none;"></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button onclick="waBack()" class="sm:hidden px-3 py-1.5 border border-slate-300 rounded-lg text-xs">← Kembali</button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-2.5 bg-slate-50" id="wa-messages"></div>
            <form onsubmit="return waReply(event)" class="p-3 border-t border-slate-200 flex gap-2 bg-white" id="wa-reply-form" style="display: none;">
                <input id="wa-reply-text" type="text" placeholder="Tulis balasan..." autocomplete="off"
                    class="flex-1 border border-slate-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                <button type="submit" id="wa-reply-btn" class="bg-brand-600 hover:bg-brand-700 text-white w-10 h-10 rounded-xl text-sm transition flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
            <div class="flex-1 flex-col items-center justify-center text-slate-400 text-sm p-8 text-center" id="wa-empty" style="display: flex;">
                <i class="fa-regular fa-comments text-3xl mb-2 text-slate-300"></i>
                Pilih percakapan di sebelah kiri.
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// Guard window agar kunjungan ulang via Turbo tidak crash
// "Identifier 'WAInbox' has already been declared".
window.WAInbox = window.WAInbox || {
    active: null,
    timer: null,
    sending: false,

    esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    },

    mediaHtml(m) {
        if (!m.media_url) return '';
        const url = m.media_url;
        if (m.media_kind === 'image') {
            return `<a href="${url}" target="_blank" rel="noopener"><img src="${url}" class="rounded-xl mb-1.5 max-h-64 w-auto" loading="lazy"></a>`;
        }
        if (m.media_kind === 'video') {
            return `<video src="${url}" controls class="rounded-xl mb-1.5 max-h-64 max-w-full"></video>`;
        }
        if (m.media_kind === 'audio') {
            return `<audio src="${url}" controls class="mb-1.5 max-w-full"></audio>`;
        }
        return `<a href="${url}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 underline mb-1.5"><i class="fa-solid fa-paperclip"></i> Lampiran</a>`;
    },

    csrfToken() {
        // Cookie XSRF-TOKEN selalu fresh di tiap respons (meta bisa basi
        // kalau halaman berasal dari cache Turbo) — cegah 419 Page Expired.
        const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
        if (m) {
            try { return decodeURIComponent(m[1]); } catch (e) {}
        }
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    },

    async api(url, opts = {}) {
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken(),
                ...(opts.headers || {}),
            },
            ...opts,
        });
        if (res.status === 419) {
            const err = new Error('SESSION_EXPIRED');
            err.sessionExpired = true;
            throw err;
        }
        return res.json();
    },

    async loadConvs() {
        const searchEl = document.getElementById('wa-search');
        const box = document.getElementById('wa-convs');
        if (!searchEl || !box) return; // halaman sedang diganti Turbo
        try {
            const data = await this.api('{{ route('crm.whatsapp.conversations') }}');
            if (data.status !== 'success') return;
            const q = (searchEl.value || '').toLowerCase();
            const box = document.getElementById('wa-convs');
            const list = data.data.filter(c =>
                !q || (c.name || '').toLowerCase().includes(q) || (c.phone || '').includes(q));
            box.innerHTML = list.length === 0
                ? '<div class="p-8 text-center text-slate-400 text-sm">Belum ada percakapan.<br>Balasan blast akan muncul di sini.</div>'
                : list.map(c => `
                    <button onclick="window.WAInbox.open('${c.phone}')" class="w-full text-left p-3 border-b border-slate-100 hover:bg-slate-50 transition flex items-center gap-3 ${this.active === c.phone ? 'bg-brand-50' : ''}">
                        <div class="w-10 h-10 rounded-full ${c.unread > 0 ? 'bg-brand-600' : 'bg-slate-200'} text-white flex items-center justify-center font-bold text-sm shrink-0">
                            ${this.esc((c.name || c.phone || '?').substring(0, 1).toUpperCase())}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-semibold text-slate-900 text-sm truncate">${this.esc(c.name || c.phone)}</p>
                                ${c.unread > 0 ? `<span class="bg-red-500 text-white text-[10px] font-bold min-w-[20px] h-5 px-1 rounded-full flex items-center justify-center shrink-0">${c.unread}</span>` : ''}
                            </div>
                            <p class="text-xs text-slate-500 truncate">${c.direction === 'out' ? 'Anda: ' : ''}${this.esc(c.last_message)}</p>
                            <p class="text-[10px] text-slate-400 font-mono">${this.esc(c.customer_phone || ('+' + c.phone))} • ${this.esc(c.at || '')}</p>
                        </div>
                    </button>`).join('');
        } catch (e) {
            console.error(e);
        }
    },

    async open(phone) {
        this.active = phone;
        document.getElementById('wa-thread-pane').classList.remove('hidden');
        document.getElementById('wa-thread-pane').classList.add('flex');
        document.getElementById('wa-conv-list').classList.add('hidden', 'sm:flex');
        await this.loadThread();
        this.loadConvs();
    },

    waBack() {
        this.active = null;
        document.getElementById('wa-thread-pane').classList.add('hidden');
        document.getElementById('wa-thread-pane').classList.remove('flex');
        document.getElementById('wa-conv-list').classList.remove('hidden');
    },

    async loadThread(silent = true) {
        if (!this.active) return;
        try {
            const data = await this.api(`{{ route('crm.whatsapp.thread') }}?phone=${encodeURIComponent(this.active)}`);
            if (data.status !== 'success') return;
            document.getElementById('wa-thread-head').style.display = 'flex';
            document.getElementById('wa-reply-form').style.display = 'flex';
            document.getElementById('wa-empty').style.display = 'none';
            document.getElementById('wa-thread-name').textContent = data.name || data.phone;
            document.getElementById('wa-thread-phone').textContent = data.customer_phone
                ? data.customer_phone
                : '+' + data.phone;
            const custEl = document.getElementById('wa-thread-customer');
            if (data.customer_phone) {
                custEl.textContent = '👤 ' + (data.name || '') + ' • ' + data.customer_phone;
                custEl.style.display = 'block';
            } else {
                custEl.style.display = 'none';
            }
            window.WAInbox.threadCustomerId = data.customer_id || null;
            const box = document.getElementById('wa-messages');
            box.innerHTML = data.messages.length === 0
                ? '<div class="text-center text-slate-400 text-sm py-8">Belum ada pesan.</div>'
                : data.messages.map(m => {
                    const media = this.mediaHtml(m);
                    const side = m.direction === 'out';
                    const wrap = side
                        ? 'flex justify-end'
                        : 'flex justify-start';
                    const bubble = side
                        ? 'max-w-[80%] bg-brand-600 text-white text-sm rounded-2xl rounded-br-md px-3.5 py-2 shadow-sm'
                        : 'max-w-[80%] bg-white text-slate-800 text-sm rounded-2xl rounded-bl-md px-3.5 py-2 shadow-sm border border-slate-200';
                    const stamp = side ? 'text-brand-100' : 'text-slate-400';
                    return `<div class="${wrap}"><div class="${bubble}">${media}<p class="break-words">${this.esc(m.message)}</p><p class="text-[10px] ${stamp} text-right mt-1">${this.esc(m.at || '')}</p></div></div>`;
                }).join('');
            box.scrollTop = box.scrollHeight;
        } catch (e) {
            if (!silent) console.error(e);
        }
    },

    async freshToken() {
        // Minta token CSRF fresh dari sesi aktif sesaat sebelum POST.
        // Kalau ini gagal (HTML login), berarti sesi tab ini mati total.
        try {
            const res = await fetch('{{ route('crm.whatsapp.csrf') }}', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.csrf) return data.csrf;
        } catch (e) {}
        return null;
    },

    async reply(e) {
        e.preventDefault();
        const input = document.getElementById('wa-reply-text');
        const text = input.value.trim();
        if (!text || !this.active || this.sending) return false;
        // Simpan draft agar tidak hilang kalau harus reload (mis. sesi mati)
        try { localStorage.setItem('wa-reply-draft', JSON.stringify({ phone: this.active, text })); } catch (err) {}
        this.sending = true;
        try {
            const token = await this.freshToken();
            if (!token) {
                if (confirm('Sesi login tab ini mati (tidak bisa mengambil token baru). Buka halaman login? Pesan Anda tersimpan sebagai draft.')) {
                    window.location.href = '/login';
                }
                return false;
            }
            const response = await fetch('{{ route('crm.whatsapp.reply') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ phone: this.active, message: text }),
            });
            if (response.status === 419) {
                if (confirm('Server menolak token (419). Muat ulang halaman untuk sesi baru? Pesan Anda tersimpan sebagai draft.')) {
                    window.location.reload();
                }
                return false;
            }
            const data = await response.json();
            if (data.status === 'success') {
                input.value = '';
                try { localStorage.removeItem('wa-reply-draft'); } catch (err) {}
                await this.loadThread();
                this.loadConvs();
            } else {
                alert(data.message || 'Gagal mengirim.');
            }
        } catch (err) {
            alert('Terjadi kesalahan jaringan.');
        } finally {
            this.sending = false;
        }
        return false;
    },
};

function waBack() { window.WAInbox.waBack(); }
function waReply(e) { return window.WAInbox.reply(e); }



function waInboxInit() {
    // Hentikan timer lama (mis. dari kunjungan Turbo sebelumnya) agar tidak dobel
    if (window.WAInbox.timer) clearInterval(window.WAInbox.timer);
    window.WAInbox.active = null;
    // Kembalikan draft pesan yang tersimpan sebelum reload paksa
    try {
        const draft = JSON.parse(localStorage.getItem('wa-reply-draft') || 'null');
        if (draft && draft.text) {
            const input = document.getElementById('wa-reply-text');
            if (input && !input.value) input.value = draft.text;
        }
    } catch (err) {}
    window.WAInbox.loadConvs();
    const searchEl = document.getElementById('wa-search');
    if (searchEl && !searchEl.dataset.bound) {
        searchEl.dataset.bound = '1';
        searchEl.addEventListener('input', () => window.WAInbox.loadConvs());
    }
    window.WAInbox.timer = setInterval(() => {
        window.WAInbox.loadConvs();
        if (window.WAInbox.active) window.WAInbox.loadThread();
    }, 5000);
}

document.addEventListener('DOMContentLoaded', waInboxInit);
// Turbo tidak memicu DOMContentLoaded saat navigasi
document.addEventListener('turbo:load', waInboxInit);
document.addEventListener('turbo:before-cache', () => {
    if (window.WAInbox && window.WAInbox.timer) clearInterval(window.WAInbox.timer);
});
</script>
@endsection
