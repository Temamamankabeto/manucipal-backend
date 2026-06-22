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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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


    public function departments(): JsonResponse
    {
        abort_unless(auth()->user()->can('payment.approve') || auth()->user()->can('payment.view') || auth()->user()->can('payment.read'), 403);

        if (! Schema::hasTable('departments')) {
            return response()->json([
                'success' => true,
                'message' => 'Departments table is not available yet',
                'data' => [],
                'meta' => ['total' => 0],
            ]);
        }

        $select = ['departments.id', 'departments.name'];

        if (Schema::hasColumn('departments', 'office_id')) {
            $select[] = 'departments.office_id';
        }

        if (Schema::hasColumn('departments', 'is_active')) {
            $select[] = 'departments.is_active';
        }

        $query = DB::table('departments')
            ->select($select)
            ->orderBy('departments.name');

        if (Schema::hasColumn('departments', 'is_active')) {
            $query->where('departments.is_active', true);
        }

        $departments = $query->get()->map(function ($department) {
            $office = null;

            if (isset($department->office_id) && Schema::hasTable('offices')) {
                $officeColumns = ['id'];

                if (Schema::hasColumn('offices', 'name')) {
                    $officeColumns[] = 'name';
                }

                $office = DB::table('offices')
                    ->where('id', $department->office_id)
                    ->first($officeColumns);
            }

            return [
                'id' => $department->id,
                'office_id' => $department->office_id ?? null,
                'name' => $department->name,
                'is_active' => $department->is_active ?? true,
                'office' => $office ? [
                    'id' => $office->id,
                    'name' => $office->name ?? null,
                ] : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Departments retrieved successfully',
            'data' => $departments,
            'meta' => ['total' => $departments->count()],
        ]);
    }

    public function departmentTeamLeaders(int|string $departmentId): JsonResponse
    {
        abort_unless(auth()->user()->can('payment.approve') || auth()->user()->can('payment.view') || auth()->user()->can('payment.read'), 403);

        $users = $this->departmentUsersByNormalizedRoles($departmentId, [
            'team-leader',
            'department-head',
            'team-leader-department-head',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department Team Leaders retrieved successfully',
            'data' => $users,
            'meta' => ['total' => $users->count()],
        ]);
    }

    public function departmentExperts(int|string $departmentId): JsonResponse
    {
        abort_unless(auth()->user()->can('payment.approve') || auth()->user()->can('payment.view') || auth()->user()->can('payment.read'), 403);

        $users = $this->departmentUsersByNormalizedRoles($departmentId, ['expert']);

        return response()->json([
            'success' => true,
            'message' => 'Department Experts retrieved successfully',
            'data' => $users,
            'meta' => ['total' => $users->count()],
        ]);
    }

    private function departmentUsersByNormalizedRoles(int|string $departmentId, array $normalizedRoles)
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'department_id')) {
            return collect();
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return collect();
        }

        $select = [
            'users.id',
            'users.name',
            'users.email',
            'users.phone',
            'users.department_id',
            'roles.name as role_name',
        ];

        if (Schema::hasColumn('users', 'is_active')) {
            $select[] = 'users.is_active';
        }

        $query = DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('users.department_id', $departmentId)
            ->orderBy('users.name')
            ->select($select);

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('users.is_active', true);
        }

        return $query->get()
            ->filter(fn ($user) => in_array($this->normalizePaymentRole((string) $user->role_name), $normalizedRoles, true))
            ->unique('id')
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => ($user->is_active ?? true) ? 'active' : 'inactive',
                'department_id' => $user->department_id,
                'role' => $user->role_name,
                'display_role' => $user->role_name,
                'roles' => [[
                    'id' => null,
                    'name' => $user->role_name,
                ]],
            ])
            ->values();
    }

    private function normalizePaymentRole(string $role): string
    {
        return trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', str_replace('&', 'and', strtolower($role)))), '-');
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
            'save_manager_final_attachment_draft' => ['payment.approve'],
            'save_record_attachment_draft' => ['records.register', 'payment.approve'],
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
