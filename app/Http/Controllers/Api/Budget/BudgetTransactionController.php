<?php

namespace App\Http\Controllers\Api\Budget;

use App\Http\Controllers\Controller;
use App\Models\BudgetTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('budget.view') || $request->user()->can('reports.view'), 403);

        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));

        $transactions = BudgetTransaction::query()
            ->with(['budget:id,bi_code,budget_code,account_name,fiscal_year', 'payment:id,payment_no,title,status,amount', 'creator:id,name'])
            ->when($request->filled('budget_id'), fn ($q) => $q->where('budget_id', $request->integer('budget_id')))
            ->when($request->filled('fiscal_year') && $request->fiscal_year !== 'all', fn ($q) => $q->whereHas('budget', fn ($b) => $b->where('fiscal_year', $request->fiscal_year)))
            ->when($request->filled('bi_code') && $request->bi_code !== 'all', fn ($q) => $q->whereHas('budget', fn ($b) => $b->where('bi_code', $request->bi_code)))
            ->when($request->filled('type') && $request->type !== 'all', fn ($q) => $q->where('type', $request->type))
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Budget transactions retrieved successfully',
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }
}
