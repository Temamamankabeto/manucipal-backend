<?php

namespace App\Http\Controllers\Api\Budget;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\PaymentType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BudgetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('budget.view') || $request->user()->can('payment.view'), 403);

        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));

        $budgets = Budget::query()
            ->withCount('transactions')
            ->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('fiscal_year') && $request->fiscal_year !== 'all', fn ($q) => $q->where('fiscal_year', $request->fiscal_year))
            ->when($request->filled('bi_code') && $request->bi_code !== 'all', fn ($q) => $q->where('bi_code', $request->bi_code))
            ->when($request->filled('payment_type_id'), function ($q) use ($request) {
                $type = PaymentType::query()->find($request->integer('payment_type_id'));

                if ($type) {
                    $q->whereRaw('LOWER(TRIM(account_name)) = ?', [strtolower(trim($type->name))]);
                }
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(fn ($qq) => $qq
                    ->where('budget_code', 'like', "%{$search}%")
                    ->orWhere('bi_code', 'like', "%{$search}%")
                    ->orWhere('account_name', 'like', "%{$search}%"));
            })
            ->orderBy('bi_code')->orderBy('budget_code')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Budgets retrieved successfully',
            'data' => $budgets->items(),
            'meta' => [
                'current_page' => $budgets->currentPage(),
                'per_page' => $budgets->perPage(),
                'total' => $budgets->total(),
                'last_page' => $budgets->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeBudgetManagement($request);

        $data = $request->validate([
            'bi_code' => ['nullable', 'string', 'max:100'],
            'budget_code' => ['required', 'string', 'max:20'],
            'account_name' => ['required', 'string', 'max:255'],
            'fiscal_year' => ['nullable', 'string', 'max:20'],
            'source_of_finance' => ['nullable', 'string', 'max:100'],
            'bank_account_code' => ['nullable', 'string', 'max:150'],
            'budget_type' => ['nullable', 'string', 'max:100'],
            'allocated_amount' => ['required', 'numeric', 'min:0'],
            'used_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string'],
        ]);

        $used = (float) ($data['used_amount'] ?? 0);
        $allocated = (float) $data['allocated_amount'];
        abort_if($used > $allocated, 422, 'Used amount cannot exceed allocated amount.');

        $budget = Budget::create([
            ...$data,
            'used_amount' => $used,
            'remaining_amount' => $allocated - $used,
            'status' => $data['status'] ?? Budget::STATUS_ACTIVE,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Budget created successfully',
            'data' => $budget,
            'meta' => null,
        ], 201);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        abort_unless($request->user()->can('budget.view') || $request->user()->can('payment.view'), 403);

        $budget = Budget::with(['transactions.payment:id,payment_no,title,amount,status'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Budget retrieved successfully',
            'data' => $budget,
            'meta' => null,
        ]);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $this->authorizeBudgetManagement($request);

        $budget = Budget::findOrFail($id);

        $data = $request->validate([
            'bi_code' => ['nullable', 'string', 'max:100'],
            'budget_code' => ['required', 'string', 'max:20'],
            'account_name' => ['required', 'string', 'max:255'],
            'fiscal_year' => ['nullable', 'string', 'max:20'],
            'source_of_finance' => ['nullable', 'string', 'max:100'],
            'bank_account_code' => ['nullable', 'string', 'max:150'],
            'budget_type' => ['nullable', 'string', 'max:100'],
            'allocated_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string'],
        ]);

        $used = (float) $budget->used_amount;
        $allocated = (float) $data['allocated_amount'];
        abort_if($allocated < $used, 422, 'Allocated amount cannot be less than already used amount.');

        $budget->update([
            ...$data,
            'used_amount' => $used,
            'remaining_amount' => $allocated - $used,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Budget updated successfully',
            'data' => $budget->fresh(),
            'meta' => null,
        ]);
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $this->authorizeBudgetManagement($request);

        $budget = Budget::withCount('transactions')->findOrFail($id);
        abort_if($budget->transactions_count > 0 || (float) $budget->used_amount > 0, 422, 'Budget has transactions and cannot be deleted. Deactivate it instead.');
        $budget->delete();

        return response()->json([
            'success' => true,
            'message' => 'Budget deleted successfully',
            'data' => null,
            'meta' => null,
        ]);
    }


    protected function authorizeBudgetManagement(Request $request): void
    {
        $user = $request->user()?->loadMissing('department');

        abort_unless($user instanceof User && $this->canManageBudgetMasterData($user), 403);
    }

    protected function canManageBudgetMasterData(User $user): bool
    {
        if ($user->hasRole(User::ROLE_SUPER_ADMIN)) {
            return true;
        }

        $departmentName = strtolower((string) ($user->department?->name ?? ''));
        $isBudgetDepartment = str_contains($departmentName, 'budget')
            || str_contains($departmentName, 'baajata')
            || str_contains($departmentName, 'baajataa');

        return $user->can('budget.create')
            && $user->can('budget.update')
            && $user->can('budget.delete')
            && $user->hasRole(User::ROLE_TEAM_LEADER)
            && $isBudgetDepartment;
    }

    public function fiscalYears(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('budget.view') || $request->user()->can('payment.view'), 403);

        $years = Budget::query()
            ->whereNotNull('fiscal_year')
            ->select('fiscal_year')
            ->distinct()
            ->orderByDesc('fiscal_year')
            ->pluck('fiscal_year')
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Fiscal years retrieved successfully',
            'data' => $years,
            'meta' => null,
        ]);
    }

    public function biCodes(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('budget.view') || $request->user()->can('payment.view'), 403);

        $query = Budget::query()
            ->where('status', Budget::STATUS_ACTIVE)
            ->whereNotNull('bi_code')
            ->when($request->filled('fiscal_year'), fn ($q) => $q->where('fiscal_year', $request->fiscal_year));

        // BI Code / Office Code dropdown must be driven by fiscal year first.
        // Do not hard-filter BI codes by payment type here, because the expert
        // must first choose the office/BI Code, then the Account Code is loaded
        // under that BI Code. Filtering too early can hide valid BI Codes when
        // payment type names and account descriptions differ slightly.
        $codes = $query->select('bi_code')
            ->distinct()
            ->orderBy('bi_code')
            ->pluck('bi_code')
            ->filter(fn ($code) => filled($code))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'BI codes retrieved successfully',
            'data' => $codes,
            'meta' => null,
        ]);
    }

    public function accountCodes(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('budget.view') || $request->user()->can('payment.view'), 403);

        $query = Budget::query()
            ->where('status', Budget::STATUS_ACTIVE)
            ->when($request->filled('fiscal_year'), fn ($q) => $q->where('fiscal_year', $request->fiscal_year))
            ->when($request->filled('bi_code'), fn ($q) => $q->where('bi_code', $request->bi_code));

        $filteredQuery = clone $query;
        $this->applyPaymentTypeFilter($filteredQuery, $request);

        $columns = [
            'id',
            'bi_code',
            'budget_code',
            'account_name',
            'fiscal_year',
            'allocated_amount',
            'used_amount',
            'remaining_amount',
            'status',
        ];

        $budgets = $filteredQuery->orderBy('budget_code')->get($columns);

        // Safe fallback: if a selected payment type does not exactly match the
        // budget account description, still show the account codes under the
        // chosen BI Code so the expert can select the correct budget manually.
        if ($budgets->isEmpty() && $request->filled('payment_type_id')) {
            $budgets = $query->orderBy('budget_code')->get($columns);
        }

        return response()->json([
            'success' => true,
            'message' => 'Account codes retrieved successfully',
            'data' => $budgets,
            'meta' => null,
        ]);
    }

    public function paymentTypeBalance(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('budget.view') || $request->user()->can('payment.view'), 403);

        $query = Budget::query()
            ->where('status', Budget::STATUS_ACTIVE)
            ->when($request->filled('fiscal_year'), fn ($q) => $q->where('fiscal_year', $request->fiscal_year));

        $this->applyPaymentTypeFilter($query, $request);

        $budgets = $query->orderBy('bi_code')
            ->orderBy('budget_code')
            ->get([
                'id',
                'bi_code',
                'budget_code',
                'account_name',
                'fiscal_year',
                'allocated_amount',
                'used_amount',
                'remaining_amount',
                'status',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment type budget balances retrieved successfully',
            'data' => $budgets,
            'meta' => [
                'total_balance_not_committed' => $budgets->sum(fn (Budget $budget) => (float) $budget->remaining_amount),
                'total_adjusted_budget' => $budgets->sum(fn (Budget $budget) => (float) $budget->allocated_amount),
                'total_debit' => $budgets->sum(fn (Budget $budget) => (float) $budget->used_amount),
            ],
        ]);
    }

    protected function applyPaymentTypeFilter($query, Request $request): void
    {
        if (! $request->filled('payment_type_id')) {
            return;
        }

        $type = PaymentType::query()->find($request->integer('payment_type_id'));

        if (! $type) {
            return;
        }

        $query->whereRaw('LOWER(TRIM(account_name)) = ?', [strtolower(trim($type->name))]);
    }


    public function aggregate(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('budget.view') || $request->user()->can('reports.view'), 403);

        $rows = Budget::query()
            ->when($request->filled('fiscal_year'), fn ($q) => $q->where('fiscal_year', $request->fiscal_year))
            ->selectRaw('budget_code, MAX(account_name) as account_name, COALESCE(SUM(allocated_amount),0) as allocated_amount, COALESCE(SUM(used_amount),0) as used_amount')
            ->groupBy('budget_code')
            ->orderBy('budget_code')
            ->get()
            ->map(function ($row) {
                $adjusted = (float) $row->allocated_amount;
                $debit = (float) $row->used_amount;

                return [
                    'budget_code' => $row->budget_code,
                    'account_name' => $row->account_name,
                    'allocated_amount' => number_format($adjusted, 2, '.', ''),
                    'used_amount' => number_format($debit, 2, '.', ''),
                    'remaining_amount' => number_format($adjusted - $debit, 2, '.', ''),
                    'status' => 'active',
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Aggregated recurrent budget report retrieved successfully',
            'data' => $rows,
            'meta' => [
                'fiscal_year' => $request->fiscal_year,
                'bi_code' => 'Aggregate - All BI Codes',
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('budget.view') || $request->user()->can('reports.view'), 403);

        $summary = Budget::query()->selectRaw('
            COALESCE(SUM(allocated_amount),0) as allocated,
            COALESCE(SUM(used_amount),0) as used,
            COALESCE(SUM(remaining_amount),0) as remaining,
            COUNT(*) as total_codes
        ')->first();

        return response()->json([
            'success' => true,
            'message' => 'Budget summary retrieved successfully',
            'data' => $summary,
            'meta' => null,
        ]);
    }
}
