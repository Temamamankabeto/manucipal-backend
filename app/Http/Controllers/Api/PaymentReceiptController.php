<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentReceiptController extends Controller
{
    public function index(Request $request)
    {
        $receipts = PaymentReceipt::with([
            'payment',
            'bill',
            'citizen',
            'generator',
            'verifier',
        ])->latest()->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Payment receipts retrieved successfully',
            'data' => $receipts->items(),
            'meta' => [
                'current_page' => $receipts->currentPage(),
                'last_page' => $receipts->lastPage(),
                'per_page' => $receipts->perPage(),
                'total' => $receipts->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tax_payment_id' => ['required', 'exists:tax_payments,id'],
            'tax_bill_id' => ['nullable', 'exists:tax_bills,id'],
            'citizen_id' => ['nullable', 'exists:citizens,id'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $validated['receipt_number'] = 'REC-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        $validated['status'] = 'generated';
        $validated['generated_by'] = auth()->id();
        $validated['generated_at'] = now();

        $receipt = PaymentReceipt::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Receipt generated successfully',
            'data' => $receipt,
        ], 201);
    }

    public function verify(PaymentReceipt $paymentReceipt)
    {
        $paymentReceipt->update([
            'status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Receipt verified successfully',
            'data' => $paymentReceipt,
        ]);
    }
}
