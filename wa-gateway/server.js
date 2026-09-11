'use strict';

/**
 * WA Gateway — sidecar Baileys multi-session.
 * Satu sesi per admin/SPV (id mis. "user:3", "spv:102").
 * Laravel yang mengatur siapa pemilik sesi; gateway hanya menyimpan koneksi.
 */

const express = require('express');
const QRCode = require('qrcode');
const path = require('path');
const fs = require('fs');

const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
} = require('@whiskeysockets/baileys');

const PORT = parseInt(process.env.WA_PORT || '3001', 10);
const TOKEN = process.env.WA_GATEWAY_TOKEN || '';
const LARAVEL_URL = (process.env.LARAVEL_URL || 'http://127.0.0.1:8020').replace(/\/$/, '');
const MIN_DELAY = parseInt(process.env.WA_MIN_DELAY_MS || '3000', 10);
const MAX_DELAY = parseInt(process.env.WA_MAX_DELAY_MS || '7000', 10);
const SESSIONS_DIR = path.join(__dirname, 'sessions');

if (!fs.existsSync(SESSIONS_DIR)) fs.mkdirSync(SESSIONS_DIR, { recursive: true });

const sessions = new Map(); // id -> { sock, qr, status, phone, queue }

const app = express();
app.use(express.json({ limit: '1mb' }));

// ---- auth sederhana antar service ----
app.use((req, res, next) => {
    if (req.path === '/health') return next();
    if (!TOKEN || req.header('X-Gateway-Token') !== TOKEN) {
        return res.status(401).json({ ok: false, message: 'Unauthorized gateway' });
    }
    next();
});

function sleep(ms) {
    return new Promise((r) => setTimeout(r, ms));
}

function randDelay() {
    const lo = Math.min(MIN_DELAY, MAX_DELAY);
    const hi = Math.max(MIN_DELAY, MAX_DELAY);
    return lo + Math.floor(Math.random() * (hi - lo + 1));
}

function sessionDir(id) {
    return path.join(SESSIONS_DIR, encodeURIComponent(id));
}

// Penyimpanan pesan singkat per sesi agar retry dekripsi bisa dipenuhi.
// Tanpa ini, pesan yang gagal dibuka akan stuck "Waiting for this message".
// Disimpan ke disk agar tetap bisa memenuhi retry walau gateway restart.
const MAX_STORED_PER_CHAT = 200;
const MAX_STORED_CHATS = 150;
const MSG_STORE_FILE = path.join(__dirname, 'message-store.json');
const messageStores = new Map(); // sessionId -> Map<remoteJid, Map<msgId, message>>
let msgStoreDirty = false;
let msgStoreLastSave = 0;

function loadMessageStore() {
    try {
        if (!fs.existsSync(MSG_STORE_FILE)) return;
        const raw = JSON.parse(fs.readFileSync(MSG_STORE_FILE, 'utf8'));
        for (const [sid, chats] of Object.entries(raw)) {
            const chatMap = new Map();
            for (const [jid, msgs] of Object.entries(chats || {})) {
                chatMap.set(jid, new Map(Object.entries(msgs || {})));
            }
            messageStores.set(sid, chatMap);
        }
    } catch (e) {}
}

function saveMessageStore() {
    try {
        const now = Date.now();
        if (now - msgStoreLastSave < 5000) return; // throttle tulis disk
        msgStoreLastSave = now;
        const raw = {};
        for (const [sid, chats] of messageStores) {
            raw[sid] = {};
            for (const [jid, msgs] of chats) {
                raw[sid][jid] = Object.fromEntries(msgs);
            }
        }
        fs.writeFileSync(MSG_STORE_FILE + '.tmp', JSON.stringify(raw));
        fs.renameSync(MSG_STORE_FILE + '.tmp', MSG_STORE_FILE);
    } catch (e) {}
}

loadMessageStore();

function storeMessage(sessionId, msg) {
    try {
        const jid = msg.key?.remoteJid;
        const id = msg.key?.id;
        if (!jid || !id || !msg.message) return;
        let chats = messageStores.get(sessionId);
        if (!chats) {
            chats = new Map();
            messageStores.set(sessionId, chats);
        }
        let chat = chats.get(jid);
        if (!chat) {
            chat = new Map();
            chats.set(jid, chat);
            while (chats.size > MAX_STORED_CHATS) {
                chats.delete(chats.keys().next().value);
            }
        }
        chat.set(id, msg.message);
        // Batasi memori: buang yang paling lama
        while (chat.size > MAX_STORED_PER_CHAT) {
            chat.delete(chat.keys().next().value);
        }
        msgStoreDirty = true;
        saveMessageStore();
    } catch (e) {}
}

/** Ambil nomor telepon pengirim dari field senderPn bila ada. */
function senderPhoneNumber(msg) {
    try {
        let pn = msg.key?.senderPn || '';
        pn = String(pn).split('@')[0].replace(/\D/g, '');
        return /^\d{9,16}$/.test(pn) ? pn : '';
    } catch (e) {
        return '';
    }
}

