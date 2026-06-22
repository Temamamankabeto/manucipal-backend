<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\PaymentCategory\StorePaymentCategoryRequest;
use App\Http\Requests\PaymentCategory\UpdatePaymentCategoryRequest;
use App\Models\PaymentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentCategoryController extends Controller
{
    private function isSuperAdmin(?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(User::ROLE_SUPER_ADMIN, 'sanctum')
            || $user->hasRole(User::ROLE_SUPER_ADMIN)
            || $user->getRoleNames()->contains(fn ($role) => strtolower((string) $role) === strtolower(User::ROLE_SUPER_ADMIN));
    }

    private function canViewMasterData(?\App\Models\User $user): bool
    {
        return $this->isSuperAdmin($user)
            || (bool) ($user?->can('payment.view') || $user?->can('payment.read') || $user?->can('payment.create'));
    }

    private function canDeleteMasterData(?\App\Models\User $user): bool
    {
        return $this->isSuperAdmin($user)
            || (bool) ($user?->can('payment.delete') || $user?->can('payment.approve'));
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->canViewMasterData($request->user()), 403);

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
        abort_unless($this->canViewMasterData($request->user()), 403);

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
        abort_unless($this->canDeleteMasterData($request->user()), 403);

        DB::transaction(fn () => $payment_category->delete());

        return response()->json([
            'success' => true,
            'message' => 'Payment category deleted successfully',
            'data' => null,
            'meta' => null,
        ]);
    }
}
