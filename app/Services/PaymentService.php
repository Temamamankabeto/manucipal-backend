<?php

namespace App\Services;

use App\Models\PaymentAttachment;
use App\Models\PaymentHistory;
use App\Models\PaymentRequest;
use App\Models\Budget;
use App\Models\BudgetTransaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 10), 100));

        return PaymentRequest::query()
            ->with(['requester:id,name,email', 'currentHandler:id,name,email', 'paymentType:id,category_id,name', 'budget:id,bi_code,budget_code,account_name,allocated_amount,used_amount,remaining_amount,status'])
            ->when(! $this->canViewAllPayments(), function ($q) {
                $userId = auth()->id();
                $workflowStatuses = $this->visibleWorkflowStatusesForUser();

                $q->where(function ($qq) use ($userId, $workflowStatuses) {
                    $qq->where('requested_by', $userId)
                        ->orWhere('current_handler_id', $userId)
                        ->orWhere('manager_signed_by', $userId)
                        ->orWhere('budget_tl_signed_by', $userId)
                        ->orWhere('budget_expert_signed_by', $userId)
                        ->orWhere('budget_tl_final_signed_by', $userId)
                        ->orWhere('manager_final_signed_by', $userId)
                        ->orWhere('records_signed_by', $userId)
                        ->orWhere('finance_signed_by', $userId);

                    if (! empty($workflowStatuses)) {
                        $qq->orWhereIn('status', $workflowStatuses);
                    }
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('payment_no', 'like', "%{$v}%")
                    ->orWhere('title', 'like', "%{$v}%")
                    ->orWhere('reference_no', 'like', "%{$v}%")
                    ->orWhere('document_no', 'like', "%{$v}%");
            }))
            ->latest()
            ->paginate($perPage);
    }

    public function find(int|string $id): PaymentRequest
    {
        return PaymentRequest::with([
            'items',
            'perDiem.employees',
            'attachments',
            'histories.actor:id,name,email,signature_path,stamp_path,titer_path',
            'histories.actor.roles:id,name',
            'requester:id,name,email',
            'currentHandler:id,name,email,signature_path,stamp_path,titer_path',
            'currentHandler.roles:id,name',
            'paymentCategory:id,name',
            'paymentType:id,category_id,name',
            'budget:id,bi_code,budget_code,account_name,allocated_amount,used_amount,remaining_amount,status',
            'managerSigner:id,name,signature_path,stamp_path,titer_path',
            'managerSigner.roles:id,name',
            'budgetTlSigner:id,name,signature_path,stamp_path,titer_path',
            'budgetTlSigner.roles:id,name',
            'budgetExpertSigner:id,name,signature_path,stamp_path,titer_path',
            'budgetExpertSigner.roles:id,name',
            'budgetTlFinalSigner:id,name,signature_path,stamp_path,titer_path',
            'budgetTlFinalSigner.roles:id,name',
            'managerFinalSigner:id,name,signature_path,stamp_path,titer_path',
            'managerFinalSigner.roles:id,name',
            'recordsSigner:id,name,signature_path,stamp_path,titer_path',
            'recordsSigner.roles:id,name',
            'financeSigner:id,name,signature_path,stamp_path,titer_path',
            'financeSigner.roles:id,name',
        ])->findOrFail($id);
    }


    public function planningBudgetExperts()
    {
        $allowedRoles = [
            'planning-budget-experts',
            'planning-budget-expert',
            'planning-and-budget-expert',
            'planing-and-budget-expert',
            'budget-expert',
        ];

        return User::query()
            ->with(['roles:id,name'])
            ->where('is_active', true)
            ->whereHas('roles')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'is_active'])
            ->filter(function (User $user) use ($allowedRoles) {
                return $user->roles->contains(function ($role) use ($allowedRoles) {
                    return in_array($this->normalizeRole((string) $role->name), $allowedRoles, true);
                });
            })
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => 'active',
                'role' => 'Planning & Budget Expert',
                'display_role' => 'Planning & Budget Expert',
                'roles' => $user->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ])->values(),
            ])
            ->values();
    }

    public function create(array $data, ?array $files = []): PaymentRequest
    {
        return DB::transaction(function () use ($data, $files) {
            $items = $data['items'] ?? [];
            unset($data['items'], $data['attachments']);

            if (! empty($data['payment_category_id'])) {
                $category = \App\Models\PaymentCategory::query()->find($data['payment_category_id']);
                $data['payment_category'] = $data['payment_category'] ?? $category?->name;
            }

            if (! empty($data['budget_id'])) {
                $budget = Budget::query()->find($data['budget_id']);
                $data['budget_code'] = $data['budget_code'] ?? $budget?->budget_code;
            }

            if (! empty($data['payment_type_id'])) {
                $type = \App\Models\PaymentType::query()->find($data['payment_type_id']);
                $data['request_type'] = $data['request_type'] ?? $type?->name;
            }

            $data['request_type'] = $data['request_type'] ?? 'Payment Request';
            $data['payment_category'] = $data['payment_category'] ?? 'General Payment';
            $data['title'] = $data['title'] ?? ($data['requesting_entity'] ?? 'Payment Request');
            $data['payment_no'] = $this->nextNumber('PAY');
            $data['requested_by'] = auth()->id();
            $data['status'] = PaymentRequest::STATUS_DRAFT;

            $request = PaymentRequest::create($data);

            $total = 0;
            foreach ($items as $item) {
                $item['total_price'] = (float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 1);
                $total += $item['total_price'];
                $request->items()->create($item);
            }

            if ($total > 0) {
                $request->update(['amount' => $total]);
            }

            $this->storeAttachments($request, $files ?? []);
            $this->history($request, 'CREATE', null, $request->status, 'Payment request created');

            if (class_exists(AuditLogService::class)) {
                AuditLogService::log('payment', 'CREATE', $request, ['after' => $request->toArray()]);
            }

            return $this->find($request->id);
        });
    }

    public function update(PaymentRequest $request, array $data, ?array $files = []): PaymentRequest
    {
        return DB::transaction(function () use ($request, $data, $files) {
            $before = $request->toArray();
            $items = $data['items'] ?? null;
            unset($data['items'], $data['attachments']);

            $request->update($data);

            if (is_array($items)) {
                $request->items()->delete();
                $total = 0;

                foreach ($items as $item) {
                    $item['total_price'] = (float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 1);
                    $total += $item['total_price'];
                    $request->items()->create($item);
                }

                $request->update(['amount' => $total]);
            }

            $this->storeAttachments($request, $files ?? []);
            $this->history($request, 'UPDATE', $before['status'] ?? null, $request->status, 'Payment request updated');

            if (class_exists(AuditLogService::class)) {
                AuditLogService::log('payment', 'UPDATE', $request, ['before' => $before, 'after' => $request->fresh()->toArray()]);
            }

            return $this->find($request->id);
        });
    }

    public function action(PaymentRequest $request, string $action, array $data = []): PaymentRequest
    {
        return DB::transaction(function () use ($request, $action, $data) {
            $from = $request->status;

            if (! $this->canRunActionFromStatus($from, $action)) {
                throw ValidationException::withMessages([
                    'action' => ['This action is not allowed for the current workflow status.'],
                ]);
            }

            if (in_array($from, [PaymentRequest::STATUS_MANAGER_REVIEW, PaymentRequest::STATUS_MANAGER_FINAL_REVIEW], true)
                && $request->current_handler_id
                && (int) $request->current_handler_id !== (int) auth()->id()
                && ! $this->canViewAllPayments()) {
                throw ValidationException::withMessages([
                    'action' => ['This payment request is assigned to another manager or branch head.'],
                ]);
            }

            if ($action === 'reject') {
                $returnStep = $this->previousStepForRejection($request, $from);

                if (empty($data['reason']) && empty($data['note'])) {
                    throw ValidationException::withMessages([
                        'reason' => ['Please provide a rejection reason.'],
                    ]);
                }

                $to = $returnStep['status'];
                $payload = [
                    'status' => $to,
                    'current_handler_id' => $returnStep['handler_id'],
                ];

                $data['reason'] = $data['reason'] ?? $data['note'];
                $data['returned_to_status'] = $to;
                $data['returned_to_user_id'] = $returnStep['handler_id'];
            } else {
                $to = $this->nextStatus($from, $action);

                $payload = ['status' => $to];

                match ($action) {
                    'manager_approve' => $payload['manager_signed_by'] = auth()->id(),
                    'budget_tl_approve' => $payload['budget_tl_signed_by'] = auth()->id(),
                    'expert_complete' => $payload['budget_expert_signed_by'] = auth()->id(),
                    'budget_tl_final_approve' => $payload['budget_tl_final_signed_by'] = auth()->id(),
                    'manager_final_approve' => $payload['manager_final_signed_by'] = auth()->id(),
                    'records_process' => $payload['records_signed_by'] = auth()->id(),
                    'finance_complete' => $payload['finance_signed_by'] = auth()->id(),
                    default => null,
                };
            }

            if ($action === 'submit') {
                $handlerId = $data['send_to_user_id'] ?? $data['current_handler_id'] ?? $request->current_handler_id;

                if (! $handlerId) {
                    throw ValidationException::withMessages([
                        'send_to_user_id' => ['Please select Manager, Head of Development Branch, or Head of Service Branch.'],
                    ]);
                }

                $payload['submitted_at'] = now();
                $payload['current_handler_id'] = $handlerId;
            }

            if ($action === 'manager_approve') {
                $payload['current_handler_id'] = null;
            }

            if ($action === 'budget_tl_approve') {
                $expertId = $data['expert_user_id'] ?? null;

                if (! $expertId) {
                    throw ValidationException::withMessages([
                        'expert_user_id' => ['Please select a Planning & Budget Expert.'],
                    ]);
                }

                $payload['current_handler_id'] = $expertId;
            }

            if ($action === 'expert_complete') {
                $payload['current_handler_id'] = $request->budget_tl_signed_by;
            }

            if ($action === 'budget_tl_final_approve') {
                $payload['current_handler_id'] = $request->manager_signed_by;
            }

            if (in_array($action, ['manager_final_approve', 'records_process', 'finance_complete'], true)) {
                $payload['current_handler_id'] = null;
            }

            if ($action === 'expert_complete' && ! empty($data['per_diem']) && is_array($data['per_diem'])) {
                $payload['amount'] = $this->storePerDiem($request, $data['per_diem']);
            } elseif ($action === 'expert_complete' && ! empty($data['items']) && is_array($data['items'])) {
                $request->items()->delete();

                $total = 0;

                foreach ($data['items'] as $item) {
                    $lineTotal = (float) ($item['amount'] ?? $item['unit_price'] ?? 0);
                    $total += $lineTotal;

                    $request->items()->create([
                        'description' => $item['description'],
                        'invoice_no' => $item['budget_code'] ?? $data['budget_code'] ?? null,
                        'quantity' => 1,
                        'unit' => 'service',
                        'unit_price' => $lineTotal,
                        'total_price' => $lineTotal,
                        'remark' => $item['remark'] ?? null,
                    ]);
                }

                $payload['amount'] = $total;
            }

            if ($action === 'expert_complete') {
                if (! empty($data['budget_id'])) {
                    $budget = Budget::query()->find($data['budget_id']);
                    $payload['budget_id'] = $budget?->id;
                    $payload['budget_code'] = $budget?->budget_code ?? ($data['budget_code'] ?? $request->budget_code);
                } else {
                    $payload['budget_code'] = $data['budget_code'] ?? $request->budget_code;
                }
                $payload['office_code'] = $data['office_code'] ?? $request->office_code;
                $payload['budget_year'] = $data['budget_year'] ?? $request->budget_year;
            }

            if ($action === 'records_process') {
                $payload['reference_no'] = $data['reference_no'] ?? $request->reference_no ?? $this->nextNumber('PAY-REF');
                $payload['official_date'] = $data['official_date'] ?? now()->toDateString();
            }

            if ($action === 'finance_complete') {
                $payload['paid_by'] = auth()->id();
                $payload['paid_amount'] = $data['paid_amount'] ?? $request->amount;
                $payload['paid_date'] = $data['paid_date'] ?? now()->toDateString();
                $payload['voucher_no'] = $data['voucher_no'] ?? $request->voucher_no;
                $payload['finance_remark'] = $data['finance_remark'] ?? $data['note'] ?? null;
            }

            if ($to === PaymentRequest::STATUS_PAYMENT_COMPLETED) {
                $payload['completed_at'] = now();
            }

            $request->update($payload);

            if ($action === 'finance_complete') {
                $this->deductBudgetForPayment($request->fresh());
            }
            $this->history($request, strtoupper($action), $from, $to, $data['note'] ?? $data['reason'] ?? null, $data);

            if (class_exists(AuditLogService::class)) {
                AuditLogService::log('payment', strtoupper($action), $request, [
                    'before' => ['status' => $from],
                    'after' => ['status' => $to],
                    'meta' => $data,
                ]);
            }

            return $this->find($request->id);
        });
    }

    protected function canRunActionFromStatus(string $from, string $action): bool
    {
        if ($action === 'reject') {
            return ! in_array($from, [
                PaymentRequest::STATUS_DRAFT,
                PaymentRequest::STATUS_PAYMENT_COMPLETED,
                PaymentRequest::STATUS_REJECTED,
            ], true);
        }

        return match ($action) {
            'submit' => $from === PaymentRequest::STATUS_DRAFT,
            'manager_approve' => $from === PaymentRequest::STATUS_MANAGER_REVIEW,
            'budget_tl_approve' => $from === PaymentRequest::STATUS_BUDGET_TL_REVIEW,
            'expert_complete' => $from === PaymentRequest::STATUS_BUDGET_EXPERT_PROCESSING,
            'budget_tl_final_approve' => $from === PaymentRequest::STATUS_BUDGET_TL_FINAL_REVIEW,
            'manager_final_approve' => $from === PaymentRequest::STATUS_MANAGER_FINAL_REVIEW,
            'records_process' => $from === PaymentRequest::STATUS_RECORDS_PROCESSING,
            'finance_complete' => $from === PaymentRequest::STATUS_SENT_TO_FINANCE,
            default => false,
        };
    }

    protected function nextStatus(string $from, string $action): string
    {
        return match ($action) {
            'submit' => PaymentRequest::STATUS_MANAGER_REVIEW,
            'manager_approve' => PaymentRequest::STATUS_BUDGET_TL_REVIEW,
            'budget_tl_approve' => PaymentRequest::STATUS_BUDGET_EXPERT_PROCESSING,
            'expert_complete' => PaymentRequest::STATUS_BUDGET_TL_FINAL_REVIEW,
            'budget_tl_final_approve' => PaymentRequest::STATUS_MANAGER_FINAL_REVIEW,
            'manager_final_approve' => PaymentRequest::STATUS_RECORDS_PROCESSING,
            'records_process' => PaymentRequest::STATUS_SENT_TO_FINANCE,
            'finance_complete' => PaymentRequest::STATUS_PAYMENT_COMPLETED,
            default => $from,
        };
    }


    protected function previousStepForRejection(PaymentRequest $request, string $from): array
    {
        return match ($from) {
            PaymentRequest::STATUS_MANAGER_REVIEW => [
                'status' => PaymentRequest::STATUS_DRAFT,
                'handler_id' => $request->requested_by,
            ],
            PaymentRequest::STATUS_BUDGET_TL_REVIEW => [
                'status' => PaymentRequest::STATUS_MANAGER_REVIEW,
                'handler_id' => $request->manager_signed_by
                    ?: $this->latestActorBeforeStatus($request, PaymentRequest::STATUS_BUDGET_TL_REVIEW)
                    ?: $request->current_handler_id,
            ],
            PaymentRequest::STATUS_BUDGET_EXPERT_PROCESSING => [
                'status' => PaymentRequest::STATUS_BUDGET_TL_REVIEW,
                'handler_id' => $request->budget_tl_signed_by
                    ?: $this->latestActorBeforeStatus($request, PaymentRequest::STATUS_BUDGET_EXPERT_PROCESSING)
                    ?: $request->current_handler_id,
            ],
            PaymentRequest::STATUS_BUDGET_TL_FINAL_REVIEW => [
                'status' => PaymentRequest::STATUS_BUDGET_EXPERT_PROCESSING,
                'handler_id' => $request->budget_expert_signed_by
                    ?: $this->latestActorBeforeStatus($request, PaymentRequest::STATUS_BUDGET_TL_FINAL_REVIEW)
                    ?: $request->current_handler_id,
            ],
            PaymentRequest::STATUS_MANAGER_FINAL_REVIEW => [
                'status' => PaymentRequest::STATUS_BUDGET_TL_FINAL_REVIEW,
                'handler_id' => $request->budget_tl_final_signed_by
                    ?: $request->budget_tl_signed_by
                    ?: $this->latestActorBeforeStatus($request, PaymentRequest::STATUS_MANAGER_FINAL_REVIEW)
                    ?: $request->current_handler_id,
            ],
            PaymentRequest::STATUS_RECORDS_PROCESSING => [
                'status' => PaymentRequest::STATUS_MANAGER_FINAL_REVIEW,
                'handler_id' => $request->manager_final_signed_by
                    ?: $request->manager_signed_by
                    ?: $this->latestActorBeforeStatus($request, PaymentRequest::STATUS_RECORDS_PROCESSING)
                    ?: $request->current_handler_id,
            ],
            PaymentRequest::STATUS_SENT_TO_FINANCE => [
                'status' => PaymentRequest::STATUS_RECORDS_PROCESSING,
                'handler_id' => $request->records_signed_by
                    ?: $this->latestActorBeforeStatus($request, PaymentRequest::STATUS_SENT_TO_FINANCE)
                    ?: $request->current_handler_id,
            ],
            default => throw ValidationException::withMessages([
                'action' => ['This payment cannot be rejected from the current workflow status.'],
            ]),
        };
    }

    protected function latestActorBeforeStatus(PaymentRequest $request, string $status): ?int
    {
        $actorId = PaymentHistory::query()
            ->where('payment_request_id', $request->id)
            ->where('to_status', $status)
            ->whereNotIn('action', ['REJECT', 'UPDATE', 'CREATE'])
            ->latest('id')
            ->value('actor_id');

        return $actorId ? (int) $actorId : null;
    }


    protected function deductBudgetForPayment(PaymentRequest $request): void
    {
        if (! $request->budget_id) {
            throw ValidationException::withMessages([
                'budget_id' => ['Please select a budget code before processing this payment.'],
            ]);
        }

        if (BudgetTransaction::query()
            ->where('payment_request_id', $request->id)
            ->where('type', BudgetTransaction::TYPE_DEBIT)
            ->exists()) {
            return;
        }

        $budget = Budget::query()->lockForUpdate()->findOrFail($request->budget_id);
        $amount = (float) $request->amount;

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount must be greater than zero before processing.'],
            ]);
        }

        if ((float) $budget->remaining_amount < $amount) {
            throw ValidationException::withMessages([
                'budget_id' => ['Insufficient budget balance for budget code ' . $budget->budget_code . '.'],
            ]);
        }

        $before = (float) $budget->remaining_amount;
        $used = (float) $budget->used_amount + $amount;
        $after = $before - $amount;

        $budget->update([
            'used_amount' => $used,
            'remaining_amount' => $after,
        ]);

        BudgetTransaction::create([
            'budget_id' => $budget->id,
            'payment_request_id' => $request->id,
            'transaction_no' => $this->nextNumber('BT'),
            'type' => BudgetTransaction::TYPE_DEBIT,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'remarks' => 'Budget deducted for payment ' . ($request->payment_no ?? $request->id),
            'created_by' => auth()->id(),
        ]);
    }

    protected function storePerDiem(PaymentRequest $request, array $payload): float
    {
        $common = $payload['common'] ?? [];
        $employees = collect($payload['employees'] ?? [])
            ->filter(fn ($employee) => ! empty($employee['employee_name']))
            ->values();

        if ($employees->isEmpty()) {
            throw ValidationException::withMessages([
                'per_diem.employees' => ['Please add at least one employee per diem form before completing.'],
            ]);
        }

        $perDiem = $request->perDiem()->updateOrCreate(
            ['payment_request_id' => $request->id],
            [
                'program' => $common['program'] ?? null,
                'purpose' => $common['purpose'] ?? null,
                'pi_code' => $common['pi_code'] ?? null,
                'budget_code' => $common['budget_code'] ?? ($payload['budget_code'] ?? null),
                'office_name' => $common['office_name'] ?? null,
                'departure_location' => $common['departure_location'] ?? null,
                'destination' => $common['destination'] ?? null,
                'departure_date' => $common['departure_date'] ?? null,
                'return_date' => $common['return_date'] ?? null,
                'transport_allowance' => (float) ($common['transport_allowance'] ?? 0),
                'daily_per_diem_rate' => (float) ($common['daily_per_diem_rate'] ?? 0),
                'approved_budget' => (float) ($common['approved_budget'] ?? 0),
                'metadata' => $common['metadata'] ?? null,
            ]
        );

        $perDiem->employees()->delete();

        $totalPerDiem = 0;
        $totalTransport = 0;
        $totalFuel = 0;
        $totalOther = 0;

        foreach ($employees as $employee) {
            $days = (float) ($employee['number_of_days'] ?? 0);
            $dailyRate = (float) ($employee['daily_rate'] ?? $common['daily_per_diem_rate'] ?? 0);
            $breakfast = (float) ($employee['breakfast_deduction'] ?? 0);
            $lunch = (float) ($employee['lunch_deduction'] ?? 0);
            $dinner = (float) ($employee['dinner_deduction'] ?? 0);
            $accommodation = (float) ($employee['accommodation_deduction'] ?? 0);
            $transport = (float) ($employee['transport_cost'] ?? 0);
            $fuel = (float) ($employee['fuel_cost'] ?? 0);
            $other = (float) ($employee['other_cost'] ?? 0);
            $calculatedPerDiem = max(0, ($days * $dailyRate) - $breakfast - $lunch - $dinner - $accommodation);
            $totalPayable = $calculatedPerDiem + $transport + $fuel + $other;

            $totalPerDiem += $calculatedPerDiem;
            $totalTransport += $transport;
            $totalFuel += $fuel;
            $totalOther += $other;

            $perDiem->employees()->create([
                'employee_name' => $employee['employee_name'],
                'salary_level' => $employee['salary_level'] ?? null,
                'salary_amount' => (float) ($employee['salary_amount'] ?? 0),
                'transportation_type' => $employee['transportation_type'] ?? null,
                'departure_location' => $employee['departure_location'] ?? ($common['departure_location'] ?? null),
                'destination' => $employee['destination'] ?? ($common['destination'] ?? null),
                'departure_date' => $employee['departure_date'] ?? ($common['departure_date'] ?? null),
                'departure_time' => $employee['departure_time'] ?? null,
                'return_date' => $employee['return_date'] ?? ($common['return_date'] ?? null),
                'return_time' => $employee['return_time'] ?? null,
                'number_of_days' => $days,
                'breakfast_deduction' => $breakfast,
                'lunch_deduction' => $lunch,
                'dinner_deduction' => $dinner,
                'accommodation_deduction' => $accommodation,
                'transport_cost' => $transport,
                'fuel_cost' => $fuel,
                'other_cost' => $other,
                'daily_rate' => $dailyRate,
                'calculated_per_diem' => $calculatedPerDiem,
                'total_payable' => $totalPayable,
                'work_description' => $employee['work_description'] ?? null,
                'is_selected' => (bool) ($employee['is_selected'] ?? true),
                'metadata' => $employee['metadata'] ?? null,
            ]);
        }

        $grandTotal = $totalPerDiem + $totalTransport + $totalFuel + $totalOther;

        $perDiem->update([
            'total_per_diem' => $totalPerDiem,
            'total_transport' => $totalTransport,
            'total_fuel' => $totalFuel,
            'total_other' => $totalOther,
            'grand_total' => $grandTotal,
        ]);

        $request->items()->delete();
        $request->items()->create([
            'description' => 'Per diem payment for ' . $employees->count() . ' employee(s)',
            'invoice_no' => $common['budget_code'] ?? $request->budget_code,
            'quantity' => $employees->count(),
            'unit' => 'employee',
            'unit_price' => $grandTotal,
            'total_price' => $grandTotal,
            'remark' => 'Generated from Per Diem employee forms',
        ]);

        return $grandTotal;
    }

    protected function visibleWorkflowStatusesForUser(): array
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'roles')) {
            return [];
        }

        $roles = $user->roles()->pluck('name')
            ->map(fn ($role) => $this->normalizeRole((string) $role))
            ->all();

        $statuses = [];

        if (array_intersect($roles, ['manager', 'municipal-manager', 'head-of-development-branch', 'development-branch-head', 'head-development-branch', 'head-of-service-branch', 'service-branch-head', 'head-service-branch'])) {
            $statuses[] = PaymentRequest::STATUS_MANAGER_REVIEW;
            $statuses[] = PaymentRequest::STATUS_MANAGER_FINAL_REVIEW;
        }

        if (array_intersect($roles, ['planning-budget-team-leader', 'planning-and-budget-team-leader', 'budget-team-leader', 'planning-budget-tl'])) {
            $statuses[] = PaymentRequest::STATUS_BUDGET_TL_REVIEW;
            $statuses[] = PaymentRequest::STATUS_BUDGET_TL_FINAL_REVIEW;
        }

        if (array_intersect($roles, ['planning-budget-expert', 'planning-and-budget-expert', 'planning-budget-experts', 'budget-expert'])) {
            $statuses[] = PaymentRequest::STATUS_BUDGET_EXPERT_PROCESSING;
        }

        if (array_intersect($roles, ['records-office', 'record-office', 'record-officer', 'records-officer', 'secretary'])) {
            $statuses[] = PaymentRequest::STATUS_RECORDS_PROCESSING;
        }

        if (array_intersect($roles, ['finance', 'finance-department', 'finance-accountant', 'accountant', 'finance-officer', 'cashier'])) {
            $statuses[] = PaymentRequest::STATUS_SENT_TO_FINANCE;
        }

        return array_values(array_unique($statuses));
    }

    protected function normalizeRole(string $role): string
    {
        return trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', str_replace('&', 'and', strtolower($role)))), '-');
    }

    protected function canViewAllPayments(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
            return true;
        }

        $roles = method_exists($user, 'roles')
            ? $user->roles()->pluck('name')->map(fn ($role) => strtolower((string) $role))->all()
            : [];

        return in_array('super admin', $roles, true) || in_array('admin', $roles, true);
    }

    protected function storeAttachments(PaymentRequest $request, array $files): void
    {
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $path = $file->store('payment', 'public');

                PaymentAttachment::create([
                    'payment_request_id' => $request->id,
                    'uploaded_by' => auth()->id(),
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize() ?: 0,
                ]);
            }
        }
    }

    protected function history(PaymentRequest $request, string $action, ?string $from, ?string $to, ?string $note = null, array $meta = []): void
    {
        PaymentHistory::create([
            'payment_request_id' => $request->id,
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
