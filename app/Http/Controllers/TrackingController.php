<?php

namespace App\Http\Controllers;

use App\Models\CollectorPosition;
use App\Models\DebtCollector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Peta live posisi collector lapangan (supervisor). */
class TrackingController extends Controller
{
    protected function authorizeAccess()
    {
        if (!Auth::check() && !session()->has('supervisor_extension')) {
            abort(403, 'Unauthorized. Admin or Supervisor access required.');
        }
    }

    public function index()
    {
        $this->authorizeAccess();
        $collectors = DebtCollector::active()->field()->orderBy('name')->get(['id', 'name', 'phone', 'area']);

        return view('crm.tracking.index', compact('collectors'));
    }

    /** Posisi terakhir tiap collector lapangan aktif + beban case. */
    public function live()
    {
        $this->authorizeAccess();

        $rows = DebtCollector::active()->field()->withCount([
            'customers as open_cases' => fn ($q) => $q->whereIn('payment_status', ['unpaid', 'partial']),
        ])->orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $rows->map(function ($c) {
                $last = $c->lastPosition();

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'area' => $c->area,
                    'open_cases' => $c->open_cases,
                    'online' => $c->is_online,
                    'tracking' => (bool) $c->is_tracking,
                    'active_visit' => $this->activeVisitPayload($c->id),
                    'latitude' => $last?->latitude,
                    'longitude' => $last?->longitude,
                    'accuracy' => $last?->accuracy,
                    'at' => $last?->recorded_at?->toDateTimeString(),
                ];
            })->values(),
        ]);
    }

    /** Visit aktif 1 collector untuk garis OTW di peta (null bila tidak ada). */
    protected function activeVisitPayload(int $collectorId): ?array
    {
        $visit = \App\Models\CollectorVisit::where('collector_id', $collectorId)
            ->active()->latest('id')->first();
        if (!$visit) {
            return null;
        }
        $cust = \App\Models\Customer::find($visit->customer_id, ['id', 'name', 'latitude', 'longitude']);

        return [
            'id' => $visit->id,
            'status' => $visit->status,
            'started_at' => $visit->started_at?->toDateTimeString(),
            'customer_id' => $visit->customer_id,
            'customer_name' => $cust?->name,
            'latitude' => $cust?->latitude ? (float) $cust->latitude : null,
            'longitude' => $cust?->longitude ? (float) $cust->longitude : null,
        ];
    }

    /** Jejak 30 titik terakhir 1 collector (garis trail di peta). */
    public function trail(DebtCollector $collector)
    {
        $this->authorizeAccess();

        $pts = CollectorPosition::where('collector_id', $collector->id)
            ->orderByDesc('recorded_at')->orderByDesc('id')
            ->limit(30)
            ->get(['latitude', 'longitude', 'recorded_at'])
            ->reverse()->values();

        return response()->json(['status' => 'success', 'data' => $pts]);
    }
}
