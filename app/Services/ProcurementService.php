<?php

namespace App\Services;

use App\Models\Department;
use App\Models\ProcurementAttachment;
use App\Models\ProcurementHistory;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int)($filters['per_page'] ?? 10), 100));
        $user = auth()->user();

        return ProcurementRequest::query()
            ->with([
                'requester:id,name,email',
                'currentHandler:id,name,email',
                'category:id,name',
                'procurementType:id,category_id,name',
                'department:id,name,office_id',
                'assignedTeamLeader:id,name,email',
                'budgetDepartment:id,name,office_id',
                'budgetTeamLeader:id,name,email',
            ])
            ->when($user && ! $user->hasRole(User::ROLE_SUPER_ADMIN), function ($query) use ($user) {
                if ($user->hasRole(User::ROLE_RECORD_OFFICER) || $user->hasRole(User::ROLE_RECORDS_OFFICE)) {
                    // Record Officer must keep access to every procurement request that reached records,
                    // even after current_handler_id changes or the request is completed.
                    $query->where(function ($scope) use ($user) {
                        $scope->where('current_handler_id', $user->id)
                            ->orWhereNotNull('records_signed_by')
                            ->orWhereIn('status', [
                                ProcurementRequest::STATUS_RECORDS_PROCESSING,
                                ProcurementRequest::STATUS_SENT_TO_FINANCE,
                                ProcurementRequest::STATUS_COMPLETED,
                            ]);
                    });

                    return;
                }

                // Show every procurement request that the current authenticated user
                // created, is currently assigned to, or has already acted on.
                // This keeps tracking/history visibility without changing workflow ownership:
                // action buttons are still controlled by current_handler_id and status.
                $query->where(function ($scope) use ($user) {
                    $scope->where('current_handler_id', $user->id)
                        ->orWhere('requested_by', $user->id)
                        ->orWhereHas('histories', function ($history) use ($user) {
                            $history->where('actor_id', $user->id);
                        });
                });
            })
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn($q, $v) => $q->where(fn($qq) => $qq
                ->where('request_no', 'like', "%{$v}%")
                ->orWhere('title', 'like', "%{$v}%")
                ->orWhere('reference_no', 'like', "%{$v}%")))
            ->latest()
            ->paginate($perPage);
    }

    public function find(int|string $id): ProcurementRequest
    {
        return ProcurementRequest::with([
            'items',
            'attachments',
            'histories.actor:id,name,email,signature_path',
            'requester:id,name,email',
            'currentHandler:id,name,email',
            'category:id,name',
            'procurementType:id,category_id,name',
            'department:id,name,office_id',
            'assignedTeamLeader:id,name,email,department_id',
            'budgetDepartment:id,name,office_id',
            'budgetTeamLeader:id,name,email,department_id',
            'initialApprover:id,name,email',
            'managerSigner:id,name,signature_path,stamp_path,titer_path',
            'assetSigner:id,name,signature_path,stamp_path,titer_path',
            'budgetTlSigner:id,name,signature_path,stamp_path,titer_path',
            'finalManagerSigner:id,name,signature_path,stamp_path,titer_path',
            'recordsSigner:id,name,signature_path,stamp_path,titer_path',
            'financeSigner:id,name,signature_path,stamp_path,titer_path',
        ])->findOrFail($id);
    }

    public function create(array $data, ?array $files = []): ProcurementRequest
    {
        return DB::transaction(function () use ($data, $files) {
            $items = $data['items'] ?? [];
            unset($data['items'], $data['attachments']);

            $data['request_no'] = $this->nextNumber('PR');
            $data['requested_by'] = auth()->id();
            $data['status'] = $data['status'] ?? ProcurementRequest::STATUS_DRAFT;

            $request = ProcurementRequest::create($data);

            foreach ($items as $item) {
                $item['estimated_total_cost'] = ($item['estimated_unit_cost'] ?? 0) * ($item['quantity'] ?? 1);
                $request->items()->create($item);
            }

            $this->storeAttachments($request, $files ?? []);
            $this->history($request, 'CREATE', null, $request->status, 'Procurement request created');
            AuditLogService::log('procurement', 'CREATE', $request, ['after' => $request->toArray()]);

            return $this->find($request->id);
        });
    }

    public function update(ProcurementRequest $request, array $data, ?array $files = []): ProcurementRequest
    {
        return DB::transaction(function () use ($request, $data, $files) {
            $before = $request->toArray();
            $items = $data['items'] ?? null;
            unset($data['items'], $data['attachments']);

            $request->update($data);

            if (is_array($items)) {
                $request->items()->delete();
                foreach ($items as $item) {
                    $item['estimated_total_cost'] = ($item['estimated_unit_cost'] ?? 0) * ($item['quantity'] ?? 1);
                    $request->items()->create($item);
                }
            }

            $this->storeAttachments($request, $files ?? []);
            $this->history($request, 'UPDATE', $before['status'] ?? null, $request->status, 'Procurement request updated');
            AuditLogService::log('procurement', 'UPDATE', $request, ['before' => $before, 'after' => $request->fresh()->toArray()]);

            return $this->find($request->id);
        });
    }

    public function action(ProcurementRequest $request, string $action, array $data = []): ProcurementRequest
    {
        return DB::transaction(function () use ($request, $action, $data) {
            $from = $request->status;

            if (! $this->canRunActionFromStatus($from, $action)) {
                throw ValidationException::withMessages([
                    'action' => ['This action is not allowed for the current workflow status.'],
                ]);
            }

            $this->assertCurrentUserCanRunAction($request, $action);

            $to = $this->nextStatus($request, $from, $action);
            $payload = ['status' => $to];

            if ($action === 'submit') {
                $handlerId = (int)($data['send_to_user_id'] ?? $data['receiver_id'] ?? 0) ?: $this->resolveInitialHandlerId($data['receiver_type'] ?? null);
                $this->assertUserHasAnyRole($handlerId, [User::ROLE_MANAGER, User::ROLE_HEAD_DEVELOPMENT_BRANCH, User::ROLE_HEAD_SERVICE_BRANCH], 'receiver');

                $payload['submitted_at'] = now();
                $payload['initial_approver_id'] = $handlerId;
                $payload['current_handler_id'] = $handlerId;
            }

            if ($action === 'manager_approve') {
                $departmentId = (int)($data['department_id'] ?? 0);
                $teamLeaderId = (int)($data['team_leader_user_id'] ?? $data['assigned_team_leader_id'] ?? 0);

                $this->assertDepartmentExists($departmentId);
                $this->assertCategoryDepartmentRule($request, $departmentId);
                $this->assertUserInDepartmentWithRole($teamLeaderId, $departmentId, User::ROLE_TEAM_LEADER, 'team_leader_user_id');

                $payload['manager_signed_by'] = auth()->id();
                $payload['department_id'] = $departmentId;
                $payload['assigned_team_leader_id'] = $teamLeaderId;
                $payload['initial_approver_id'] = $request->initial_approver_id ?: auth()->id();
                $payload['current_handler_id'] = $teamLeaderId;
            }

            if ($action === 'asset_team_approve') {
                $budgetDepartmentId = (int)($data['budget_department_id'] ?? 0) ?: $this->resolveBudgetDepartmentId();
                $budgetTeamLeaderId = (int)($data['budget_team_leader_user_id'] ?? $data['budget_team_leader_id'] ?? 0) ?: $this->resolveTeamLeaderIdByDepartment($budgetDepartmentId);

                $this->assertDepartmentExists($budgetDepartmentId);
                $this->assertBudgetDepartment($budgetDepartmentId);
                $this->assertUserInDepartmentWithRole($budgetTeamLeaderId, $budgetDepartmentId, User::ROLE_TEAM_LEADER, 'budget_team_leader_user_id');

                $payload['asset_signed_by'] = auth()->id();
                $payload['budget_department_id'] = $budgetDepartmentId;
                $payload['budget_team_leader_id'] = $budgetTeamLeaderId;
                $payload['current_handler_id'] = $budgetTeamLeaderId;
            }

            if ($action === 'assign_budget_code') {
                $payload['budget_tl_signed_by'] = auth()->id();
                $payload['budget_code'] = $data['budget_code'] ?? $request->budget_code;
                $payload['current_handler_id'] = $request->initial_approver_id ?: $request->manager_signed_by ?: $this->resolveFirstUserIdByRoles([User::ROLE_MANAGER]);
            }

            if ($action === 'final_manager_approve') {
                $payload['final_manager_signed_by'] = auth()->id();
                $payload['current_handler_id'] = $this->resolveFirstUserIdByRoles([User::ROLE_RECORD_OFFICER, User::ROLE_RECORDS_OFFICE]);

                foreach (['attachment_to', 'attachment_address', 'attachment_case', 'attachment_body', 'attachment_gg'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $payload[$field] = $data[$field];
                    }
                }
            }

            if ($action === 'save_manager_final_attachment_draft') {
                foreach (['attachment_to', 'attachment_address', 'attachment_case', 'attachment_body', 'attachment_gg'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $payload[$field] = $data[$field];
                    }
                }
                unset($payload['status']);
                $to = $from;
            }

            if ($action === 'save_record_attachment_draft') {
                $payload['attachment_reference_no'] = $data['attachment_reference_no'] ?? $request->attachment_reference_no;
                $payload['attachment_official_date_ec'] = $data['attachment_official_date_ec'] ?? $request->attachment_official_date_ec;
                unset($payload['status']);
                $to = $from;
            }

            if ($action === 'records_process') {
                $payload['records_signed_by'] = auth()->id();
                $payload['reference_no'] = $data['reference_no'] ?? $request->reference_no ?? $this->nextNumber('PROC-REF');
                $payload['official_date'] = $data['official_date'] ?? now()->toDateString();
                if (array_key_exists('official_date_ec', $data)) {
                    $payload['official_date_ec'] = $data['official_date_ec'];
                }
                if (array_key_exists('attachment_reference_no', $data)) {
                    $payload['attachment_reference_no'] = $data['attachment_reference_no'];
                }
                if (array_key_exists('attachment_official_date_ec', $data)) {
                    $payload['attachment_official_date_ec'] = $data['attachment_official_date_ec'];
                }
                $payload['current_handler_id'] = null;
                $payload['completed_at'] = now();
            }

            if ($action === 'finance_complete') {
                $payload['finance_signed_by'] = auth()->id();
                $payload['completed_at'] = now();
                $payload['current_handler_id'] = null;
            }

            $request->update($payload);

            if ($action === 'asset_team_approve' && isset($data['items']) && is_array($data['items'])) {
                $request->items()->delete();
                foreach ($data['items'] as $item) {
                    $item['estimated_unit_cost'] = $item['estimated_unit_cost'] ?? 0;
                    $item['estimated_total_cost'] = $item['estimated_total_cost'] ?? (($item['estimated_unit_cost'] ?? 0) * ($item['quantity'] ?? 1));
                    $request->items()->create($item);
                }
            }

            $this->history($request, strtoupper($action), $from, $to, $data['note'] ?? $data['reason'] ?? null, $data);
            AuditLogService::log('procurement', strtoupper($action), $request, [
                'before' => ['status' => $from],
                'after' => ['status' => $to],
                'meta' => $data,
            ]);

            return $this->find($request->id);
        });
    }

    protected function assertCurrentUserCanRunAction(ProcurementRequest $request, string $action): void
    {
        $user = auth()->user();

        if (! $user || $user->hasRole(User::ROLE_SUPER_ADMIN)) {
            return;
        }

        $currentUserId = (int) $user->id;
        $currentHandlerId = (int) ($request->current_handler_id ?? 0);

        if (in_array($action, ['submit', 'reject', 'save_manager_final_attachment_draft', 'save_record_attachment_draft'], true)) {
            return;
        }

        if ($currentHandlerId !== $currentUserId) {
            throw ValidationException::withMessages([
                'action' => ['You are not assigned to take action on this procurement request.'],
            ]);
        }

        if ($action === 'asset_team_approve' && (int) ($request->assigned_team_leader_id ?? 0) !== $currentUserId) {
            throw ValidationException::withMessages([
                'action' => ['Only the selected Department Team Leader can forward this procurement request to Budget Department.'],
            ]);
        }

        if ($action === 'assign_budget_code' && (int) ($request->budget_team_leader_id ?? 0) !== $currentUserId) {
            throw ValidationException::withMessages([
                'action' => ['Only the selected Budget Department Team Leader can approve this procurement request.'],
            ]);
        }
    }

    protected function canRunActionFromStatus(string $from, string $action): bool
    {
        if ($action === 'reject') {
            return ! in_array($from, [ProcurementRequest::STATUS_COMPLETED, ProcurementRequest::STATUS_REJECTED], true);
        }

        return match ($action) {
            'submit' => $from === ProcurementRequest::STATUS_DRAFT,
            'manager_approve' => $from === ProcurementRequest::STATUS_MANAGER_REVIEW,
            'asset_team_approve' => $from === ProcurementRequest::STATUS_ASSET_TEAM_REVIEW,
            'assign_budget_code' => $from === ProcurementRequest::STATUS_BUDGET_TL_REVIEW,
            'final_manager_approve', 'save_manager_final_attachment_draft' => $from === ProcurementRequest::STATUS_FINAL_MANAGER_REVIEW,
            'save_record_attachment_draft', 'records_process' => $from === ProcurementRequest::STATUS_RECORDS_PROCESSING,
            'finance_complete' => $from === ProcurementRequest::STATUS_SENT_TO_FINANCE,
            default => false,
        };
    }

    protected function nextStatus(ProcurementRequest $request, string $from, string $action): string
    {
        if ($action === 'reject') {
            return ProcurementRequest::STATUS_REJECTED;
        }

        return match ($action) {
            'submit' => ProcurementRequest::STATUS_MANAGER_REVIEW,
            'manager_approve' => ProcurementRequest::STATUS_ASSET_TEAM_REVIEW,
            'asset_team_approve' => ProcurementRequest::STATUS_BUDGET_TL_REVIEW,
            'assign_budget_code' => ProcurementRequest::STATUS_FINAL_MANAGER_REVIEW,
            'final_manager_approve' => ProcurementRequest::STATUS_RECORDS_PROCESSING,
            'records_process' => ProcurementRequest::STATUS_COMPLETED,
            'finance_complete' => ProcurementRequest::STATUS_COMPLETED,
            default => $from,
        };
    }

    public function departments(?ProcurementRequest $request = null)
    {
        if (! Schema::hasTable('departments')) {
            return collect();
        }

        $query = Department::query()->with('office:id,name')->orderBy('name');

        if (Schema::hasColumn('departments', 'is_active')) {
            $query->where('is_active', true);
        }

        $departments = $query->get(['id', 'office_id', 'name', 'is_active']);

        if ($request) {
            $recommendedKeywords = $this->categoryDepartmentKeywords($request);
            if ($recommendedKeywords) {
                $filtered = $departments
                    ->filter(fn (Department $department) => $this->departmentNameMatchesAny($department, $recommendedKeywords))
                    ->values();

                return $filtered->isNotEmpty() ? $filtered : $departments;
            }
        }

        return $departments;
    }

    public function budgetDepartments()
    {
        return $this->departments()
            ->filter(fn (Department $department) => str_contains($this->normalizeText($department->name), 'budget') || str_contains($this->normalizeText($department->name), 'baajata'))
            ->values();
    }

    public function departmentTeamLeaders(int|string $departmentId)
    {
        return $this->departmentUsersByNormalizedRoles($departmentId, ['team-leader', 'department-head', 'team-leader-department-head']);
    }

    protected function resolveInitialHandlerId(?string $receiverType): ?int
    {
        $normalized = str_replace('-', '_', strtolower(trim((string) $receiverType)));

        $roles = match ($normalized) {
            'development_branch', 'development_head', 'head_of_development_branch' => [User::ROLE_HEAD_DEVELOPMENT_BRANCH],
            'service_head', 'service_branch', 'head_of_service_branch' => [User::ROLE_HEAD_SERVICE_BRANCH],
            default => [User::ROLE_MANAGER],
        };

        return $this->resolveFirstUserIdByRoles($roles);
    }

    protected function resolveFirstUserIdByRoles(array $roles): ?int
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roles))
            ->orderBy('id')
            ->value('id');
    }

    protected function resolveTeamLeaderIdByDepartment(?int $departmentId): ?int
    {
        if (! $departmentId) {
            return null;
        }

        return User::query()
            ->where('is_active', true)
            ->where('department_id', $departmentId)
            ->whereHas('roles', fn ($query) => $query->where('name', User::ROLE_TEAM_LEADER))
            ->orderBy('id')
            ->value('id');
    }

    protected function resolveBudgetDepartmentId(): ?int
    {
        return Department::query()
            ->where(fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%budget%'])->orWhereRaw('LOWER(name) LIKE ?', ['%baajata%']))
            ->orderBy('id')
            ->value('id');
    }

    protected function assertDepartmentExists(int $departmentId): void
    {
        if (! $departmentId || ! Department::query()->whereKey($departmentId)->exists()) {
            throw ValidationException::withMessages(['department_id' => ['Please select a valid department.']]);
        }
    }

    protected function assertBudgetDepartment(int $departmentId): void
    {
        $department = Department::query()->find($departmentId);
        if (! $department || (! str_contains($this->normalizeText($department->name), 'budget') && ! str_contains($this->normalizeText($department->name), 'baajata'))) {
            throw ValidationException::withMessages(['budget_department_id' => ['Please select the Budget Department.']]);
        }
    }

    protected function assertCategoryDepartmentRule(ProcurementRequest $request, int $departmentId): void
    {
        $keywords = $this->categoryDepartmentKeywords($request);
        if (! $keywords) {
            return;
        }

        $department = Department::query()->find($departmentId);
        if ($department && ! $this->departmentNameMatchesAny($department, $keywords)) {
            throw ValidationException::withMessages([
                'department_id' => [$this->categoryDepartmentValidationMessage($keywords)],
            ]);
        }
    }

    protected function assertUserHasAnyRole(?int $userId, array $roles, string $field): void
    {
        $exists = User::query()
            ->whereKey($userId)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roles))
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([$field => ['Please select a valid approver.']]);
        }
    }

    protected function assertUserInDepartmentWithRole(?int $userId, int $departmentId, string $role, string $field): void
    {
        $exists = User::query()
            ->whereKey($userId)
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', $role))
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([$field => ['Please select a valid Team Leader from the selected department.']]);
        }
    }

    protected function departmentUsersByNormalizedRoles(int|string $departmentId, array $normalizedRoles)
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'department_id')) {
            return collect();
        }

        return User::query()
            ->with('roles:id,name')
            ->where('is_active', true)
            ->where('department_id', $departmentId)
            ->whereHas('roles')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'department_id', 'is_active'])
            ->filter(fn (User $user) => $user->roles->contains(fn ($role) => in_array($this->normalizeRole((string) $role->name), $normalizedRoles, true)))
            ->unique('id')
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => 'active',
                'department_id' => $user->department_id,
                'role' => optional($user->roles->first())->name,
                'display_role' => optional($user->roles->first())->name,
                'roles' => $user->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values(),
            ])
            ->values();
    }

    protected function categoryDepartmentKeywords(ProcurementRequest $request): array
    {
        $request->loadMissing('category');
        $categoryName = $this->normalizeText((string) optional($request->category)->name);

        if (str_contains($categoryName, 'fixedasset') || str_contains($categoryName, 'asset')) {
            return ['asset'];
        }

        if (
            str_contains($categoryName, 'machinery') ||
            str_contains($categoryName, 'machinary') ||
            str_contains($categoryName, 'machine') ||
            str_contains($categoryName, 'mcihnery')
        ) {
            // Keep compatibility with the existing database spelling: "Mcihnery Department".
            // Also accept correct/older spellings so this workflow does not break existing data.
            return ['mcihnery', 'machinery', 'machinary', 'machine', 'machin'];
        }

        return [];
    }

    protected function departmentNameMatchesAny(Department $department, array $keywords): bool
    {
        $departmentName = $this->normalizeText((string) $department->name);

        foreach ($keywords as $keyword) {
            if (str_contains($departmentName, $this->normalizeText((string) $keyword))) {
                return true;
            }
        }

        return false;
    }

    protected function categoryDepartmentValidationMessage(array $keywords): string
    {
        if (in_array('asset', $keywords, true)) {
            return 'This procurement category must be forwarded to the Asset Department.';
        }

        if (array_intersect($keywords, ['mcihnery', 'machinery', 'machinary', 'machine', 'machin'])) {
            return 'This procurement category must be forwarded to the Mcihnery Department.';
        }

        return 'Please select the correct department for this procurement category.';
    }

    protected function normalizeRole(string $role): string
    {
        return trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', str_replace('&', 'and', strtolower($role)))), '-');
    }

    protected function normalizeText(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
    }

    protected function storeAttachments(ProcurementRequest $request, array $files): void
    {
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $path = $file->store('procurement', 'public');
                ProcurementAttachment::create([
                    'procurement_request_id' => $request->id,
                    'uploaded_by' => auth()->id(),
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize() ?: 0,
                ]);
            }
        }
    }

    protected function history(ProcurementRequest $request, string $action, ?string $from, ?string $to, ?string $note = null, array $meta = []): void
    {
        ProcurementHistory::create([
            'procurement_request_id' => $request->id,
            'actor_id' => auth()->id(),
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'metadata' => $meta,
        ]);
    }

    protected function nextNumber(string $prefix): string
    {
        return $prefix . '-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
