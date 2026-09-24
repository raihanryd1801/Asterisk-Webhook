@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-4 h-[calc(100vh-140px)] min-h-[540px]">
    <div class="bg-brand-50 border border-brand-100 rounded-2xl px-5 py-4 flex flex-wrap items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-brand-600 text-white flex items-center justify-center text-lg shadow-sm shrink-0">
            <i class="fa-solid fa-location-crosshairs"></i>
        </div>
        <div class="min-w-0 flex-1">
            <h1 class="text-xl font-bold text-brand-700 leading-tight">Live Tracking Collector</h1>
            <p class="text-sm text-brand-600/80 mt-0.5">Posisi real-time collector lapangan. Klik marker untuk jejak 30 titik terakhir.</p>
        </div>
        <div class="flex items-center gap-2 text-xs font-semibold">
            <span class="inline-flex items-center gap-1.5 bg-white border border-slate-200 rounded-full px-3 py-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
                <span id="track-online">0 online</span>
            </span>
            <span class="inline-flex items-center gap-1.5 bg-white border border-slate-200 rounded-full px-3 py-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-slate-400 inline-block"></span>
                <span id="track-offline">0 offline</span>
            </span>
            <span class="inline-flex items-center gap-1.5 bg-white border border-slate-200 rounded-full px-3 py-1.5 text-slate-500">
                <i class="fa-solid fa-satellite-dish text-brand-500"></i><span id="track-mode">polling</span>
            </span>
        </div>
    </div>

    <div class="flex-1 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
        <div id="tracking-map" class="w-full h-full z-0" style="min-height: 480px;"></div>
        <div class="absolute top-3 right-3 z-[500] bg-white/95 backdrop-blur border border-slate-200 rounded-xl shadow-lg px-3 py-2.5 space-y-2 text-xs w-52">
            <p class="font-bold text-slate-700 uppercase tracking-wider text-[10px]">Layer Peta</p>
            <label class="flex items-center gap-2 cursor-pointer text-slate-700">
                <input type="checkbox" id="track-ly-collector" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"> Collector (<span id="track-n-collector">0</span>)
            </label>
            <label class="flex items-center gap-2 cursor-pointer text-slate-700">
                <input type="checkbox" id="track-ly-customer" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"> Customer (<span id="track-n-customer">0</span>)
            </label>
            <select id="track-cust-filter" class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent" title="Saring pin customer per collector">
                <option value="">Semua customer</option>
            </select>
        </div>
        <p class="absolute left-0 right-0 bottom-0 px-4 py-1.5 text-[11px] text-slate-400 bg-white/85 border-t border-slate-100 z-[500]">© OpenStreetMap contributors • Online = posisi &lt; 5 menit • Jejak = 30 titik terakhir • <span class="text-blue-600">●</span> customer</p>
    </div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Guard window agar kunjungan ulang via Turbo tidak dobel init/timer.
window.trackMap = window.trackMap || null;
window.trackMarkers = window.trackMarkers || {};
window.trackMeta = window.trackMeta || {};
window.trackOtw = window.trackOtw || {};
window.trackTrail = window.trackTrail || null;
window.trackTimer = window.trackTimer || null;

window.trackEsc = window.trackEsc || function (s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
};

window.trackIcon = window.trackIcon || function (name, online) {
    const initial = (name || '?').trim().substring(0, 1).toUpperCase();
    const bg = online ? '#059669' : '#94a3b8';
    return L.divIcon({
        className: 'track-div-icon',
        html: `<div style="width:34px;height:34px;border-radius:50%;background:${bg};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.35);${online ? 'outline:2px solid #059669;outline-offset:2px;' : ''}">${initial}</div>`,
        iconSize: [34, 34],
        iconAnchor: [17, 17],
        popupAnchor: [0, -18],
    });
};

