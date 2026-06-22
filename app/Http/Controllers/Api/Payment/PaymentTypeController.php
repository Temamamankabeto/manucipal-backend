<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\PaymentType\StorePaymentTypeRequest;
use App\Http\Requests\PaymentType\UpdatePaymentTypeRequest;
use App\Models\PaymentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentTypeController extends Controller
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

        $query = PaymentType::query()->with('category:id,name');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->string('search') . '%');
        }

        $types = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Payment types retrieved successfully',
            'data' => $types->items(),
            'meta' => [
                'current_page' => $types->currentPage(),
                'per_page' => $types->perPage(),
                'total' => $types->total(),
                'last_page' => $types->lastPage(),
            ],
        ]);
    }

    public function store(StorePaymentTypeRequest $request): JsonResponse
    {
        $type = DB::transaction(fn () => PaymentType::create($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Payment type created successfully',
            'data' => $type->load('category:id,name'),
            'meta' => null,
        ], 201);
    }

    public function show(Request $request, PaymentType $payment_type): JsonResponse
    {
        abort_unless($this->canViewMasterData($request->user()), 403);

        return response()->json([
            'success' => true,
            'message' => 'Payment type retrieved successfully',
            'data' => $payment_type->load('category:id,name'),
            'meta' => null,
        ]);
    }

    public function update(UpdatePaymentTypeRequest $request, PaymentType $payment_type): JsonResponse
    {
        DB::transaction(fn () => $payment_type->update($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Payment type updated successfully',
            'data' => $payment_type->fresh()->load('category:id,name'),
            'meta' => null,
        ]);
    }

    public function destroy(Request $request, PaymentType $payment_type): JsonResponse
    {
        abort_unless($this->canDeleteMasterData($request->user()), 403);

        DB::transaction(fn () => $payment_type->delete());

        return response()->json([
            'success' => true,
            'message' => 'Payment type deleted successfully',
            'data' => null,
            'meta' => null,
        ]);
    }
}
