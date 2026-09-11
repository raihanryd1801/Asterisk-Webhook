<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DebtCollector;

class DebtCollectorController extends Controller
{
    public function index(Request $request)
    {
        $query = DebtCollector::withCount(['customers as active_cases' => function ($q) {
            $q->whereIn('payment_status', ['unpaid', 'partial']);
        }]);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('area', 'like', "%{$s}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $collectors = $query->latest()->paginate(15)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($collectors);
        }

        return view('crm.collectors.index', compact('collectors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'type' => 'required|in:field,desk',
            'area' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $collector = DebtCollector::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'type' => $request->type,
            'area' => $request->area,
            'notes' => $request->notes,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Debt collector berhasil ditambahkan!',
            'collector' => $collector,
        ]);
    }

    public function show(DebtCollector $collector)
    {
        $collector->loadCount(['customers as active_cases' => function ($q) {
            $q->whereIn('payment_status', ['unpaid', 'partial']);
        }]);
        return response()->json($collector);
    }

    public function update(Request $request, DebtCollector $collector)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'type' => 'required|in:field,desk',
            'area' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $collector->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'type' => $request->type,
            'area' => $request->area,
            'notes' => $request->notes,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Debt collector berhasil diupdate!',
            'collector' => $collector,
        ]);
    }

    public function destroy(DebtCollector $collector)
    {
        // Case yang menunjuk ke collector ini otomatis null (FK nullOnDelete)
        $name = $collector->name;
        $collector->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Debt collector {$name} dihapus. Case terkait jadi Tanpa Collector.",
        ]);
    }
}
