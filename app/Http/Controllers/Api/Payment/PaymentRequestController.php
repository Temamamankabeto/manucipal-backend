<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\IndexPaymentRequest;
use App\Http\Requests\Payment\PaymentActionRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentRequestController extends Controller
{
    public function __construct(protected PaymentService $service) {}

    public function index(IndexPaymentRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('payment.view') || $request->user()->can('payment.read'), 403);

        $data = $this->service->paginate($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment requests retrieved successfully',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ],
        ]);
    }


    public function initialApprovers(): JsonResponse
    {
        abort_unless(auth()->user()->can('payment.create') || auth()->user()->can('payment.submit'), 403);

        $normalizeRole = static function (?string $role): string {
            $value = str_replace('&', 'and', strtolower((string) $role));
            $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
            return trim((string) $value);
        };

        $roleAliasMap = [
            'Manager' => [
                'manager',
                'municipal manager',
                'municipality manager',
                'city manager',
                'general manager',
            ],
            'Head of Development Branch' => [
                'head of development branch',
                'head development branch',
                'development branch head',
                'development head',
                'development branch manager',
            ],
            'Head of Service Branch' => [
                'head of service branch',
                'head service branch',
                'service branch head',
                'service head',
                'service branch manager',
            ],
        ];

        $normalizedAliases = collect($roleAliasMap)
            ->flatMap(fn (array $aliases, string $displayRole) => collect($aliases)
                ->mapWithKeys(fn (string $alias) => [$normalizeRole($alias) => $displayRole]))
            ->all();

        $users = User::query()
            ->with(['roles:id,name'])
            ->where('is_active', true)
            ->whereHas('roles')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'is_active'])
            ->map(function (User $user) use ($normalizedAliases, $normalizeRole) {
                $matchedRole = null;

                foreach ($user->roles as $role) {
                    $normalizedRole = $normalizeRole($role->name);

                    if (isset($normalizedAliases[$normalizedRole])) {
                        $matchedRole = $normalizedAliases[$normalizedRole];
                        break;
                    }

                    foreach ($normalizedAliases as $alias => $displayRole) {
                        if (str_contains($normalizedRole, $alias) || str_contains($alias, $normalizedRole)) {
                            $matchedRole = $displayRole;
                            break 2;
                        }
                    }
                }

                if (! $matchedRole) {
                    return null;
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => 'active',
                    'role' => $matchedRole,
                    'display_role' => $matchedRole,
                    'roles' => $user->roles->map(fn ($role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                    ])->values(),
                ];
            })
            ->filter()
            ->unique('id')
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Initial payment approvers retrieved successfully',
            'data' => $users,
            'meta' => [
                'total' => $users->count(),
                'allowed_roles' => array_keys($roleAliasMap),
            ],
        ]);
    }


    public function planningBudgetExperts(): JsonResponse
    {
        abort_unless(auth()->user()->can('payment.approve') || auth()->user()->can('payment.view') || auth()->user()->can('payment.read'), 403);

        $users = $this->service->planningBudgetExperts();

        return response()->json([
            'success' => true,
            'message' => 'Planning and Budget Experts retrieved successfully',
            'data' => $users,
            'meta' => [
                'total' => $users->count(),
            ],
        ]);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('payment.create'), 403);

        return response()->json([
            'success' => true,
            'message' => 'Payment request created and submitted successfully',
            'data' => $this->service->create($request->validated(), $request->file('attachments', [])),
            'meta' => null,
        ], 201);
    }

    public function show(int|string $id): JsonResponse
    {
        abort_unless(auth()->user()->can('payment.view') || auth()->user()->can('payment.read'), 403);

        return response()->json([
            'success' => true,
            'message' => 'Payment request retrieved successfully',
            'data' => $this->service->find($id),
            'meta' => null,
        ]);
    }

    public function update(UpdatePaymentRequest $request, int|string $id): JsonResponse
    {
        abort_unless($request->user()->can('payment.update'), 403);

        return response()->json([
            'success' => true,
            'message' => 'Payment request updated successfully',
            'data' => $this->service->update($this->service->find($id), $request->validated(), $request->file('attachments', [])),
            'meta' => null,
        ]);
    }

    public function destroy(int|string $id): JsonResponse
    {
        abort_unless(auth()->user()->can('payment.delete'), 403);

        $model = $this->service->find($id);
        $model->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment request deleted successfully',
            'data' => null,
            'meta' => null,
        ]);
    }

    public function action(PaymentActionRequest $request, int|string $id): JsonResponse
    {
        $action = $request->validated()['action'];

        $permissions = match ($action) {
            'submit' => ['payment.submit', 'payment.create'],
            'reject' => ['payment.reject', 'payment.approve'],
            'expert_complete' => ['payment.form.prepare', 'payment.approve'],
            'records_process' => ['records.register', 'payment.approve'],
            'finance_complete', 'mark_paid' => ['finance.process', 'payment.complete'],
            default => ['payment.approve'],
        };

        abort_unless(collect($permissions)->contains(fn ($permission) => $request->user()->can($permission)), 403);

        return response()->json([
            'success' => true,
            'message' => 'Payment workflow updated successfully',
            'data' => $this->service->action($this->service->find($id), $action, $request->validated()),
            'meta' => null,
        ]);
    }
}
