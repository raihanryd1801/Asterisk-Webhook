<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

/**
 * Customer yang di-assign ke collector pemilik token (untuk peta + nagih).
 * Scoping WAJIB collector_id milik sendiri — collector A tidak bisa
 * mengintip customer collector B.
 */
class CollectorCustomerController extends Controller
{
    protected function scope(Request $request)
    {
        return Customer::where('collector_id', $request->attributes->get('collector')->id);
    }

    /**
     * Daftar customer untuk peta Flutter.
     * Default hanya case jalan (unpaid/partial); ?all=1 = semua.
     * ?unmapped=1 = hanya yang belum berkoordinat (untuk prioritas kunjungan).
     */
    public function index(Request $request)
    {
        $q = $this->scope($request)->orderByDesc('total_amount');

        if (!$request->boolean('all')) {
            $q->whereIn('payment_status', ['unpaid', 'partial']);
        }
        if ($request->boolean('unmapped')) {
            $q->where(function ($qq) {
                $qq->whereNull('latitude')->orWhereNull('longitude');
            });
        }

        $rows = $q->limit(2000)->get([
            'id', 'name', 'phone', 'company', 'address',
            'latitude', 'longitude', 'geocode_label',
            'total_amount', 'paid_amount', 'discount_amount', 'payment_status',
            'due_date', 'days_past_due', 'bucket',
        ]);

        return response()->json([
            'status' => 'success',
            'total' => $rows->count(),
            'mapped' => $rows->whereNotNull('latitude')->whereNotNull('longitude')->count(),
            'data' => $rows->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'company' => $c->company,
                'address' => $c->address,
                'latitude' => $c->latitude ? (float) $c->latitude : null,
                'longitude' => $c->longitude ? (float) $c->longitude : null,
                'manual_pin' => $c->geocode_label === 'Manual',
                'remaining' => max(0, (float) $c->total_amount - (float) $c->paid_amount - (float) $c->discount_amount),
                'payment_status' => $c->payment_status,
                'due_date' => $c->due_date?->toDateString(),
                'days_past_due' => $c->days_past_due,
                'bucket' => $c->bucket,
            ])->values(),
        ]);
    }

    /** Detail 1 customer (tap pin di Flutter). 404 bila bukan miliknya. */
    public function show(Request $request, $id)
    {
        $c = $this->scope($request)->find($id);
        if (!$c) {
            return response()->json(['status' => 'error', 'message' => 'Customer tidak ditemukan.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'office_phone' => $c->office_phone,
                'emergency_phone' => $c->emergency_phone,
                'company' => $c->company,
                'address' => $c->address,
                'latitude' => $c->latitude ? (float) $c->latitude : null,
                'longitude' => $c->longitude ? (float) $c->longitude : null,
                'manual_pin' => $c->geocode_label === 'Manual',
                'total_amount' => (float) $c->total_amount,
                'paid_amount' => (float) $c->paid_amount,
                'discount_amount' => (float) $c->discount_amount,
                'remaining' => max(0, (float) $c->total_amount - (float) $c->paid_amount - (float) $c->discount_amount),
                'payment_status' => $c->payment_status,
                'due_date' => $c->due_date?->toDateString(),
                'days_past_due' => $c->days_past_due,
                'bucket' => $c->bucket,
                'notes' => $c->notes,
            ],
        ]);
    }
}
