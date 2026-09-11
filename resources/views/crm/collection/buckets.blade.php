@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-6">
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-5 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-brand-600"></i> Buckets
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Kelompok umur tunggakan (DPD). Klik baris untuk melihat customers-nya.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button onclick="openRangeModal()" class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2">
                <i class="fa-solid fa-sliders"></i> Atur Rentang
            </button>
            <a href="{{ route('crm.collection.dashboard') }}" class="bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Bucket</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Rentang DPD</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Cases</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Portfolio</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Collected</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Outstanding</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Recovery %</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Avg DPD</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($buckets as $b)
                        @php
                            $rangeMap = [];
                            foreach ($ranges as $r) {
                                $rangeMap[$r['bucket']] = \App\Models\BucketRange::describe($r);
                            }
                            $rangeMap['Tanpa Bucket'] = 'tanpa due date';
                            $recovery = $b->total_amount > 0 ? round(($b->paid_amount / $b->total_amount) * 100, 1) : 0;
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                    @class([
                                        'bg-blue-50 text-blue-700 border-blue-200' => $b->bucket === 'Current',
                                        'bg-green-50 text-green-700 border-green-200' => $b->bucket === 'Bucket 1',
                                        'bg-yellow-50 text-yellow-700 border-yellow-200' => $b->bucket === 'Bucket 2',
                                        'bg-orange-50 text-orange-700 border-orange-200' => $b->bucket === 'Bucket 3',
                                        'bg-red-50 text-red-700 border-red-200' => $b->bucket === 'NPL',
                                        'bg-slate-100 text-slate-600 border-slate-200' => !in_array($b->bucket, ['Current', 'Bucket 1', 'Bucket 2', 'Bucket 3', 'NPL']),
                                    ])>
                                    {{ $b->bucket }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $rangeMap[$b->bucket] ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($b->count) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700">Rp {{ number_format($b->total_amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-emerald-700">Rp {{ number_format($b->paid_amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-amber-700">Rp {{ number_format(max(0, $b->remaining_amount), 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ $recovery }}%</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-500">{{ number_format($b->avg_dpd, 1) }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($b->bucket === 'Tanpa Bucket')
                                    <span class="text-slate-300 text-xs">—</span>
                                @else
                                    <button onclick="openBlastModal({{ json_encode($b->bucket) }})" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition-colors" title="Blast WA/SMS ke bucket ini">
                                        <i class="fa-solid fa-paper-plane text-[10px]"></i> Blast
                                    </button>
                                    <a href="{{ route('crm.customers.index', ['bucket' => $b->bucket]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-brand-50 text-brand-700 border border-brand-200 hover:bg-brand-100 transition-colors">
                                        Lihat <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Blast per Bucket -->
<div id="blast-modal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" onclick="closeBlastModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between p-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Blast <span id="blast-bucket-name"></span></h3>
                <button onclick="closeBlastModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div class="p-4 space-y-4">
                <p class="text-xs text-slate-500">Target: case <span id="blast-bucket-name-2" class="font-bold"></span> berstatus unpaid/partial dan bernomor.</p>
                <div id="blast-sender-box" class="text-xs rounded-lg p-3 border border-slate-200 bg-slate-50 text-slate-600">
                    Memeriksa sesi WhatsApp Anda...
                </div>
                <div class="text-sm font-medium text-slate-700">Estimasi target: <span class="font-bold" id="blast-targets">-</span> nomor</div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Pesan</label>
                    <textarea id="blast-message" rows="4" maxlength="1000" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent" placeholder="Yth Bpk/Ibu, tagihan Anda telah jatuh tempo..."></textarea>
                </div>
                <div id="blast-history" class="space-y-1"></div>
                <div class="flex justify-end gap-2 pt-4 border-t border-slate-200">
                    <button onclick="closeBlastModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                    <button onclick="submitBlast()" id="blast-submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors disabled:opacity-50">Kirim Blast</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Atur Rentang DPD -->
<div id="range-modal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" onclick="closeRangeModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between p-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Atur Rentang DPD per Bucket</h3>
                <button onclick="closeRangeModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <p class="text-xs text-slate-500 mb-3">DPD = hari keterlambatan (0 = belum jatuh tempo). Urutkan min menaik tanpa tumpang tindih. Tepat 1 bucket tanpa batas atas (untuk DPD terbesar).</p>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Bucket</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Min DPD</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Max DPD</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Risk</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200" id="range-body"></tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-200 flex justify-end gap-2">
                <button onclick="closeRangeModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                <button onclick="submitRanges()" id="range-submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm hover:bg-brand-700 transition-colors disabled:opacity-50">Simpan</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const initialRanges = @json($ranges);

function openRangeModal() {
    const tbody = document.getElementById('range-body');
    tbody.innerHTML = initialRanges.map((r, i) => `
        <tr class="hover:bg-slate-50" data-idx="${i}">
            <td class="px-3 py-2 font-medium text-slate-900">${r.bucket}<input type="hidden" data-f="bucket" value="${r.bucket}"></td>
            <td class="px-3 py-2"><input type="number" min="0" max="3650" data-f="min_dpd" value="${r.min_dpd}" class="w-24 border border-slate-300 rounded-lg px-2 py-1 text-sm"></td>
            <td class="px-3 py-2">
                <div class="flex items-center gap-2">
                    <input type="number" min="0" max="3650" data-f="max_dpd" value="${r.max_dpd ?? ''}" ${r.max_dpd === null ? 'disabled' : ''} class="w-24 border border-slate-300 rounded-lg px-2 py-1 text-sm disabled:bg-slate-100">
                    <label class="text-xs text-slate-500 flex items-center gap-1 whitespace-nowrap"><input type="checkbox" data-f="unbounded" ${r.max_dpd === null ? 'checked' : ''} onchange="this.closest('tr').querySelector('[data-f=max_dpd]').disabled = this.checked"> ∞</label>
                </div>
            </td>
            <td class="px-3 py-2">
                <select data-f="risk_level" class="border border-slate-300 rounded-lg px-2 py-1 text-sm">
                    ${['low', 'medium', 'high', 'critical'].map(lv => `<option value="${lv}" ${r.risk_level === lv ? 'selected' : ''}>${lv}</option>`).join('')}
                </select>
            </td>
        </tr>`).join('');
    document.getElementById('range-modal').style.display = 'block';
}

function closeRangeModal() {
    document.getElementById('range-modal').style.display = 'none';
}

let blastBucket = '';

let blastSenderReady = false;

async function openBlastModal(bucket) {
    blastBucket = bucket;
    blastSenderReady = false;
    document.getElementById('blast-bucket-name').textContent = bucket;
    document.getElementById('blast-bucket-name-2').textContent = bucket;
    document.getElementById('blast-targets').textContent = '...';
    document.getElementById('blast-message').value = '';
    document.getElementById('blast-history').innerHTML = '';
    document.getElementById('blast-sender-box').className = 'text-xs rounded-lg p-3 border border-slate-200 bg-slate-50 text-slate-600';
    document.getElementById('blast-sender-box').textContent = 'Memeriksa sesi WhatsApp Anda...';
    document.getElementById('blast-modal').style.display = 'block';
    try {
        const [prevRes, senderRes] = await Promise.all([
            fetch(`{{ url('/dashboard/crm/collection/blast/preview') }}?bucket=${encodeURIComponent(bucket)}`, {
                headers: { 'Accept': 'application/json' }
            }),
            fetch(`{{ url('/dashboard/crm/whatsapp/sender') }}`, {
                headers: { 'Accept': 'application/json' }
            }),
        ]);
        const data = await prevRes.json();
        if (data.status === 'success') {
            document.getElementById('blast-targets').textContent = data.targets;
            document.getElementById('blast-history').innerHTML =
                '<p class="text-xs font-semibold text-slate-500 uppercase">Riwayat blast bucket ini</p>' +
                (data.history.length === 0
                    ? '<p class="text-xs text-slate-400">Belum ada blast.</p>'
                    : data.history.map(h => `<div class="text-xs border border-slate-200 rounded-lg p-2 mb-1 flex justify-between gap-2">
                        <span class="uppercase font-bold">${h.channel}</span>
                        <span>${h.sent}/${h.total_target} target</span>
                        <span class="text-slate-400">${h.created_at}</span>
                    </div>`).join(''));
        }
        const sender = await senderRes.json();
        const box = document.getElementById('blast-sender-box');
        if (sender.connected) {
            blastSenderReady = true;
            box.className = 'text-xs rounded-lg p-3 border border-emerald-200 bg-emerald-50 text-emerald-700';
            box.innerHTML = `Dikirim dari nomor Anda: <strong>+${sender.phone || '-'}</strong> (${sender.owner})`;
        } else {
            box.className = 'text-xs rounded-lg p-3 border border-amber-200 bg-amber-50 text-amber-700';
            box.innerHTML = `WhatsApp Anda <strong>belum terhubung</strong>. <a href="{{ route('crm.whatsapp') }}" class="underline font-bold">Scan QR dulu di menu WhatsApp Saya</a>.`;
        }
    } catch (e) {
        console.error(e);
    }
}

function closeBlastModal() {
    document.getElementById('blast-modal').style.display = 'none';
    blastBucket = '';
}

async function submitBlast() {
    const message = document.getElementById('blast-message').value.trim();
    if (!message) {
        alert('Isi pesan dulu');
        return;
    }
    if (!blastSenderReady) {
        alert('WhatsApp Anda belum terhubung. Scan QR dulu di menu WhatsApp Saya.');
        return;
    }
    const btn = document.getElementById('blast-submit');
    btn.disabled = true;
    try {
        const res = await fetch(`{{ url('/dashboard/crm/collection/blast/send') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                bucket: blastBucket,
                channel: 'wa',
                message: message,
            }),
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert(data.message);
            closeBlastModal();
        } else {
            alert(data.message || 'Gagal.');
        }
    } catch (e) {
        console.error(e);
        alert('Terjadi kesalahan jaringan/server.');
    } finally {
        btn.disabled = false;
    }
}

async function submitRanges() {
    const btn = document.getElementById('range-submit');
    btn.disabled = true;
    try {
        const ranges = [...document.querySelectorAll('#range-body tr')].map(tr => {
            const get = f => tr.querySelector(`[data-f="${f}"]`);
            const unbounded = get('unbounded').checked;
            return {
                bucket: get('bucket').value,
                min_dpd: parseInt(get('min_dpd').value || '0', 10),
                max_dpd: unbounded ? null : (get('max_dpd').value === '' ? null : parseInt(get('max_dpd').value, 10)),
                risk_level: get('risk_level').value,
            };
        });
        const res = await fetch('{{ route('crm.collection.buckets.ranges') }}', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ ranges }),
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || 'Gagal menyimpan.');
        }
    } catch (e) {
        console.error(e);
        alert('Terjadi kesalahan jaringan/server.');
    } finally {
        btn.disabled = false;
    }
}
</script>
@endsection
