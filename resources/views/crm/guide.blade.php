@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-6 max-w-5xl">

    <div>
        <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
            <i class="fa-solid fa-book-open text-brand-600"></i> Panduan CRM & Collection
        </h1>
        <p class="text-slate-500 mt-1">Penjelasan semua fitur CRM, Collection Banking, dan Auto-Dialer — dibaca saat lupa.</p>
    </div>

    <!-- Daftar isi -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <h2 class="font-bold text-slate-800 mb-3">Daftar Isi</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
            <a href="#alur" class="text-brand-700 hover:underline">1. Alur besar: CRM → Collection → Collector</a>
            <a href="#istilah" class="text-brand-700 hover:underline">2. Istilah penting (Bucket, DPD, PTP, Debtor)</a>
            <a href="#menu" class="text-brand-700 hover:underline">3. Fungsi tiap menu</a>
            <a href="#ptp" class="text-brand-700 hover:underline">4. Alur status PTP</a>
            <a href="#bayar" class="text-brand-700 hover:underline">5. Status pembayaran</a>
            <a href="#premium" class="text-brand-700 hover:underline">6. Gembok Premium & akun Superadmin</a>
            <a href="#masalah" class="text-brand-700 hover:underline">7. Masalah umum & solusinya</a>
        </div>
    </div>

    <!-- 1. Alur -->
    <div id="alur" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 scroll-mt-4">
        <h2 class="text-lg font-bold text-slate-800 mb-3">1. Alur besar: CRM → Collection → Collector</h2>
        <ol class="list-decimal list-inside space-y-2 text-sm text-slate-600 leading-relaxed">
            <li><strong class="text-slate-800">Input debtor di Customers</strong> — nama, phone, total tagihan, dan <strong>jatuh tempo (due date)</strong>. Bucket/DPD/risk dihitung otomatis dari due date.</li>
            <li><strong class="text-slate-800">Daftarkan debt collector di Debt Collectors</strong> — tim lapangan / desk yang menagih (terpisah dari agent call center).</li>
            <li><strong class="text-slate-800">Assign case ke collector</strong> — manual per customer (form Edit), massal via checkbox + <strong>Bulk Assign</strong>, atau otomatis via <strong>Auto-Assign</strong> (bagi rata per bucket).</li>
            <li><strong class="text-slate-800">Blast per bucket bila perlu</strong> — tombol Blast di halaman Buckets (WA/SMS, tercatat sebagai log).</li>
            <li><strong class="text-slate-800">Agent menagih dari Workspace</strong> — list customer assigned + tombol Call (auto isi dialer) & buat PTP, catat hasil di Call History.</li>
            <li><strong class="text-slate-800">Kelola janji di PTP Management</strong> — tandai ditepati (✓) atau gagal (✕), ubah nominal/tanggal via ✏️.</li>
            <li><strong class="text-slate-800">Catat pembayaran di Customers</strong> — isi paid amount / diskon; status bayar ter-update otomatis.</li>
            <li><strong class="text-slate-800">Monitor di Collection Dashboard</strong> — bucket aging, performa collector, PTP, jatuh tempo 7 hari, Top NPL, SLA breach.</li>
        </ol>
    </div>

    <!-- 2. Istilah -->
    <div id="istilah" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 scroll-mt-4">
        <h2 class="text-lg font-bold text-slate-800 mb-3">2. Istilah penting</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Istilah</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Arti</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-slate-600">
                    <tr><td class="px-3 py-2 font-medium text-slate-800">Debtor / Borrower</td><td class="px-3 py-2">Customer yang punya hutang (di tabel disebut Customer).</td></tr>
                    <tr><td class="px-3 py-2 font-medium text-slate-800">DPD</td><td class="px-3 py-2"><em>Days Past Due</em> — jumlah hari keterlambatan dari due date. Dihitung otomatis.</td></tr>
                    <tr><td class="px-3 py-2 font-medium text-slate-800">Bucket</td><td class="px-3 py-2">Kelompok umur tunggakan: Current (belum tempo) → Bucket 1 (1–30) → Bucket 2 (31–60) → Bucket 3 (61–90) → NPL (90+).</td></tr>
                    <tr><td class="px-3 py-2 font-medium text-slate-800">Collector / Debt Collector</td><td class="px-3 py-2">Penagih dari tabel sendiri (menu Debt Collectors, bukan agent). <strong>Desk</strong> = nagih via telepon (Bucket 1–2). <strong>Field</strong> = orang lapangan, kunjungan langsung (Bucket 3/NPL). Opsional per customer.</td></tr>
                    <tr><td class="px-3 py-2 font-medium text-slate-800">PTP</td><td class="px-3 py-2"><em>Promise to Pay</em> — janji bayar: nominal + tanggal + catatan.</td></tr>
                    <tr><td class="px-3 py-2 font-medium text-slate-800">Aging</td><td class="px-3 py-2">Laporan sebaran case per bucket.</td></tr>
                    <tr><td class="px-3 py-2 font-medium text-slate-800">SLA / Eskalasi</td><td class="px-3 py-2">Batas DPD per bucket; case yang melewati batas otomatis naik risk-nya saat <em>Run SLA Check</em>.</td></tr>
                    <tr><td class="px-3 py-2 font-medium text-slate-800">Kolektabilitas</td><td class="px-3 py-2">% tagihan yang tertagih = (terbayar + diskon) / total tagihan.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. Menu -->
    <div id="menu" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 scroll-mt-4">
        <h2 class="text-lg font-bold text-slate-800 mb-3">3. Fungsi tiap menu</h2>
        <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
            <div>
                <p class="font-bold text-slate-800"><i class="fa-solid fa-chart-pie text-brand-600 w-5"></i> Dashboard Agent (CRM)</p>
                <p>Ringkasan CRM: total customer, tagihan, terbayar, diskon, sisa, kolektabilitas, top customer, performa agent, tren 6 bulan.</p>
            </div>
            <div>
                <p class="font-bold text-slate-800"><i class="fa-solid fa-users text-brand-600 w-5"></i> Customers</p>
                <p>CRUD debtor + tagihan + pembayaran + collection (due date, collector, risk). Fitur: filter (status bayar/bucket/agent/handover), checkbox + <strong>Bulk Assign Collector</strong>, <strong>Recalculate Bucket</strong>, <strong>Export/Import Excel</strong>, handover pihak ketiga, call history per customer.</p>
            </div>
            <div>
                <p class="font-bold text-slate-800"><i class="fa-solid fa-layer-group text-brand-600 w-5"></i> Collection Dashboard</p>
                <p>Monitoring collection: bucket aging, sebaran risiko, performa collector, statistik PTP, jatuh tempo 7 hari, Top NPL, SLA breach + tombol <strong>Auto-Assign Collector</strong> dan <strong>Run SLA Check</strong>.</p>
            </div>
            <div>
                <p class="font-bold text-slate-800"><i class="fa-solid fa-boxes-stacked text-brand-600 w-5"></i> Buckets</p>
                <p>Ringkasan per bucket (rentang DPD bisa diatur) + tombol <strong>Lihat</strong> untuk buka Customers yang sudah terfilter + tombol <strong>Blast</strong> WA/SMS per bucket.</p>
            </div>
            <div>
                <p class="font-bold text-slate-800"><i class="fa-solid fa-handshake text-brand-600 w-5"></i> PTP Management</p>
                <p>Kelola janji bayar dengan tab Active/Overdue/Kept/Broken/All. Tombol ✓ (ditepati), ✕ (gagal), ✏️ (ubah nominal/tanggal — sekaligus mengaktifkan ulang PTP).</p>
            </div>
            <div>
                <p class="font-bold text-slate-800"><i class="fa-solid fa-headset text-brand-600 w-5"></i> Agent Workspace (login agent)</p>
                <p>Section <strong>Customer Assigned</strong>: list tagihan + tombol Call (auto isi nomor & panggil via SIP), plus riwayat & catatan panggilan.</p>
            </div>
        </div>
    </div>

    <!-- 4. PTP -->
    <div id="ptp" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 scroll-mt-4">
        <h2 class="text-lg font-bold text-slate-800 mb-3">4. Alur status PTP</h2>
        <div class="text-sm text-slate-600 leading-relaxed space-y-2">
            <p>Status yang tersimpan hanya 3: <code class="bg-slate-100 px-1.5 py-0.5 rounded font-mono text-xs">pending</code>, <code class="bg-slate-100 px-1.5 py-0.5 rounded font-mono text-xs">kept</code>, <code class="bg-slate-100 px-1.5 py-0.5 rounded font-mono text-xs">broken</code>. Label <strong>Active</strong> dan <strong>Overdue</strong> dihitung dari tanggal:</p>
            <ul class="list-disc list-inside space-y-1">
                <li>Buat/ubah PTP → <strong>pending</strong> (tanggal ≥ hari ini = tampil <strong>Active</strong>; tanggal &lt; hari ini = tampil <strong>Overdue</strong> otomatis).</li>
                <li>Tombol <strong>✓</strong> → <strong>kept</strong> (janji ditepati).</li>
                <li>Tombol <strong>✕</strong> → <strong>broken</strong> (janji gagal).</li>
                <li>Tombol <strong>✏️ Edit</strong> → ubah nominal/tanggal, status kembali <strong>pending</strong>.</li>
            </ul>
            <p class="text-xs text-slate-400">Catatan: pembayaran lunas tidak otomatis menandai PTP kept — masih manual via tombol ✓.</p>
        </div>
    </div>

    <!-- 5. Bayar -->
    <div id="bayar" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 scroll-mt-4">
        <h2 class="text-lg font-bold text-slate-800 mb-3">5. Status pembayaran</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Status</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Arti & cara terjadi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-slate-600">
                    <tr><td class="px-3 py-2 font-medium text-red-700">Belum Bayar</td><td class="px-3 py-2">Belum ada pembayaran/diskon tercatat.</td></tr>
                    <tr><td class="px-3 py-2 font-medium text-yellow-700">Cicilan</td><td class="px-3 py-2">Sudah bayar sebagian (otomatis saat ada paid/discount &gt; 0).</td></tr>
                    <tr><td class="px-3 py-2 font-medium text-green-700">Lunas</td><td class="px-3 py-2">Terbayar penuh tanpa diskon (otomatis).</td></tr>
                    <tr><td class="px-3 py-2 font-medium text-purple-700">Diskon Lunas</td><td class="px-3 py-2">Lunas dengan bantuan diskon pelunasan (otomatis).</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 6. Premium -->
    <div id="premium" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 scroll-mt-4">
        <h2 class="text-lg font-bold text-slate-800 mb-3">6. Gembok Premium & akun Superadmin</h2>
        <div class="text-sm text-slate-600 leading-relaxed space-y-2">
            <ul class="list-disc list-inside space-y-1">
                <li>Modul <strong>CRM</strong>, <strong>Collection</strong>, dan <strong>Auto-Dialer</strong> adalah fitur premium yang bisa digembok per modul.</li>
                <li>Akun <strong>superadmin</strong> (<code class="bg-slate-100 px-1.5 py-0.5 rounded font-mono text-xs">superadmin@skykom.com</code>) selalu bisa membuka semuanya.</li>
                <li>Membuka/menutup: login superadmin → menu <strong>👑 Premium / Lisensi</strong> → klik toggle per modul (berlaku ≤ 1 menit).</li>
                <li>Saat terkunci, admin/supervisor melihat ikon 🔒 di sidebar dan halaman <em>"Premium Feature — hubungi admin jika ingin menggunakannya"</em>.</li>
                <li>Workspace agent (list + click-to-call) <strong>tidak ikut digembok</strong> — itu tool kerja harian collector.</li>
            </ul>
        </div>
    </div>

    <!-- 7. Masalah -->
    <div id="masalah" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 scroll-mt-4">
        <h2 class="text-lg font-bold text-slate-800 mb-3">7. Masalah umum & solusinya</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Gejala</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Solusi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-slate-600">
                    <tr><td class="px-3 py-2">Tombol tidak bisa diklik / modal tidak muncul</td><td class="px-3 py-2">Hard-refresh (Ctrl+Shift+R). Kalau masih, jalankan <code class="bg-slate-100 px-1.5 py-0.5 rounded font-mono text-xs">php artisan octane:reload</code> di server.</td></tr>
                    <tr><td class="px-3 py-2">Simpan gagal + notif Premium Feature</td><td class="px-3 py-2">Modulnya sedang digembok — minta superadmin buka via Premium/Lisensi.</td></tr>
                    <tr><td class="px-3 py-2">Error <code class="bg-slate-100 px-1.5 py-0.5 rounded font-mono text-xs">Unexpected token '&lt;'</code> saat simpan</td><td class="px-3 py-2">Versi lama dari bug gembok; refresh halaman. Kalau masih muncul, laporkan + catat pesannya.</td></tr>
                    <tr><td class="px-3 py-2">Bucket/DPD tidak sesuai umur tunggakan</td><td class="px-3 py-2">Klik <strong>Recalculate Bucket</strong> di halaman Customers (menghitung ulang dari due date).</td></tr>
                    <tr><td class="px-3 py-2">Halaman gembok tampil polos tanpa style</td><td class="px-3 py-2">Refresh sekali (bug Turbo pada respons error — sudah diperbaiki di sisi server).</td></tr>
                    <tr><td class="px-3 py-2">Lupa password superadmin</td><td class="px-3 py-2">Reset via server (tinker): update kolom <code class="bg-slate-100 px-1.5 py-0.5 rounded font-mono text-xs">password</code> user terkait.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