window.trackPopup = window.trackPopup || function (c) {
    const esc = window.trackEsc;
    const when = c.at ? new Date(c.at.replace(' ', 'T')).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '-';
    return `<strong>${esc(c.name || '-')}</strong><br>` +
        `<span style="color:#64748b;font-size:12px">${esc(c.area || '')} • ${esc(c.phone || '')}</span><br>` +
        `<span style="font-size:12px">📦 ${c.open_cases ?? 0} case jalan • ${c.online ? '🟢 online' : (c.tracking === false ? '🔴 tracking mati' : '⚪ offline')}</span><br>` +
        (c.active_visit ? `<span style="font-size:12px">🚗 → ${esc(c.active_visit.customer_name || ('#' + c.active_visit.customer_id))} (${esc(c.active_visit.status === 'sampai' ? 'sampai lokasi' : 'OTW')})</span><br>` : '') +
        `<span style="color:#94a3b8;font-size:11px">Terakhir: ${esc(when)}${c.accuracy ? ' • ±' + c.accuracy + ' m' : ''}</span><br>` +
        `<span style="color:#94a3b8;font-size:11px">Klik marker untuk jejak</span>`;
};

window.trackApply = window.trackApply || function (list) {
    const map = window.trackMap;
    if (!map || !Array.isArray(list)) return;
    let online = 0, offline = 0;
    const seen = new Set();
    const bounds = [];
    list.forEach(c => {
        if (c.latitude == null || c.longitude == null) { if (!c.online) offline++; return; }
        seen.add(c.id);
        c.online ? online++ : offline++;
        // Ingat meta terakhir (nama/telp/area) untuk dipakai event stop.
        const prev = window.trackMeta[c.id] || {};
        window.trackMeta[c.id] = {
            name: c.name ?? prev.name, phone: c.phone ?? prev.phone,
            area: c.area ?? prev.area, open_cases: c.open_cases ?? prev.open_cases,
        };
        const ll = [parseFloat(c.latitude), parseFloat(c.longitude)];
        bounds.push(ll);
        let mk = window.trackMarkers[c.id];
        if (!mk) {
            mk = L.marker(ll, { icon: window.trackIcon(c.name, c.online) }).addTo(map);
            mk.on('click', () => window.trackShowTrail(c.id));
            window.trackMarkers[c.id] = mk;
        } else {
            mk.setLatLng(ll);
            mk.setIcon(window.trackIcon(c.name, c.online));
        }
        mk.bindPopup(window.trackPopup(c));
        window.trackApplyOtw(map, c);
    });
    // Hapus marker collector yang sudah tidak aktif
    Object.keys(window.trackMarkers).forEach(id => {
        if (!seen.has(Number(id))) { try { map.removeLayer(window.trackMarkers[id]); } catch (e) {} delete window.trackMarkers[id]; }
        if (!seen.has(Number(id)) && window.trackOtw[id]) { try { map.removeLayer(window.trackOtw[id]); } catch (e) {} try { map.removeLayer(window.trackOtw[id]._destBadge); } catch (e) {} delete window.trackOtw[id]; }
    });
    document.getElementById('track-online').textContent = online + ' online';
    document.getElementById('track-offline').textContent = offline + ' offline';
    document.getElementById('track-n-collector').textContent = seen.size;
    // Opsi filter customer per collector (dibangun sekali setelah data live ada).
    try { window.trackBuildCustFilter(); } catch (e) {}
    // Toggle layer collector: sembunyikan marker (+ garis OTW) tanpa hapus data.
    const showCol = document.getElementById('track-ly-collector');
    const colOn = !showCol || showCol.checked;
    Object.values(window.trackMarkers).forEach(mk => {
        const has = map.hasLayer(mk);
        if (colOn && !has) mk.addTo(map);
        if (!colOn && has) { try { map.removeLayer(mk); } catch (e) {} }
    });
    Object.values(window.trackOtw).forEach(line => {
        const has = map.hasLayer(line);
        if (colOn && !has) line.addTo(map);
        if (!colOn && has) { try { map.removeLayer(line); } catch (e) {} }
        if (line._destBadge) {
            const hasB = map.hasLayer(line._destBadge);
            if (colOn && !hasB) line._destBadge.addTo(map);
            if (!colOn && hasB) { try { map.removeLayer(line._destBadge); } catch (e) {} }
        }
    });
    if (bounds.length && !window.trackFitted) {
        window.trackFitted = true;
        map.fitBounds(bounds, { padding: [40, 40] });
    }
};

