<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentCategory\StorePaymentCategoryRequest;
use App\Http\Requests\PaymentCategory\UpdatePaymentCategoryRequest;
use App\Models\PaymentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('payment.view') || $request->user()->can('payment.read') || $request->user()->can('payment.create'), 403);

        $query = PaymentCategory::query()->withCount('types');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->string('search') . '%');
        }

        $categories = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Payment categories retrieved successfully',
            'data' => $categories->items(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'last_page' => $categories->lastPage(),
            ],
        ]);
    }

    public function store(StorePaymentCategoryRequest $request): JsonResponse
    {
        $category = DB::transaction(fn () => PaymentCategory::create($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Payment category created successfully',
            'data' => $category,
            'meta' => null,
        ], 201);
    }

    public function show(Request $request, PaymentCategory $payment_category): JsonResponse
    {
        abort_unless($request->user()->can('payment.view') || $request->user()->can('payment.read') || $request->user()->can('payment.create'), 403);

        return response()->json([
            'success' => true,
            'message' => 'Payment category retrieved successfully',
            'data' => $payment_category->load('types'),
            'meta' => null,
        ]);
    }

    public function update(UpdatePaymentCategoryRequest $request, PaymentCategory $payment_category): JsonResponse
    {
        DB::transaction(fn () => $payment_category->update($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Payment category updated successfully',
            'data' => $payment_category->fresh()->loadCount('types'),
            'meta' => null,
        ]);
    }

    public function destroy(Request $request, PaymentCategory $payment_category): JsonResponse
    {
        abort_unless($request->user()->can('payment.delete') || $request->user()->can('payment.approve'), 403);

        DB::transaction(fn () => $payment_category->delete());

        return response()->json([
            'success' => true,
            'message' => 'Payment category deleted successfully',
            'data' => null,
            'meta' => null,
        ]);
    }
}