async function recallMessage(sessionId, key) {
    try {
        return messageStores.get(sessionId)?.get(key?.remoteJid)?.get(key?.id) || undefined;
    } catch (e) {
        return undefined;
    }
}

function normalizePhone(raw) {
    let d = String(raw || '').replace(/\D/g, '');
    if (d.startsWith('0')) d = '62' + d.slice(1);
    if (d.startsWith('620')) d = '62' + d.slice(3);
    return d;
}

function getSession(id) {
    return sessions.get(id) || null;
}

function publicState(id) {
    const s = sessions.get(id);
    if (!s) return { id, status: 'none', phone: null, qr: null };
    return { id, status: s.status, phone: s.phone, qr: s.status === 'connected' ? null : s.qr };
}

async function startSession(id) {
    let s = sessions.get(id);
    if (s && (s.status === 'connected' || s.status === 'connecting' || s.status === 'qr')) {
        return s;
    }
    s = { sock: null, qr: null, status: 'connecting', phone: null, queue: Promise.resolve() };
    sessions.set(id, s);

    const { state, saveCreds } = await useMultiFileAuthState(sessionDir(id));
    const { version } = await fetchLatestBaileysVersion().catch(() => ({ version: [2, 3000, 0] }));

    const sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        browser: ['SKYKOM Collection', 'Chrome', '1.0'],
        syncFullHistory: false,
        markOnlineOnConnect: false,
        // Wajib agar "Waiting for this message" bisa dipulihkan via retry
        getMessage: async (key) => recallMessage(id, key),
    });
    s.sock = sock;

    sock.ev.on('creds.update', saveCreds);

    // Teruskan pesan MASUK ke Laravel (abaikan pesan sendiri & status).
    // Media (gambar/video/dokumen/audio) diunduh lalu dikirim multipart.
    const { downloadMediaMessage } = require('@whiskeysockets/baileys');
    const MAX_MEDIA_BYTES = 8 * 1024 * 1024;

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') return;
        for (const msg of messages || []) {
            storeMessage(id, msg);
            try {
                if (!msg.message || msg.key?.fromMe) continue;
                // Baileys baru mengirim JID samaran @lid; nomor asli ada di remoteJidAlt.
                // Utamakan JID nomor telepon, tapi JANGAN buang pesan murni @lid
                // (tanpa padanan nomor) — tetap teruskan dengan server aslinya
                // agar balasan bisa kembali ke alamat yang tepat.
                const alt = msg.key?.remoteJidAlt || '';
                let remote = msg.key?.remoteJid || '';
                let server = null;
                if (alt.endsWith('@s.whatsapp.net')) {
                    remote = alt;
                    server = 's.whatsapp.net';
                } else if (remote.endsWith('@s.whatsapp.net')) {
                    server = 's.whatsapp.net';
                } else if (remote.endsWith('@lid')) {
                    // Coba petakan ke nomor asli via identitas pengirim;
                    // kalau tidak ada, tetap teruskan sebagai LID.
                    const pn = senderPhoneNumber(msg);
                    if (pn) {
                        remote = `${pn}@s.whatsapp.net`;
                        server = 's.whatsapp.net';
                    } else {
                        server = 'lid';
                    }
                } else {
                    continue;
                }
                const m = msg.message;
                const mediaM = m.imageMessage || m.videoMessage || m.documentMessage || m.audioMessage || m.stickerMessage || null;
                const text =
                    m.conversation ||
                    m.extendedTextMessage?.text ||
                    m.imageMessage?.caption ||
                    m.videoMessage?.caption ||
                    m.documentMessage?.caption ||
                    '';
                let mediaBuffer = null;
                let mediaMime = '';
                let mediaKind = '';
                if (mediaM) {
                    try {
                        mediaMime = mediaM.mimetype || 'application/octet-stream';
                        if (m.imageMessage) mediaKind = 'image';
                        else if (m.videoMessage) mediaKind = 'video';
                        else if (m.audioMessage) mediaKind = 'audio';
                        else if (m.stickerMessage) mediaKind = 'sticker';
                        else mediaKind = 'document';
                        const buf = await downloadMediaMessage(msg, 'buffer', {});
                        if (buf && buf.length > 0 && buf.length <= MAX_MEDIA_BYTES) {
                            mediaBuffer = Buffer.from(buf);
                        } else if (buf && buf.length > MAX_MEDIA_BYTES) {
                            console.log(`[${id}] media too large (${buf.length} bytes), text only`);
                        }
                    } catch (e) {
                        console.error(`[${id}] media download failed:`, e.message);
                    }
                }
                if (!String(text).trim() && !mediaBuffer) continue;
                const ts = msg.messageTimestamp
                    ? new Date(Number(msg.messageTimestamp) * 1000).toISOString()
                    : new Date().toISOString();
                const baseFields = {
                    session_id: id,
                    remote_jid: remote,
                    remote_server: server,
                    push_name: msg.pushName || '',
                    message_id: msg.key?.id || '',
                    text: String(text).slice(0, 4000),
                    occurred_at: ts,
                };
                if (mediaBuffer) {
                    const ext = (mediaMime.split('/')[1] || 'bin').split(';')[0].replace(/[^a-z0-9]/gi, '') || 'bin';
                    const form = new FormData();
                    for (const [k, v] of Object.entries(baseFields)) form.append(k, v);
                    form.append('media_kind', mediaKind);
                    form.append('media', new Blob([mediaBuffer], { type: mediaMime }), `wa-${Date.now()}.${ext}`);
                    await fetch(`${LARAVEL_URL}/api/wa/inbound`, {
                        method: 'POST',
                        headers: { 'X-Gateway-Token': TOKEN },
                        body: form,
                    }).catch(() => {});
                } else {
                    await fetch(`${LARAVEL_URL}/api/wa/inbound`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Gateway-Token': TOKEN,
                        },
                        body: JSON.stringify(baseFields),
                    }).catch(() => {});
                }
            } catch (e) {
                console.error(`[${id}] inbound forward failed:`, e.message);
            }
        }
    });

    sock.ev.on('connection.update', async (u) => {
        const { connection, lastDisconnect, qr } = u;

        if (qr) {
            try {
                s.qr = await QRCode.toDataURL(qr);
            } catch (e) {
                s.qr = null;
            }
            if (s.status !== 'connected') s.status = 'qr';
        }

        if (connection === 'open') {
            s.status = 'connected';
            s.qr = null;
            const raw = (sock.user && sock.user.id) || '';
            s.phone = raw.split(':')[0].split('@')[0] || null;
            console.log(`[${id}] connected as ${s.phone}`);
        }

        if (connection === 'close') {
            const code = lastDisconnect?.error?.output?.statusCode;
            const loggedOut = code === DisconnectReason.loggedOut;
            console.log(`[${id}] closed (code=${code})`);
            try { sock.ev.removeAllListeners(); } catch (e) {}
            sessions.delete(id);
            if (loggedOut) {
                // Kredensial mati — hapus agar scan berikutnya fresh
                fs.rmSync(sessionDir(id), { recursive: true, force: true });
                console.log(`[${id}] logged out, auth cleared`);
            } else {
                // Reconnect otomatis dengan QR baru bila perlu
                setTimeout(() => {
                    startSession(id).catch((e) => console.error(`[${id}] reconnect failed:`, e.message));
                }, 3000);
            }
        }
    });

    return s;
}