window.trackApplyOtw = window.trackApplyOtw || function (map, c) {
    // Garis putus-putus collector -> customer tujuan + badge "-> nama".
    // Hilang otomatis saat visit selesai/batal atau customer tanpa titik.
    // Event live position tidak membawa kunci active_visit -> lewati agar
    // garis tidak berkedip (data lengkap datang via trackLoad sesudahnya).
    if (!('active_visit' in c)) return;
    const v = c.active_visit;
    const hasLine = v && c.latitude != null && v.latitude != null && v.longitude != null;
    let line = window.trackOtw[c.id];
    if (!hasLine) {
        if (line) {
            try { map.removeLayer(line); } catch (e) {}
            try { map.removeLayer(line._destBadge); } catch (e) {}
            delete window.trackOtw[c.id];
        }
        return;
    }
    const pts = [[parseFloat(c.latitude), parseFloat(c.longitude)], [parseFloat(v.latitude), parseFloat(v.longitude)]];
    const label = '→ ' + (v.customer_name || ('#' + v.customer_id));
    const badgeIcon = L.divIcon({
        className: 'track-otw-badge',
        html: `<div style="background:#1d4ed8;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;white-space:nowrap;box-shadow:0 2px 6px rgba(0,0,0,.35);border:2px solid #fff;">${window.trackEsc(label)}</div>`,
        iconSize: null,
        iconAnchor: [-8, -8],
    });
    if (!line) {
        line = L.polyline(pts, { color: '#2563eb', weight: 2.5, opacity: 0.9, dashArray: '6 8' }).addTo(map);
        line._destBadge = L.marker(pts[1], { icon: badgeIcon, interactive: false, keyboard: false }).addTo(map);
        window.trackOtw[c.id] = line;
    } else {
        line.setLatLngs(pts);
        line._destBadge.setLatLng(pts[1]);
        line._destBadge.setIcon(badgeIcon);
    }
};

window.trackCustLayer = window.trackCustLayer || null;
window.trackCustCache = window.trackCustCache || null;
window.trackCustFilterInit = window.trackCustFilterInit || false;

