<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectorVisit;
use App\Models\Customer;
use Illuminate\Http\Request;

/**
 * Kunjungan collector (tombol OTW di Flutter).
 * Auth: middleware collector.token. Semua aksi ter-skoping ke visit
 * milik sendiri (404 bila bukan) dan customer yang di-assign ke dirinya.
 * Aturan: maksimal 1 visit aktif (otw/sampai) per collector.
 */
class CollectorVisitController extends Controller
{
    protected function activeVisit(int $collectorId): ?CollectorVisit
    {
        return CollectorVisit::where('collector_id', $collectorId)
            ->active()->latest('id')->first();
    }

    protected function visitPayload(CollectorVisit $visit): array
    {
        $visit->loadMissing('customer:id,name,phone,address,latitude,longitude');
        $c = $visit->customer;

        return [
            'id' => $visit->id,
            'status' => $visit->status,
            'result' => $visit->result,
            'note' => $visit->note,
            'started_at' => $visit->started_at?->toDateTimeString(),
            'arrived_at' => $visit->arrived_at?->toDateTimeString(),
            'finished_at' => $visit->finished_at?->toDateTimeString(),
            'customer' => $c ? [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'address' => $c->address,
                'latitude' => $c->latitude ? (float) $c->latitude : null,
                'longitude' => $c->longitude ? (float) $c->longitude : null,
            ] : null,
        ];
    }

    protected function activeConflict(?CollectorVisit $active)
    {
        if (!$active) {
            return null;
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Masih ada kunjungan aktif. Selesaikan / batalkan dulu.',
            'active_visit' => $this->visitPayload($active),
        ], 409);
    }

    /** Mulai OTW ke customer. */
    public function start(Request $request)
    {
        $collector = $request->attributes->get('collector');
        $request->validate(['customer_id' => 'required|integer']);

        if ($conflict = $this->activeConflict($this->activeVisit($collector->id))) {
            return $conflict;
        }

        $customer = Customer::where('collector_id', $collector->id)->find($request->customer_id);
        if (!$customer) {
            return response()->json(['status' => 'error', 'message' => 'Customer bukan assigned Anda.'], 422);
        }

        $visit = CollectorVisit::create([
            'collector_id' => $collector->id,
            'customer_id' => $customer->id,
            'status' => 'otw',
            'started_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'visit_id' => $visit->id,
            'visit' => $this->visitPayload($visit),
        ], 201);
    }

    /** Kunjungan aktif (untuk pulihkan status app setelah restart). */
    public function current(Request $request)
    {
        $collector = $request->attributes->get('collector');
        $active = $this->activeVisit($collector->id);

        return response()->json([
            'status' => 'success',
            'visit' => $active ? $this->visitPayload($active) : null,
        ]);
    }

    protected function ownVisit(Request $request, $id): CollectorVisit|\Illuminate\Http\JsonResponse
    {
        $collector = $request->attributes->get('collector');
        $visit = CollectorVisit::where('collector_id', $collector->id)->find($id);
        if (!$visit) {
            return response()->json(['status' => 'error', 'message' => 'Kunjungan tidak ditemukan.'], 404);
        }

        return $visit;
    }

    /** Tandai sampai lokasi (hanya dari otw). */
    public function arrive(Request $request, $id)
    {
        $visit = $this->ownVisit($request, $id);
        if ($visit instanceof \Illuminate\Http\JsonResponse) {
            return $visit;
        }
        if ($visit->status !== 'otw') {
            return response()->json(['status' => 'error', 'message' => 'Hanya visit OTW yang bisa ditandai sampai.'], 422);
        }

        $visit->update(['status' => 'sampai', 'arrived_at' => now()]);

        return response()->json(['status' => 'success', 'visit' => $this->visitPayload($visit->fresh())]);
    }

    /** Selesai (hasil: bayar|ptp|janji|zonk|lainnya + catatan). */
    public function finish(Request $request, $id)
    {
        $visit = $this->ownVisit($request, $id);
        if ($visit instanceof \Illuminate\Http\JsonResponse) {
            return $visit;
        }
        if (!$visit->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Visit sudah selesai/batal.'], 422);
        }

        $request->validate([
            'hasil' => 'nullable|string|max:32',
            'result' => 'nullable|string|max:32',
            'catatan' => 'nullable|string|max:2000',
            'note' => 'nullable|string|max:2000',
        ]);

        $visit->update([
            'status' => 'selesai',
            'result' => $request->hasil ?? $request->result,
            'note' => $request->catatan ?? $request->note,
            'arrived_at' => $visit->arrived_at ?? now(),
            'finished_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'visit' => $this->visitPayload($visit->fresh())]);
    }

    /** Batalkan visit aktif. */
    public function cancel(Request $request, $id)
    {
        $visit = $this->ownVisit($request, $id);
        if ($visit instanceof \Illuminate\Http\JsonResponse) {
            return $visit;
        }
        if (!$visit->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Visit sudah selesai/batal.'], 422);
        }

        $visit->update(['status' => 'batal', 'finished_at' => now()]);

        return response()->json(['status' => 'success', 'visit' => $this->visitPayload($visit->fresh())]);
    }
}
