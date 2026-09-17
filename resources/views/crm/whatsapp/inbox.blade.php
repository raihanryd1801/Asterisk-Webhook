@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-4 h-[calc(100vh-140px)] min-h-[540px]">
    <!-- Header banner, tema disamakan dengan Overview Dashboard -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl px-5 py-4 flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-brand-600 text-white flex items-center justify-center text-lg shadow-sm shrink-0">
            <i class="fa-brands fa-whatsapp"></i>
        </div>
        <div class="min-w-0 flex-1">
            <h1 class="text-xl font-bold text-brand-700 leading-tight">Inbox WhatsApp</h1>
            <p class="text-sm text-brand-600/80 mt-0.5">Balasan customer ke nomor Anda. Klik percakapan untuk membaca & membalas.</p>
        </div>
        <button onclick="window.WAInbox.openNewModal()" title="Mulai percakapan baru dengan customer"
            class="shrink-0 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-sm font-medium transition flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-plus"></i><span class="hidden sm:inline">Percakapan Baru</span>
        </button>
    </div>

    @if(($isSharedAgent ?? false))
    <div class="bg-sky-50 border border-sky-200 rounded-2xl px-5 py-3 flex items-start gap-2 text-sm text-sky-800">
        <i class="fa-solid fa-users mt-0.5 shrink-0"></i>
        <span>Mode agent — memakai nomor SPV <strong>{{ $waOwnerLabel ?? '-' }}</strong>. Semua percakapan di nomor ini tampil di sini. Balasan Anda tercatat atas nama Anda.</span>
    </div>
    @endif

    <div class="flex flex-1 gap-4 overflow-hidden">
        <!-- Daftar percakapan -->
        <div class="w-full sm:w-80 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col" id="wa-conv-list">
            <div class="p-3 border-b border-slate-100">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input id="wa-search" type="text" placeholder="Cari nama / nomor..."
                        class="w-full border border-slate-200 rounded-xl pl-9 pr-3 py-2.5 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-transparent transition">
                </div>
            </div>
            <div class="flex-1 overflow-y-auto divide-y divide-slate-100" id="wa-convs"></div>
        </div>

        <!-- Thread -->
        <div class="flex-1 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hidden sm:flex flex-col" id="wa-thread-pane">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between gap-2 bg-gradient-to-r from-brand-50 to-white" id="wa-thread-head" style="display: none;">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-full bg-brand-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                        <i class="fa-solid fa-user text-sm"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-slate-900 truncate" id="wa-thread-name"></p>
                        <p class="text-xs text-slate-500 font-mono" id="wa-thread-phone"></p>
                        <p class="text-xs text-emerald-700 font-mono" id="wa-thread-customer" style="display: none;"></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button onclick="window.WAInbox.openLinkModal()" title="Tautkan ke data customer"
                        class="px-3 py-1.5 border border-brand-200 bg-brand-50 text-brand-700 rounded-xl text-xs font-medium hover:bg-brand-100 transition flex items-center gap-1.5">
                        <i class="fa-solid fa-link"></i><span class="hidden sm:inline">Customer</span>
                    </button>
                    <button onclick="waBack()" class="sm:hidden px-3 py-1.5 border border-slate-300 rounded-xl text-xs font-medium hover:bg-slate-50 transition">← Kembali</button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-3" id="wa-messages"
                 style="background-color:#f4f7f5; background-image: radial-gradient(circle, rgba(15,23,42,0.05) 1px, transparent 1px); background-size: 18px 18px;"></div>
            <div id="wa-attach-preview" class="px-3 pt-2 bg-white" style="display: none;">
                <div class="flex items-center gap-2 text-xs bg-slate-100 border border-slate-200 rounded-xl px-3 py-2">
                    <i class="fa-solid fa-paperclip text-slate-500"></i>
                    <span id="wa-attach-name" class="flex-1 truncate text-slate-700"></span>
                    <button type="button" onclick="waClearAttach()" class="text-slate-400 hover:text-red-500"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <form onsubmit="return waReply(event)" class="p-3 border-t border-slate-100 flex gap-2 bg-white" id="wa-reply-form" style="display: none;">
                <input id="wa-attach-file" type="file" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" class="hidden">
                <button type="button" onclick="document.getElementById('wa-attach-file').click()" title="Lampirkan gambar/dokumen"
                    class="w-11 h-11 rounded-full text-slate-500 hover:bg-slate-100 hover:text-brand-600 text-sm transition flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-paperclip"></i>
                </button>
                <input id="wa-reply-text" type="text" placeholder="Tulis balasan..." autocomplete="off"
                    class="flex-1 border border-slate-200 rounded-full px-4 py-2.5 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-transparent transition">
                <button type="submit" id="wa-reply-btn" class="bg-brand-600 hover:bg-brand-700 text-white w-11 h-11 rounded-full text-sm transition flex items-center justify-center shrink-0 shadow-sm">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
            <div class="flex-1 flex-col items-center justify-center text-slate-400 text-sm p-8 text-center gap-2" id="wa-empty" style="display: flex;">
                <div class="w-16 h-16 rounded-full bg-brand-50 flex items-center justify-center mb-1">
                    <i class="fa-regular fa-comments text-2xl text-brand-300"></i>
                </div>
                Pilih percakapan di sebelah kiri.
            </div>
        </div>
    </div>

    <!-- Modal percakapan baru: pilih nomor customer -->
    <div id="wa-new-modal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" onclick="window.WAInbox.closeNewModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[85vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between p-4 border-b border-slate-200">
                    <div>
                        <h3 class="font-semibold text-slate-900">Percakapan Baru</h3>
                        <p class="text-xs text-slate-500">Pilih nomor customer untuk mulai chat.</p>
                    </div>
                    <button onclick="window.WAInbox.closeNewModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-xl"></i></button>
                </div>
                <div class="p-4 border-b border-slate-100">
                    <input id="wa-new-search" type="text" placeholder="Cari nama / nomor customer..."
                        class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-transparent transition">
                </div>
                <div class="flex-1 overflow-y-auto p-2" id="wa-new-results"></div>
            </div>
        </div>
    </div>

    <!-- Modal tautkan percakapan ke customer -->
    <div id="wa-link-modal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" onclick="window.WAInbox.closeLinkModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[85vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between p-4 border-b border-slate-200">
                    <div>
                        <h3 class="font-semibold text-slate-900">Tautkan ke Customer</h3>
                        <p class="text-xs text-slate-500 font-mono" id="wa-link-phone"></p>
                    </div>
                    <button onclick="window.WAInbox.closeLinkModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-xl"></i></button>
                </div>
                <div class="p-4 border-b border-slate-100">
                    <input id="wa-link-search" type="text" placeholder="Cari nama / nomor customer..."
                        class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-transparent transition">
                </div>
                <div class="flex-1 overflow-y-auto p-2" id="wa-link-results"></div>
                <div class="p-3 border-t border-slate-100 flex justify-between items-center">
                    <button onclick="window.WAInbox.unlinkCustomer()" class="text-xs text-rose-600 hover:text-rose-800 font-medium px-2 py-1">Putuskan tautan</button>
                    <button onclick="window.WAInbox.closeLinkModal()" class="px-4 py-2 border border-slate-300 rounded-xl text-sm text-slate-700 hover:bg-slate-50">Tutup</button>
                </div>
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
    seen: {},
    baselineDone: false,
    baseTitle: document.title,

    esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    },

    beep() {
        // Notifikasi suara pendek; autoplay policy: gagal diam-diam sebelum ada gesture.
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            window.WAInbox._audio = window.WAInbox._audio || new Ctx();
            const ctx = window.WAInbox._audio;
            if (ctx.state === 'suspended') { ctx.resume().catch(() => {}); return; }
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.001, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
            osc.start();
            osc.stop(ctx.currentTime + 0.3);
        } catch (e) {}
    },

    toast(phone, name, text) {
        let wrap = document.getElementById('wa-toasts');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'wa-toasts';
            wrap.className = 'fixed bottom-6 right-6 z-[60] flex flex-col gap-2 items-end';
            document.body.appendChild(wrap);
        }
        const el = document.createElement('button');
        el.className = 'max-w-xs w-72 text-left bg-slate-900 text-white rounded-2xl shadow-2xl border border-white/10 p-3.5 flex gap-3 items-start hover:bg-slate-800 transition';
        el.innerHTML =
            '<div class="w-10 h-10 rounded-full bg-brand-600 flex items-center justify-center font-bold shrink-0">' +
            this.esc((name || phone || '?').substring(0, 1).toUpperCase()) + '</div>' +
            '<div class="flex-1 min-w-0"><p class="font-semibold text-sm truncate">' +
            this.esc(name || phone) + '</p><p class="text-xs text-slate-300 truncate mt-0.5">' +
            this.esc(text || 'Pesan baru masuk') + '</p>' +
            '<p class="text-[10px] text-brand-500 font-semibold mt-1">Klik untuk membuka</p></div>';
        el.onclick = () => { el.remove(); window.WAInbox.open(phone); };
        wrap.appendChild(el);
        while (wrap.children.length > 3) wrap.firstChild.remove();
        setTimeout(() => { if (el.parentNode) el.remove(); }, 8000);
    },

    paintTitle(totalUnread) {
        document.title = totalUnread > 0
            ? `(${totalUnread > 99 ? '99+' : totalUnread}) ${this.baseTitle}`
            : this.baseTitle;
    },

    tickHtml(tick) {
        // centang 1 = terkirim ke server, centang 2 hitam = sampai di HP, centang 2 biru = dibaca
        if (tick === 'read') return '<i class="fa-solid fa-check-double text-sky-300" title="Dibaca"></i>';
        if (tick === 'delivered') return '<i class="fa-solid fa-check-double text-slate-800" title="Sampai di HP"></i>';
        return '<i class="fa-solid fa-check text-brand-100" title="Terkirim ke server"></i>';
    },

    mediaHtml(m) {
        if (!m.media_url) return '';
        const url = m.media_url;
        if (m.media_kind === 'image') {
            return `<a href="${url}" target="_blank" rel="noopener"><img src="${url}" class="rounded-xl mb-1.5 max-h-64 w-auto border border-black/5" loading="lazy"></a>`;
        }
        if (m.media_kind === 'video') {
            return `<video src="${url}" controls class="rounded-xl mb-1.5 max-h-64 max-w-full border border-black/5"></video>`;
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
            // Deteksi pesan baru untuk toast + bunyi + title. Poll pertama
            // hanya jadi baseline (tidak bunyi untuk pesan lama).
            let totalUnread = 0;
            for (const c of data.data) {
                totalUnread += (c.unread || 0);
                const prev = this.seen[c.phone];
                if (this.baselineDone && (c.unread || 0) > 0 && prev !== undefined && prev !== c.at) {
                    if (c.phone !== this.active) {
                        this.toast(c.phone, c.name || c.phone, c.last_message || 'Pesan baru masuk');
                    }
                    this.beep();
                }
                this.seen[c.phone] = c.at;
            }
            this.baselineDone = true;
            this.paintTitle(totalUnread);
            // Sinkronkan badge sidebar dengan ritme yang sama (layout me-poll
            // sendiri tiap 30 detik, jadi tanpa ini angkanya bisa beda).
            try {
                const badge = document.getElementById('wa-unread-badge');
                if (badge) {
                    badge.textContent = totalUnread > 99 ? '99+' : totalUnread;
                    badge.style.display = totalUnread > 0 ? 'inline-flex' : 'none';
                }
            } catch (e) {}
            box.innerHTML = list.length === 0
                ? '<div class="p-8 text-center text-slate-400 text-sm"><i class="fa-regular fa-comment-dots text-2xl text-slate-300 mb-2 block"></i>Belum ada percakapan.<br>Balasan blast akan muncul di sini.</div>'
                : list.map(c => `
                    <button onclick="window.WAInbox.open('${c.phone}')" class="w-full text-left px-3 py-3 hover:bg-slate-50 transition flex items-center gap-3 border-l-4 ${this.active === c.phone ? 'border-brand-600 bg-brand-50/60' : 'border-transparent'}">
                        <div class="w-11 h-11 rounded-full ${c.unread > 0 ? 'bg-gradient-to-br from-brand-500 to-brand-600' : 'bg-slate-300'} text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-sm">
                            ${this.esc((c.name || c.phone || '?').substring(0, 1).toUpperCase())}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-semibold text-slate-900 text-sm truncate">${this.esc(c.name || c.phone)}</p>
                                ${c.unread > 0 ? `<span class="bg-[#25d366] text-white text-[11px] font-bold min-w-[22px] h-[22px] px-1.5 rounded-full flex items-center justify-center shrink-0 shadow-sm">${c.unread > 99 ? '99+' : c.unread}</span>` : ''}
                            </div>
                            <p class="text-xs text-slate-500 truncate mt-0.5">${c.direction === 'out' ? 'Anda: ' : ''}${this.esc(c.last_message)}</p>
                            <p class="text-[10px] text-slate-400 font-mono mt-0.5">${this.esc(c.customer_phone || ('+' + c.phone))} • ${this.esc(c.at || '')}</p>
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
                        ? 'max-w-[80%] bg-[#005c4b] text-white text-sm rounded-2xl rounded-br-md px-4 py-2.5 shadow-sm'
                        : 'max-w-[80%] bg-white text-slate-800 text-sm rounded-2xl rounded-bl-md px-4 py-2.5 shadow-sm border border-slate-100';
                    const stamp = side ? 'text-brand-100' : 'text-slate-400';
                    const tick = side ? this.tickHtml(m.tick) : '';
                    const by = side && m.replied_by ? `<p class="text-[10px] ${stamp} opacity-80 mb-1">↩ ${this.esc(m.replied_by)}</p>` : '';
                    return `<div class="${wrap}"><div class="${bubble}">${by}${media}<p class="break-words">${this.esc(m.message)}</p><p class="text-[10px] ${stamp} text-right mt-1">${this.esc(m.at || '')} ${tick}</p></div></div>`;
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
        const fileInput = document.getElementById('wa-attach-file');
        const text = input.value.trim();
        const file = fileInput && fileInput.files ? fileInput.files[0] : null;
        if ((!text && !file) || !this.active || this.sending) return false;
        if (file && file.size > 10 * 1024 * 1024) {
            alert('Ukuran file maksimal 10 MB.');
            return false;
        }
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
            const formData = new FormData();
            formData.append('phone', this.active);
            formData.append('message', text);
            if (file) formData.append('attachment', file);
            const response = await fetch('{{ route('crm.whatsapp.reply') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: formData,
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
                waClearAttach();
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

    normPhone(raw) {
        let d = String(raw || '').replace(/\D/g, '');
        if (d.startsWith('0')) d = '62' + d.substring(1);
        else if (d.startsWith('620')) d = '62' + d.substring(3);
        return d;
    },

    openNewModal() {
        document.getElementById('wa-new-modal').style.display = 'block';
        const input = document.getElementById('wa-new-search');
        input.value = '';
        if (!input.dataset.bound) {
            input.dataset.bound = '1';
            let t = null;
            input.addEventListener('input', () => {
                clearTimeout(t);
                t = setTimeout(() => window.WAInbox.searchNew(input.value), 350);
            });
        }
        this.searchNew('');
        setTimeout(() => input.focus(), 50);
    },

    closeNewModal() {
        document.getElementById('wa-new-modal').style.display = 'none';
    },

    async searchNew(q) {
        const box = document.getElementById('wa-new-results');
        box.innerHTML = '<div class="p-6 text-center text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin"></i> Mencari...</div>';
        try {
            const res = await fetch(`{{ route('crm.customers.index') }}?search=${encodeURIComponent(q || '')}&per_page=10`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            const rows = data.data || data || [];
            box.innerHTML = rows.length === 0
                ? '<div class="p-6 text-center text-slate-400 text-sm">Tidak ketemu. Coba kata kunci lain.</div>'
                : rows.map(c => {
                    const nums = [
                        { label: 'Utama', val: c.phone },
                        { label: 'Kantor', val: c.office_phone },
                        { label: 'Darurat', val: c.emergency_phone },
                    ].filter(n => n.val);
                    const btns = nums.map(n => {
                        const norm = this.normPhone(n.val);
                        return `<button onclick="window.WAInbox.startNew('${norm}')"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-mono bg-brand-50 text-brand-700 border border-brand-200 hover:bg-brand-100 transition">
                            <i class="fa-brands fa-whatsapp"></i>${this.esc(n.val)}
                            <span class="font-sans text-[10px] text-brand-500">${n.label}</span>
                        </button>`;
                    }).join('');
                    return `<div class="px-3 py-2.5 rounded-xl hover:bg-slate-50 transition">
                        <p class="font-semibold text-slate-900 text-sm truncate">${this.esc(c.name || '-')}</p>
                        <div class="flex flex-wrap gap-1.5 mt-1.5">${btns || '<span class="text-xs text-slate-400">Tanpa nomor</span>'}</div>
                    </div>`;
                }).join('');
        } catch (e) {
            box.innerHTML = '<div class="p-6 text-center text-slate-400 text-sm">Gagal memuat. Coba lagi.</div>';
        }
    },

    async startNew(phone) {
        this.closeNewModal();
        await this.open(phone);
    },

    openLinkModal() {
        if (!this.active) return;
        document.getElementById('wa-link-phone').textContent = '+' + this.active;
        document.getElementById('wa-link-modal').style.display = 'block';
        const input = document.getElementById('wa-link-search');
        input.value = '';
        if (!input.dataset.bound) {
            input.dataset.bound = '1';
            let t = null;
            input.addEventListener('input', () => {
                clearTimeout(t);
                t = setTimeout(() => window.WAInbox.searchLink(input.value), 350);
            });
        }
        this.searchLink('');
        setTimeout(() => input.focus(), 50);
    },

    closeLinkModal() {
        document.getElementById('wa-link-modal').style.display = 'none';
    },

    async searchLink(q) {
        const box = document.getElementById('wa-link-results');
        box.innerHTML = '<div class="p-6 text-center text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin"></i> Mencari...</div>';
        try {
            const res = await fetch(`{{ route('crm.customers.index') }}?search=${encodeURIComponent(q || '')}&per_page=10`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            const rows = data.data || data || [];
            box.innerHTML = rows.length === 0
                ? '<div class="p-6 text-center text-slate-400 text-sm">Tidak ketemu. Coba kata kunci lain.</div>'
                : rows.map(c => `
                    <button onclick="window.WAInbox.chooseLink(${c.id}, '${this.esc(c.name || '').replace(/'/g, "\\'")}')"
                        class="w-full text-left px-3 py-2.5 hover:bg-brand-50 rounded-xl transition flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0">
                            ${this.esc((c.name || '?').substring(0, 1).toUpperCase())}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-slate-900 text-sm truncate">${this.esc(c.name || '-')}</p>
                            <p class="text-xs text-slate-500 font-mono truncate">${this.esc(c.phone || '')}${c.company ? ' • ' + this.esc(c.company) : ''}</p>
                        </div>
                        <i class="fa-solid fa-link text-brand-500 text-xs shrink-0"></i>
                    </button>`).join('');
        } catch (e) {
            box.innerHTML = '<div class="p-6 text-center text-slate-400 text-sm">Gagal memuat. Coba lagi.</div>';
        }
    },

    async chooseLink(customerId, customerName) {
        if (!this.active) return;
        if (!confirm(`Tautkan percakapan ini ke ${customerName}?`)) return;
        try {
            const token = await this.freshToken();
            const res = await fetch('{{ route('crm.whatsapp.link') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token || this.csrfToken() },
                body: JSON.stringify({ phone: this.active, customer_id: customerId }),
            });
            const data = await res.json();
            if (data.status === 'success') {
                this.closeLinkModal();
                await this.loadThread();
                this.loadConvs();
            } else {
                alert(data.message || 'Gagal menautkan.');
            }
        } catch (e) {
            alert('Terjadi kesalahan jaringan.');
        }
    },

    async unlinkCustomer() {
        if (!this.active) return;
        if (!confirm('Putuskan tautan percakapan ini dari customer?')) return;
        try {
            const token = await this.freshToken();
            const res = await fetch('{{ route('crm.whatsapp.link') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token || this.csrfToken() },
                body: JSON.stringify({ phone: this.active, customer_id: null }),
            });
            const data = await res.json();
            if (data.status === 'success') {
                this.closeLinkModal();
                await this.loadThread();
                this.loadConvs();
            } else {
                alert(data.message || 'Gagal memutuskan.');
            }
        } catch (e) {
            alert('Terjadi kesalahan jaringan.');
        }
    },
};

function waBack() { window.WAInbox.waBack(); }
function waReply(e) { return window.WAInbox.reply(e); }
function waClearAttach() {
    const f = document.getElementById('wa-attach-file');
    if (f) f.value = '';
    const p = document.getElementById('wa-attach-preview');
    if (p) p.style.display = 'none';
}



function waInboxInit() {
    // Hentikan timer lama (mis. dari kunjungan Turbo sebelumnya) agar tidak dobel
    if (window.WAInbox.timer) clearInterval(window.WAInbox.timer);
    window.WAInbox.active = null;
    window.WAInbox.seen = {};
    window.WAInbox.baselineDone = false;
    window.WAInbox.paintTitle(0);
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
    const attachEl = document.getElementById('wa-attach-file');
    if (attachEl && !attachEl.dataset.bound) {
        attachEl.dataset.bound = '1';
        attachEl.addEventListener('change', () => {
            const f = attachEl.files ? attachEl.files[0] : null;
            const prev = document.getElementById('wa-attach-preview');
            if (f) {
                document.getElementById('wa-attach-name').textContent =
                    f.name + ' (' + (f.size / 1024).toFixed(0) + ' KB)';
                prev.style.display = 'block';
            } else if (prev) {
                prev.style.display = 'none';
            }
        });
    }
    window.WAInbox.timer = setInterval(() => {
        window.WAInbox.loadConvs();
        if (window.WAInbox.active) window.WAInbox.loadThread();
    }, 5000);
    // Bangunkan polling saat tab dibuka lagi / halaman di-back (timer
    // di-throttle browser saat tab tersembunyi sehingga daftar bisa basi).
    for (const evt of ['focus', 'pageshow']) {
        if (!window.WAInbox['_bound_' + evt]) {
            window.WAInbox['_bound_' + evt] = true;
            window.addEventListener(evt, () => {
                window.WAInbox.loadConvs();
                if (window.WAInbox.active) window.WAInbox.loadThread();
            });
        }
    }
    document.removeEventListener('visibilitychange', window.WAInbox._visHandler || (() => {}));
    window.WAInbox._visHandler = () => {
        if (!document.hidden) {
            window.WAInbox.loadConvs();
            if (window.WAInbox.active) window.WAInbox.loadThread();
        }
    };
    document.addEventListener('visibilitychange', window.WAInbox._visHandler);
}

document.addEventListener('DOMContentLoaded', waInboxInit);
// Turbo tidak memicu DOMContentLoaded saat navigasi
document.addEventListener('turbo:load', waInboxInit);
document.addEventListener('turbo:before-cache', () => {
    if (window.WAInbox && window.WAInbox.timer) clearInterval(window.WAInbox.timer);
});
</script>
@endsection