window.trackLoadCustomers = window.trackLoadCustomers || async function () {
    // Pin customer (sama seperti peta menu Customers) — di-cache, di-refresh tiap 60 detik.
    try {
        if (!window.trackCustCache) {
            const res = await fetch('{{ route('crm.customers.map-points') }}', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            window.trackCustCache = (data.data || []).filter(p => p.latitude != null && p.longitude != null);
        }
        window.trackPaintCustomers();
        window.trackBuildCustFilter();
    } catch (e) {}
};

window.trackBuildCustFilter = window.trackBuildCustFilter || function () {
    // Opsi filter cukup dibangun sekali (jangan reset pilihan user tiap polling).
    if (window.trackCustFilterInit) return;
    const live = Object.values(window.trackMeta || {});
    if (!live.length) return;
    const sel = document.getElementById('track-cust-filter');
    if (!sel) return;
    const cur = sel.value;
    const ids = [...new Set((window.trackCustCache || []).map(p => p.collector_id).filter(Boolean))];
    sel.innerHTML = '<option value="">Semua customer</option>' + ids.map(id => {
        const meta = Object.entries(window.trackMeta).find(([cid]) => Number(cid) === Number(id));
        const nm = (meta && meta[1].name) ? meta[1].name : ('Collector #' + id);
        return `<option value="${id}">${window.trackEsc(nm)}</option>`;
    }).join('');
    sel.value = cur;
    window.trackCustFilterInit = true;
};

window.trackPaintCustomers = window.trackPaintCustomers || function () {
    const map = window.trackMap;
    if (!map) return;
    if (!window.trackCustLayer) window.trackCustLayer = L.layerGroup().addTo(map);
    const showCust = document.getElementById('track-ly-customer');
    if (showCust && !showCust.checked) {
        window.trackCustLayer.clearLayers();
        document.getElementById('track-n-customer').textContent = (window.trackCustCache || []).length;
        return;
    }
    const filterEl = document.getElementById('track-cust-filter');
    const f = filterEl ? filterEl.value : '';
    const esc = window.trackEsc;
    window.trackCustLayer.clearLayers();
    let n = 0;
    (window.trackCustCache || []).forEach(p => {
        if (f && String(p.collector_id || '') !== String(f)) return;
        const lat = parseFloat(p.latitude), lng = parseFloat(p.longitude);
        if (isNaN(lat) || isNaN(lng)) return;
        n++;
        const gmaps = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(lat + ',' + lng);
        L.circleMarker([lat, lng], {
            radius: 6, color: '#ffffff', weight: 2,
            fillColor: p.payment_status === 'unpaid' ? '#dc2626' : (p.payment_status === 'partial' ? '#d97706' : '#2563eb'),
            fillOpacity: 0.9,
        }).bindPopup('<strong>' + esc(p.name || '-') + '</strong><br>' +
            esc(p.address || '') + '<br>' +
            '<span>' + esc(p.phone || '') + (p.bucket ? ' • ' + esc(p.bucket) : '') + '</span><br>' +
            '<a href="' + gmaps + '" target="_blank" rel="noopener">Rute →</a>'
        ).addTo(window.trackCustLayer);
    });
    document.getElementById('track-n-customer').textContent = n;
};

window.trackShowTrail = window.trackShowTrail || async function (id) {    const map = window.trackMap;
    if (!map) return;
    window.trackTrailFor = id;
    window.trackTrailAt = Date.now();
    try {
        const res = await fetch(`{{ url('/dashboard/crm/tracking/trail') }}/${id}`, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        const pts = (data.data || []).map(p => [parseFloat(p.latitude), parseFloat(p.longitude)]).filter(p => !isNaN(p[0]));
        if (window.trackTrail) { try { map.removeLayer(window.trackTrail); } catch (e) {} window.trackTrail = null; }
        if (pts.length > 1) {
            window.trackTrail = L.polyline(pts, { color: '#059669', weight: 3, opacity: 0.8, dashArray: '8 6' }).addTo(map);
            map.fitBounds(window.trackTrail.getBounds(), { padding: [40, 40] });
        }
    } catch (e) {}
};

window.trackLoad = window.trackLoad || async function () {
    try {
        const res = await fetch('{{ route('crm.tracking.live') }}', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (data.status === 'success') window.trackApply(data.data);
    } catch (e) {}
};

function trackInit() {
    if (window.trackTimer) clearInterval(window.trackTimer);
    const el = document.getElementById('tracking-map');
    if (!el || typeof L === 'undefined') return;
    // Turbo mengganti seluruh <body> tiap pindah menu, tapi object window
    // tetap hidup. Map lama menempel ke container LAMA yang sudah dibuang —
    // wajib dibuang dan dibuat ulang, kalau tidak div baru kosong blank
    // (inilah kenapa harus hard-refresh).
    if (window.trackMap && window.trackMap.getContainer() !== el) {
        try { window.trackMap.remove(); } catch (e) {}
        window.trackMap = null;
        window.trackMarkers = {};
        window.trackOtw = {};
        window.trackCustLayer = null;
        window.trackTrail = null;
        window.trackFitted = false;
    }
    if (!window.trackMap) {
        window.trackMap = L.map('tracking-map').setView([-2.5, 118], 5);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(window.trackMap);
        window.trackFitted = false;
        // Realtime via Reverb bila WebSocket benar-benar tersambung.
        // PENTING: badge "live" hanya boleh nyala saat koneksi TERBUKTI
        // (event pusher:connected), bukan saat subscribe — kalau tidak,
        // badge berbohong padahal data cuma dari polling.
        try {
            if (window.Echo) {
                const pusher = window.Echo.connector && window.Echo.connector.pusher;
                const setMode = (live) => {
                    const m = document.getElementById('track-mode');
                    if (m) m.textContent = live ? 'live ⚡' : 'polling';
                };
                setMode(false); // jujur sejak awal: polling sampai terbukti live
                if (pusher && pusher.connection) {
                    pusher.connection.bind('connected', () => { setMode(true); window.trackLoad(); });
                    pusher.connection.bind('disconnected', () => setMode(false));
                    pusher.connection.bind('failed', () => setMode(false));
                    pusher.connection.bind('unavailable', () => setMode(false));
                }
                window.Echo.channel('tracking.live')
                    .listen('.collector.position.updated', (e) => {
                        // Event masuk = buktinya koneksi live (pengaman ganda).
                        const m = document.getElementById('track-mode');
                        if (m) m.textContent = 'live ⚡';
                        // Geser marker seketika, lalu sinkronkan data lengkap (open_cases dsb).
                        window.trackApply([{
                            id: e.collector_id, name: e.name, phone: e.phone, area: e.area,
                            online: true, tracking: true, latitude: e.latitude, longitude: e.longitude,
                            accuracy: e.accuracy, at: e.at,
                        }]);
                        window.trackLoad();
                        // Kalau jejak collector ini sedang dibuka, ikutkan titik
                        // barunya (dibatasi 1x/10 dtk agar tidak spam request).
                        if (window.trackTrailFor === e.collector_id && (Date.now() - (window.trackTrailAt || 0)) > 10000) {
                            window.trackShowTrail(e.collector_id);
                        }
                    })
                    .listen('.collector.tracking.stopped', (e) => {
                        // Hentikan tracking = abu SEKEJAP tanpa tunggu timeout.
                        const mk = window.trackMarkers[e.collector_id];
                        const meta = window.trackMeta[e.collector_id] || {};
                        if (mk) {
                            const ll = mk.getLatLng();
                            window.trackApply([{
                                id: e.collector_id, ...meta,
                                online: false, tracking: false,
                                latitude: ll.lat, longitude: ll.lng, at: null,
                            }]);
                        }
                        window.trackLoad();
                    });
                document.getElementById('track-mode').textContent = 'live ⚡';
            }
        } catch (e) {}
    } else {
        try { window.trackMap.invalidateSize(); } catch (e) {}
    }
    window.trackLoad();
    window.trackTimer = setInterval(window.trackLoad, 5000);
    // Layer customer: muat sekali, segarkan tiap 60 detik (data jarang berubah).
    window.trackLoadCustomers();
    if (window.trackCustTimer) clearInterval(window.trackCustTimer);
    window.trackCustTimer = setInterval(() => { window.trackCustCache = null; window.trackLoadCustomers(); }, 60000);
    // Kontrol layer (bind ulang tiap kunjungan Turbo — elemennya baru).
    ['track-ly-collector', 'track-ly-customer'].forEach(id => {
        const c = document.getElementById(id);
        if (c && !c.dataset.bound) {
            c.dataset.bound = '1';
            c.addEventListener('change', () => { window.trackLoad(); window.trackPaintCustomers(); });
        }
    });
    const f = document.getElementById('track-cust-filter');
    if (f && !f.dataset.bound) {
        f.dataset.bound = '1';
        f.addEventListener('change', () => window.trackPaintCustomers());
    }
}

document.addEventListener('DOMContentLoaded', trackInit);
document.addEventListener('turbo:load', trackInit);
document.addEventListener('turbo:before-cache', () => {
    if (window.trackTimer) clearInterval(window.trackTimer);
    if (window.trackCustTimer) clearInterval(window.trackCustTimer);
});
</script>
@endsection
