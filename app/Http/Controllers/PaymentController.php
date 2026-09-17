<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    protected function authorizeAccess()
    {
        if (!Auth::check() && !session()->has('supervisor_extension')) {
            abort(403, 'Unauthorized. Admin or Supervisor access required.');
        }
    }

    protected function recorderLabel(): string
    {
        if (Auth::check()) {
            return Auth::user()->name . ' (admin)';
        }
        $ext = session('supervisor_extension');
        if ($ext) {
            $spv = \App\Models\Agent::where('extension', $ext)->first();
            return ($spv?->name ?? 'SPV') . " (Ext: {$ext})";
        }
        return '-';
    }

    /** Riwayat pembayaran 1 customer (untuk modal). */
    public function index(Customer $customer)
    {
        $this->authorizeAccess();

        $payments = $customer->payments()->get()->map(fn($p) => [
            'id' => $p->id,
            'amount' => (float) $p->amount,
            'paid_at' => $p->paid_at?->toDateString(),
            'method' => $p->method,
            'method_label' => $p->method_label,
            'notes' => $p->notes,
            'proof_url' => $p->proof_url,
            'by' => $p->created_by_label,
            'created_at' => $p->created_at?->toDateTimeString(),
        ]);

        return response()->json([
            'status' => 'success',
            'customer' => $customer->only(['id', 'name', 'phone', 'total_amount', 'paid_amount']),
            'opening_balance' => max(0, (float) $customer->paid_amount - (float) $customer->payments()->sum('amount')),
            'data' => $payments,
        ]);
    }

    /** Catat 1 transaksi pembayaran. */
    public function store(Request $request, Customer $customer)
    {
        $this->authorizeAccess();

        $request->validate([
            'amount' => 'required|numeric|min:1|max:999999999999',
            'paid_at' => 'required|date|before_or_equal:today',
            'method' => 'required|in:' . implode(',', Payment::METHODS),
            'notes' => 'nullable|string|max:1000',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $proofPath = null;
        if ($request->hasFile('proof') && $request->file('proof')->isValid()) {
            $proofPath = $request->file('proof')->storeAs(
                'payment-proofs/' . date('Y/m'),
                \Illuminate\Support\Str::uuid() . '.' . $request->file('proof')->getClientOriginalExtension(),
                'public'
            );
        }

        try {
            $payment = Payment::create([
                'customer_id' => $customer->id,
                'amount' => $request->amount,
                'paid_at' => $request->paid_at,
                'method' => $request->method,
                'notes' => $request->notes,
                'proof' => $proofPath,
                'created_by' => Auth::id(),
                'created_by_label' => $this->recorderLabel(),
            ]);

            $customer->applyPayment((float) $request->amount, (string) $request->paid_at);
        } catch (\Throwable $e) {
            if ($proofPath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($proofPath);
            }
            throw $e;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pembayaran Rp ' . number_format($request->amount, 0, ',', '.') . ' tercatat untuk ' . $customer->name,
            'payment' => $payment,
            'customer' => $customer->refresh()->only(['id', 'paid_amount', 'payment_status', 'last_payment_date', 'remaining_amount']),
        ]);
    }

    /** Hapus 1 transaksi (koreksi salah input) + kembalikan saldo. */
    public function destroy(Payment $payment)
    {
        $this->authorizeAccess();

        $customer = $payment->customer;
        $amount = (float) $payment->amount;

        if ($payment->proof) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($payment->proof);
        }
        $payment->delete();

        if ($customer) {
            $customer->reversePayment($amount);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi dihapus, saldo dikembalikan.',
            'customer' => $customer?->refresh()->only(['id', 'paid_amount', 'payment_status', 'last_payment_date', 'remaining_amount']),
        ]);
    }
}
