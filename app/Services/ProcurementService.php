<?php

namespace App\Services;

use App\Models\ProcurementAttachment;
use App\Models\ProcurementHistory;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcurementService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int)($filters['per_page'] ?? 10), 100));
        $user = auth()->user();

        return ProcurementRequest::query()
            ->with(['requester:id,name,email', 'currentHandler:id,name,email', 'category:id,name', 'procurementType:id,category_id,name'])
            ->when(
                $user && $user->hasAnyRole([User::ROLE_ASSET_TEAM_LEADER, User::ROLE_MACHINERY_TEAM_LEADER]),
                fn ($query) => $query->where('current_handler_id', $user->id)
            )
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn($q, $v) => $q->where(fn($qq) => $qq->where('request_no','like',"%{$v}%")->orWhere('title','like',"%{$v}%")->orWhere('reference_no','like',"%{$v}%")))
            ->latest()->paginate($perPage);
    }

    public function find(int|string $id): ProcurementRequest
    {
        return ProcurementRequest::with(['items','attachments','histories.actor:id,name,email,signature_path','requester:id,name,email','currentHandler:id,name,email','category:id,name','procurementType:id,category_id,name','managerSigner:id,name,signature_path,stamp_path,titer_path','assetSigner:id,name,signature_path,stamp_path,titer_path','budgetTlSigner:id,name,signature_path,stamp_path,titer_path','finalManagerSigner:id,name,signature_path,stamp_path,titer_path','recordsSigner:id,name,signature_path,stamp_path,titer_path','financeSigner:id,name,signature_path,stamp_path,titer_path'])->findOrFail($id);
    }

    public function create(array $data, ?array $files = []): ProcurementRequest
    {
        return DB::transaction(function () use ($data, $files) {
            $items = $data['items'] ?? [];
            unset($data['items'], $data['attachments']);
            $data['request_no'] = $this->nextNumber('PR');
            $data['requested_by'] = auth()->id();
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
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'action' => ['This action is not allowed for the current workflow status.'],
                ]);
            }

            $to = $this->nextStatus($request, $from, $action);
            $payload = ['status' => $to];

            match ($action) {
                'manager_approve' => $payload['manager_signed_by'] = auth()->id(),
                'asset_team_approve' => $payload['asset_signed_by'] = auth()->id(),
                'assign_budget_code' => $payload['budget_tl_signed_by'] = auth()->id(),
                'final_manager_approve' => $payload['final_manager_signed_by'] = auth()->id(),
                'records_process' => $payload['records_signed_by'] = auth()->id(),
                'finance_complete' => $payload['finance_signed_by'] = auth()->id(),
                default => null,
            };

            if ($action === 'submit') {
                $payload['submitted_at'] = now();
                $payload['current_handler_id'] = $this->resolveInitialHandlerId($data['receiver_type'] ?? null);
            }

            if ($action === 'manager_approve') {
                $payload['current_handler_id'] = $this->resolveForwardedTechnicalHandlerId(
                    $request,
                    $data['forward_to_role'] ?? null
                );

                if (! $payload['current_handler_id']) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'forward_to_role' => ['No active Asset Team Leader or Machinery Team Leader user was found for forwarding.'],
                    ]);
                }
            }

            if ($action === 'asset_team_approve') {
                $payload['current_handler_id'] = $this->resolveFirstUserIdByRoles([User::ROLE_PLANNING_BUDGET_TEAM_LEADER]);
            }

            if ($action === 'assign_budget_code') {
                $payload['current_handler_id'] = $this->resolveFirstUserIdByRoles([User::ROLE_MANAGER]);
            }

            if ($action === 'final_manager_approve') {
                $payload['current_handler_id'] = $this->resolveFirstUserIdByRoles([User::ROLE_RECORDS_OFFICE]);
            }

            if ($action === 'records_process') {
                $payload['current_handler_id'] = $this->resolveFirstUserIdByRoles([User::ROLE_FINANCE]);
            }
            if ($action === 'assign_budget_code') $payload['budget_code'] = $data['budget_code'] ?? $request->budget_code;
            if ($action === 'records_process') {
                $payload['reference_no'] = $data['reference_no'] ?? $request->reference_no ?? $this->nextNumber('PROC-REF');
                $payload['official_date'] = $data['official_date'] ?? now()->toDateString();
                if (array_key_exists('official_date_ec', $data)) {
                    $payload['official_date_ec'] = $data['official_date_ec'];
                }
            }
            if ($to === ProcurementRequest::STATUS_COMPLETED) $payload['completed_at'] = now();
            $request->update($payload);

            if ($action === 'asset_team_approve' && isset($data['items']) && is_array($data['items'])) {
                $request->items()->delete();
                foreach ($data['items'] as $item) {
                    $item['estimated_unit_cost'] = $item['estimated_unit_cost'] ?? 0;
                    $item['estimated_total_cost'] = $item['estimated_total_cost'] ?? 0;
                    $request->items()->create($item);
                }
            }
            $this->history($request, strtoupper($action), $from, $to, $data['note'] ?? $data['reason'] ?? null, $data);
            AuditLogService::log('procurement', strtoupper($action), $request, ['before' => ['status' => $from], 'after' => ['status' => $to], 'meta' => $data]);
            return $this->find($request->id);
        });
    }

    protected function canRunActionFromStatus(string $from, string $action): bool
    {
        if ($action === 'reject') {
            return ! in_array($from, [
                ProcurementRequest::STATUS_COMPLETED,
                ProcurementRequest::STATUS_REJECTED,
            ], true);
        }

        return match ($action) {
            'submit' => $from === ProcurementRequest::STATUS_DRAFT,
            'manager_approve' => $from === ProcurementRequest::STATUS_MANAGER_REVIEW,
            'asset_team_approve' => $from === ProcurementRequest::STATUS_ASSET_TEAM_REVIEW,
            'assign_budget_code' => $from === ProcurementRequest::STATUS_BUDGET_TL_REVIEW,
            'final_manager_approve' => $from === ProcurementRequest::STATUS_FINAL_MANAGER_REVIEW,
            'records_process' => $from === ProcurementRequest::STATUS_RECORDS_PROCESSING,
            'finance_complete' => $from === ProcurementRequest::STATUS_SENT_TO_FINANCE,
            default => false,
        };
    }

    protected function nextStatus(ProcurementRequest $request, string $from, string $action): string
    {
        if ($action === 'reject') return ProcurementRequest::STATUS_REJECTED;
        return match ($action) {
            'submit' => ProcurementRequest::STATUS_MANAGER_REVIEW,
            'manager_approve' => ProcurementRequest::STATUS_ASSET_TEAM_REVIEW,
            'asset_team_approve' => ProcurementRequest::STATUS_BUDGET_TL_REVIEW,
            'assign_budget_code' => ProcurementRequest::STATUS_FINAL_MANAGER_REVIEW,
            'final_manager_approve' => ProcurementRequest::STATUS_RECORDS_PROCESSING,
            'records_process' => ProcurementRequest::STATUS_SENT_TO_FINANCE,
            'finance_complete' => ProcurementRequest::STATUS_COMPLETED,
            default => $from,
        };
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

    protected function resolveForwardedTechnicalHandlerId(ProcurementRequest $request, ?string $forwardToRole = null): ?int
    {
        $requestedRole = $this->normalizeForwardRole($forwardToRole);

        if ($requestedRole) {
            return $this->resolveFirstUserIdByRoles([$requestedRole]);
        }

        return $this->resolveFirstUserIdByRoles([$this->recommendedTechnicalRole($request)]);
    }

    protected function recommendedTechnicalRole(ProcurementRequest $request): string
    {
        $request->loadMissing('category');
        $categoryName = strtolower(trim((string) optional($request->category)->name));

        return match ($categoryName) {
            'machinery', 'machinary' => User::ROLE_MACHINERY_TEAM_LEADER,
            'fixed asset', 'fixed assets' => User::ROLE_ASSET_TEAM_LEADER,
            default => User::ROLE_ASSET_TEAM_LEADER,
        };
    }

    protected function normalizeForwardRole(?string $forwardToRole): ?string
    {
        $normalized = str_replace(['-', ' '], '_', strtolower(trim((string) $forwardToRole)));

        return match ($normalized) {
            'asset_team_leader', 'asset', 'fixed_asset' => User::ROLE_ASSET_TEAM_LEADER,
            'machinery_team_leader', 'machinary_team_leader', 'machinery', 'machinary' => User::ROLE_MACHINERY_TEAM_LEADER,
            default => null,
        };
    }

    protected function resolveFirstUserIdByRoles(array $roles): ?int
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roles))
            ->orderBy('id')
            ->value('id');
    }

    protected function storeAttachments(ProcurementRequest $request, array $files): void
    {
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $path = $file->store('procurement', 'public');
                ProcurementAttachment::create(['procurement_request_id'=>$request->id,'uploaded_by'=>auth()->id(),'original_name'=>$file->getClientOriginalName(),'stored_path'=>$path,'mime_type'=>$file->getClientMimeType(),'size_bytes'=>$file->getSize() ?: 0]);
            }
        }
    }

    protected function history(ProcurementRequest $request, string $action, ?string $from, ?string $to, ?string $note = null, array $meta = []): void
    {
        ProcurementHistory::create(['procurement_request_id'=>$request->id,'actor_id'=>auth()->id(),'action'=>$action,'from_status'=>$from,'to_status'=>$to,'note'=>$note,'metadata'=>$meta]);
    }

    protected function nextNumber(string $prefix): string
    {
        return $prefix . '-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