app.get('/health', (req, res) => res.json({ ok: true }));

// Buat/mulai sesi (idempoten)
app.post('/sessions', async (req, res) => {
    const id = String(req.body.id || '').trim();
    if (!id || id.length > 64 || !/^[\w:.@-]+$/.test(id)) {
        return res.status(422).json({ ok: false, message: 'id sesi tidak valid' });
    }
    try {
        await startSession(id);
        // Tunggu sebentar agar QR sempat generate
        for (let i = 0; i < 20; i++) {
            const st = publicState(id);
            if (st.status === 'qr' || st.status === 'connected') break;
            await sleep(500);
        }
        res.json({ ok: true, session: publicState(id) });
    } catch (e) {
        res.status(500).json({ ok: false, message: e.message });
    }
});

app.get('/sessions/:id', (req, res) => {
    res.json({ ok: true, session: publicState(req.params.id) });
});

// Logout + hapus sesi
app.delete('/sessions/:id', async (req, res) => {
    const id = req.params.id;
    const s = sessions.get(id);
    try {
        if (s && s.sock) {
            try { await s.sock.logout(); } catch (e) {}
            try { s.sock.ev.removeAllListeners(); } catch (e) {}
        }
    } finally {
        sessions.delete(id);
        fs.rmSync(sessionDir(id), { recursive: true, force: true });
    }
    res.json({ ok: true });
});

// Kirim pesan via sesi (antre + jeda acak anti rate-limit)
app.post('/sessions/:id/send', async (req, res) => {
    const id = req.params.id;
    const s = sessions.get(id);
    if (!s || s.status !== 'connected' || !s.sock) {
        return res.status(409).json({ ok: false, message: 'Sesi belum terhubung. Scan QR dulu.' });
    }
    const server = req.body.server === 'lid' ? 'lid' : 's.whatsapp.net';
    const to = server === 'lid'
        ? String(req.body.to || '').replace(/\D/g, '')
        : normalizePhone(req.body.to);
    const message = String(req.body.message || '').trim();
    if (!/^\d{9,16}$/.test(to)) {
        return res.status(422).json({ ok: false, message: 'Nomor tujuan tidak valid' });
    }
    if (!message) {
        return res.status(422).json({ ok: false, message: 'Pesan kosong' });
    }

    const jid = `${to}@${server}`;
    s.queue = s.queue.then(async () => {
        await sleep(randDelay());
        await s.sock.sendMessage(jid, { text: message });
    });

    try {
        await s.queue;
        res.json({ ok: true, to });
    } catch (e) {
        res.status(502).json({ ok: false, to, message: e.message || 'Gagal kirim' });
    }
});

app.listen(PORT, '127.0.0.1', () => {
    console.log(`WA gateway listening on 127.0.0.1:${PORT}`);
});
