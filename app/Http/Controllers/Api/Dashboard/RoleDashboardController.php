<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\PaymentRequest;
use App\Models\ProcurementRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RoleDashboardController extends Controller
{
    private const SCOPES = [
        'super_admin',
        'manager',
        'head_of_development_branch',
        'head_of_service_branch',
        'team_leader',
        'expert',
        'secretory',
        'accountant',
        'record_officer',
    ];

    public function roleSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'string', 'in:' . implode(',', self::SCOPES)],
            'tab' => ['nullable', 'string', 'in:budget,procurement,payment'],
            'fiscal_year' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:120'],
            'bi_code' => ['nullable', 'string', 'max:120'],
            'account_code' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:80'],
            'period' => ['nullable', 'string', 'in:all,this_week,this_month,custom'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $scope = $validated['scope'];

        $payments = $this->paymentQuery($scope, $user->id, $validated)->get();
        $procurements = $this->procurementQuery($scope, $user->id, $validated)->get();
        $budgets = $this->budgetQuery($scope, $user->id, $validated)->get();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard loaded successfully',
            'data' => [
                'payments' => $payments->map(fn (PaymentRequest $payment) => $this->paymentRow($payment))->values(),
                'procurements' => $procurements->map(fn (ProcurementRequest $procurement) => $this->procurementRow($procurement))->values(),
                'budgets' => $budgets->map(fn (Budget $budget) => $this->budgetRow($budget))->values(),
                'charts' => $this->charts($payments, $procurements, $budgets),
            ],
            'meta' => [
                'scope' => $scope,
                'tab' => $validated['tab'] ?? 'budget',
            ],
        ]);
    }

    private function paymentQuery(string $scope, int $userId, array $filters): Builder
    {
        $query = PaymentRequest::query()
            ->with(['paymentCategory:id,name', 'paymentType:id,name', 'budget:id,bi_code,budget_code,account_name,fiscal_year'])
            ->latest();

        match ($scope) {
            'super_admin' => null,
            'manager' => $query->where(fn (Builder $q) => $this->relatedPayment($q, $userId)),
            'head_of_development_branch', 'head_of_service_branch' => $query->whereHas('histories', fn (Builder $q) => $q->where('actor_id', $userId)),
            'team_leader' => null,
            'expert' => $query->where(fn (Builder $q) => $q->where('current_handler_id', $userId)->orWhere('budget_expert_signed_by', $userId)->orWhereHas('histories', fn (Builder $h) => $h->where('actor_id', $userId))),
            'secretory', 'record_officer' => $query->where(fn (Builder $q) => $q->where('current_handler_id', $userId)->orWhere('records_signed_by', $userId)->orWhere('status', PaymentRequest::STATUS_RECORDS_PROCESSING)),
            'accountant' => $query->where(fn (Builder $q) => $q->where('status', PaymentRequest::STATUS_SENT_TO_FINANCE)->orWhereNotNull('finance_signed_by')->orWhereNotNull('paid_by')),
            default => null,
        };

        $this->applyPaymentFilters($query, $filters);

        return $query->limit(500);
    }

    private function procurementQuery(string $scope, int $userId, array $filters): Builder
    {
        $query = ProcurementRequest::query()
            ->with(['category:id,name', 'procurementType:id,name,category_id', 'items:id,procurement_request_id,estimated_total_cost'])
            ->latest();

        match ($scope) {
            'super_admin' => null,
            'manager' => $query->where(fn (Builder $q) => $this->relatedProcurement($q, $userId)),
            'head_of_development_branch', 'head_of_service_branch' => $query->whereHas('histories', fn (Builder $q) => $q->where('actor_id', $userId)),
            'team_leader' => null,
            'expert' => $query->where(fn (Builder $q) => $q->where('current_handler_id', $userId)->orWhere('budget_expert_signed_by', $userId)->orWhereHas('histories', fn (Builder $h) => $h->where('actor_id', $userId))),
            'secretory', 'record_officer' => $query->where(fn (Builder $q) => $q->where('current_handler_id', $userId)->orWhere('records_signed_by', $userId)->orWhere('status', ProcurementRequest::STATUS_RECORDS_PROCESSING)),
            'accountant' => $query->where(fn (Builder $q) => $q->where('status', ProcurementRequest::STATUS_SENT_TO_FINANCE)->orWhereNotNull('finance_signed_by')),
            default => null,
        };

        $this->applyProcurementFilters($query, $filters);

        return $query->limit(500);
    }

    private function budgetQuery(string $scope, int $userId, array $filters): Builder
    {
        $query = Budget::query()->withCount('transactions')->latest();

        if (false) {
            $query->where(function (Builder $q) use ($userId) {
                $q->whereHas('payments', fn (Builder $p) => $p->where('requested_by', $userId))
                    ->orWhere('created_by', $userId);
            });
        }

        if (!empty($filters['fiscal_year'])) {
            $query->where('fiscal_year', $filters['fiscal_year']);
        }
        if (!empty($filters['bi_code'])) {
            $query->where('bi_code', 'like', '%' . $filters['bi_code'] . '%');
        }
        if (!empty($filters['account_code'])) {
            $query->where('budget_code', 'like', '%' . $filters['account_code'] . '%');
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        return $query->limit(500);
    }

    private function relatedPayment(Builder $query, int $userId): void
    {
        $query->where('requested_by', $userId)
            ->orWhere('current_handler_id', $userId)
            ->orWhere('manager_signed_by', $userId)
            ->orWhere('manager_final_signed_by', $userId)
            ->orWhereHas('histories', fn (Builder $h) => $h->where('actor_id', $userId));
    }

    private function relatedProcurement(Builder $query, int $userId): void
    {
        $query->where('requested_by', $userId)
            ->orWhere('current_handler_id', $userId)
            ->orWhere('manager_signed_by', $userId)
            ->orWhere('final_manager_signed_by', $userId)
            ->orWhereHas('histories', fn (Builder $h) => $h->where('actor_id', $userId));
    }

    private function procurementKind(Builder $query, string $kind): void
    {
        $query->whereHas('category', fn (Builder $q) => $q->where('name', 'like', '%' . $kind . '%'))
            ->orWhereHas('procurementType', fn (Builder $q) => $q->where('name', 'like', '%' . $kind . '%'));
    }

    private function applyPaymentFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['fiscal_year'])) {
            $query->where(fn (Builder $q) => $q->where('budget_year', $filters['fiscal_year'])->orWhereHas('budget', fn (Builder $b) => $b->where('fiscal_year', $filters['fiscal_year'])));
        }
        if (!empty($filters['category'])) {
            $query->whereHas('paymentCategory', fn (Builder $q) => $q->where('name', 'like', '%' . $filters['category'] . '%'));
        }
        if (!empty($filters['type'])) {
            $query->whereHas('paymentType', fn (Builder $q) => $q->where('name', 'like', '%' . $filters['type'] . '%'));
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        $this->applyDateFilters($query, $filters);
    }

    private function applyProcurementFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['fiscal_year'])) {
            $query->whereYear('official_date', $filters['fiscal_year']);
        }
        if (!empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $q) => $q->where('name', 'like', '%' . $filters['category'] . '%'));
        }
        if (!empty($filters['type'])) {
            $query->whereHas('procurementType', fn (Builder $q) => $q->where('name', 'like', '%' . $filters['type'] . '%'));
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        $this->applyDateFilters($query, $filters);
    }

    private function applyDateFilters(Builder $query, array $filters): void
    {
        $period = $filters['period'] ?? 'all';
        if ($period === 'this_week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        }
        if ($period === 'this_month') {
            $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        }
        if ($period === 'custom') {
            if (!empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }
        }
    }

    private function paymentRow(PaymentRequest $payment): array
    {
        return [
            'id' => $payment->id,
            'payment_no' => $payment->payment_no ?: $payment->request_no,
            'category' => $payment->paymentCategory?->name ?: (is_string($payment->payment_category) ? $payment->payment_category : null),
            'type' => $payment->paymentType?->name,
            'approved_amount' => (float) $payment->amount,
            'paid_amount' => (float) ($payment->paid_amount ?? 0),
            'status' => $payment->status,
            'allocated_budget_code' => $payment->budget?->budget_code ?: $payment->budget_code,
            'approved_date' => optional($payment->completed_at ?: $payment->official_date ?: $payment->submitted_at ?: $payment->created_at)->toDateString(),
        ];
    }

    private function procurementRow(ProcurementRequest $procurement): array
    {
        return [
            'id' => $procurement->id,
            'procurement_no' => $procurement->request_no,
            'customer_name' => $procurement->title,
            'category' => $procurement->category?->name,
            'type' => $procurement->procurementType?->name,
            'budget_code' => $procurement->budget_code,
            'amount' => $this->procurementAmount($procurement),
            'status' => $procurement->status,
            'approved_date' => optional($procurement->completed_at ?: $procurement->official_date ?: $procurement->submitted_at ?: $procurement->created_at)->toDateString(),
        ];
    }

    private function budgetRow(Budget $budget): array
    {
        return [
            'id' => $budget->id,
            'account_code' => $budget->budget_code,
            'account_description' => $budget->account_name,
            'bi_code' => $budget->bi_code,
            'adjusted_budget' => (float) $budget->allocated_amount,
            'balance_not_committed' => (float) $budget->remaining_amount,
            'debit' => (float) $budget->used_amount,
            'credit' => 0,
            'status' => $budget->status,
        ];
    }

    private function procurementAmount(ProcurementRequest $procurement): float
    {
        return (float) $procurement->items->sum(fn ($item) => (float) ($item->estimated_total_cost ?? 0));
    }

    private function charts(Collection $payments, Collection $procurements, Collection $budgets): array
    {
        return [
            'payment_by_category' => $this->groupSum($payments, fn ($p) => $p->paymentCategory?->name ?: 'Uncategorized', fn ($p) => (float) $p->amount),
            'payment_by_type' => $this->groupSum($payments, fn ($p) => $p->paymentType?->name ?: 'Unspecified', fn ($p) => (float) $p->amount),
            'payment_status_summary' => $this->groupCount($payments, fn ($p) => Str::headline($p->status)),
            'monthly_approved_amount' => $this->groupSum($payments, fn ($p) => Carbon::parse($p->completed_at ?: $p->created_at)->format('M Y'), fn ($p) => (float) $p->amount),
            'paid_vs_approved_amount' => [
                ['name' => 'Approved', 'value' => round((float) $payments->sum(fn ($p) => (float) $p->amount), 2)],
                ['name' => 'Paid', 'value' => round((float) $payments->sum(fn ($p) => (float) ($p->paid_amount ?? 0)), 2)],
            ],
            'procurement_by_category' => $this->groupSum($procurements, fn ($p) => $p->category?->name ?: 'Uncategorized', fn ($p) => $this->procurementAmount($p)),
            'procurement_by_type' => $this->groupSum($procurements, fn ($p) => $p->procurementType?->name ?: 'Unspecified', fn ($p) => $this->procurementAmount($p)),
            'procurement_status_summary' => $this->groupCount($procurements, fn ($p) => Str::headline($p->status)),
            'monthly_procurement_amount' => $this->groupSum($procurements, fn ($p) => Carbon::parse($p->completed_at ?: $p->created_at)->format('M Y'), fn ($p) => $this->procurementAmount($p)),
            'budget_code_utilization' => $this->groupSum($procurements, fn ($p) => $p->budget_code ?: 'No Budget Code', fn ($p) => $this->procurementAmount($p)),
            'budget_by_bi_code' => $this->groupSum($budgets, fn ($b) => $b->bi_code ?: 'No BI Code', fn ($b) => (float) $b->allocated_amount),
            'budget_by_account_code' => $this->groupSum($budgets, fn ($b) => $b->budget_code ?: 'No Account Code', fn ($b) => (float) $b->allocated_amount),
            'adjusted_budget_vs_balance' => [
                ['name' => 'Adjusted Budget', 'value' => round((float) $budgets->sum(fn ($b) => (float) $b->allocated_amount), 2)],
                ['name' => 'Balance Not Committed', 'value' => round((float) $budgets->sum(fn ($b) => (float) $b->remaining_amount), 2)],
            ],
            'debit_vs_credit' => [
                ['name' => 'Debit', 'value' => round((float) $budgets->sum(fn ($b) => (float) $b->used_amount), 2)],
                ['name' => 'Credit', 'value' => 0],
            ],
            'top_used_budget_codes' => $this->groupSum($budgets, fn ($b) => $b->budget_code ?: 'No Account Code', fn ($b) => (float) $b->used_amount),
        ];
    }

    private function groupSum(Collection $rows, callable $label, callable $amount): array
    {
        return $rows->groupBy($label)->map(fn (Collection $items, string $name) => [
            'name' => $name,
            'value' => round((float) $items->sum($amount), 2),
        ])->sortByDesc('value')->values()->all();
    }

    private function groupCount(Collection $rows, callable $label): array
    {
        return $rows->groupBy($label)->map(fn (Collection $items, string $name) => [
            'name' => $name,
            'value' => $items->count(),
        ])->sortByDesc('value')->values()->all();
    }
}
