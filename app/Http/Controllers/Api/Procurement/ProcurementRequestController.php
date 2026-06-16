<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\IndexProcurementRequest;
use App\Http\Requests\Procurement\ProcurementActionRequest;
use App\Http\Requests\Procurement\StoreProcurementRequest;
use App\Http\Requests\Procurement\UpdateProcurementRequest;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Services\ProcurementService;
use Illuminate\Http\JsonResponse;

class ProcurementRequestController extends Controller
{
    public function __construct(protected ProcurementService $service) {}

    public function index(IndexProcurementRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('procurement.view') || $request->user()->can('procurement.read'), 403);
        $data = $this->service->paginate($request->validated());
        return response()->json(['success'=>true,'message'=>'Procurement requests retrieved successfully','data'=>$data->items(),'meta'=>['current_page'=>$data->currentPage(),'per_page'=>$data->perPage(),'total'=>$data->total(),'last_page'=>$data->lastPage()]]);
    }


    public function initialApprovers(): JsonResponse
    {
        abort_unless(auth()->user()->can('procurement.create') || auth()->user()->can('procurement.submit'), 403);

        $normalizeRole = static function (?string $role): string {
            $value = str_replace('&', 'and', strtolower((string) $role));
            $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
            return trim((string) $value);
        };

        $roleAliasMap = [
            'Manager' => ['manager','municipal manager','municipality manager','city manager','general manager'],
            'Head of Development Branch' => ['head of development branch','head development branch','development branch head','development head'],
            'Head of Service Branch' => ['head of service branch','head service branch','service branch head','service head'],
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
            ->get(['id','name','email','phone','is_active'])
            ->map(function (User $user) use ($normalizedAliases, $normalizeRole) {
                $matchedRole = null;
                foreach ($user->roles as $role) {
                    $normalizedRole = $normalizeRole($role->name);
                    if (isset($normalizedAliases[$normalizedRole])) { $matchedRole = $normalizedAliases[$normalizedRole]; break; }
                    foreach ($normalizedAliases as $alias => $displayRole) {
                        if (str_contains($normalizedRole, $alias) || str_contains($alias, $normalizedRole)) { $matchedRole = $displayRole; break 2; }
                    }
                }
                if (! $matchedRole) return null;
                return [
                    'id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'phone'=>$user->phone,
                    'status'=>'active','role'=>$matchedRole,'display_role'=>$matchedRole,
                    'roles'=>$user->roles->map(fn ($role) => ['id'=>$role->id,'name'=>$role->name])->values(),
                ];
            })->filter()->unique('id')->values();

        return response()->json(['success'=>true,'message'=>'Initial procurement approvers retrieved successfully','data'=>$users,'meta'=>['total'=>$users->count(),'allowed_roles'=>array_keys($roleAliasMap)]]);
    }

    public function store(StoreProcurementRequest $request): JsonResponse
    {
        $allowedRoles = [
            User::ROLE_MANAGER,
            User::ROLE_HEAD_DEVELOPMENT_BRANCH,
            User::ROLE_HEAD_SERVICE_BRANCH,
            User::ROLE_PROCUREMENT_REQUESTER,
            User::ROLE_RECORDS_OFFICE,
            User::ROLE_SUPER_ADMIN,
        ];

        abort_unless(
            $request->user()->can('procurement.create')
                && $request->user()->hasAnyRole($allowedRoles),
            403
        );

        return response()->json(['success'=>true,'message'=>'Procurement request created successfully','data'=>$this->service->create($request->validated(), $request->file('attachments', []))], 201);
    }

    public function show(int|string $id): JsonResponse
    {
        abort_unless(auth()->user()->can('procurement.view') || auth()->user()->can('procurement.read'), 403);
        return response()->json(['success'=>true,'message'=>'Procurement request retrieved successfully','data'=>$this->service->find($id)]);
    }

    public function update(UpdateProcurementRequest $request, int|string $id): JsonResponse
    {
        abort_unless($request->user()->can('procurement.update'), 403);
        return response()->json(['success'=>true,'message'=>'Procurement request updated successfully','data'=>$this->service->update($this->service->find($id), $request->validated(), $request->file('attachments', []))]);
    }

    public function destroy(int|string $id): JsonResponse
    {
        abort_unless(auth()->user()->can('procurement.delete'), 403);
        $model = $this->service->find($id);
        $model->delete();
        return response()->json(['success'=>true,'message'=>'Procurement request deleted successfully','data'=>null]);
    }

    public function action(ProcurementActionRequest $request, int|string $id): JsonResponse
    {
        $action = $request->validated()['action'];
        $permission = match($action) {
            'submit' => 'procurement.submit',
            'reject' => 'procurement.reject',
            'asset_team_approve' => 'procurement.asset-review',
            'assign_budget_code' => 'procurement.assign-budget-code',
            'records_process' => 'records.register',
            'finance_complete' => 'finance.process',
            default => 'procurement.approve',
        };
        abort_unless($request->user()->can($permission), 403);
        return response()->json(['success'=>true,'message'=>'Procurement workflow updated successfully','data'=>$this->service->action($this->service->find($id), $action, $request->validated())]);
    }
}
