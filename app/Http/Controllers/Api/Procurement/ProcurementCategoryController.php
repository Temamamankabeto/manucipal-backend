<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcurementCategory\StoreProcurementCategoryRequest;
use App\Http\Requests\ProcurementCategory\UpdateProcurementCategoryRequest;
use App\Models\ProcurementCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('procurement.view') || $request->user()->can('procurement.read') || $request->user()->can('procurement.create'), 403);

        $query = ProcurementCategory::query()->withCount('types');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->string('search') . '%');
        }

        $categories = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Procurement categories retrieved successfully',
            'data' => $categories->items(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'last_page' => $categories->lastPage(),
            ],
        ]);
    }

    public function store(StoreProcurementCategoryRequest $request): JsonResponse
    {
        $category = DB::transaction(fn () => ProcurementCategory::create($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Procurement category created successfully',
            'data' => $category,
            'meta' => null,
        ], 201);
    }

    public function show(Request $request, ProcurementCategory $procurement_category): JsonResponse
    {
        abort_unless($request->user()->can('procurement.view') || $request->user()->can('procurement.read') || $request->user()->can('procurement.create'), 403);

        return response()->json([
            'success' => true,
            'message' => 'Procurement category retrieved successfully',
            'data' => $procurement_category->load('types'),
            'meta' => null,
        ]);
    }

    public function update(UpdateProcurementCategoryRequest $request, ProcurementCategory $procurement_category): JsonResponse
    {
        DB::transaction(fn () => $procurement_category->update($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Procurement category updated successfully',
            'data' => $procurement_category->fresh()->loadCount('types'),
            'meta' => null,
        ]);
    }

    public function destroy(Request $request, ProcurementCategory $procurement_category): JsonResponse
    {
        abort_unless($request->user()->can('procurement.delete') || $request->user()->can('procurement.approve'), 403);
        abort_if($procurement_category->types()->exists(), 422, 'Category has procurement types and cannot be deleted.');

        DB::transaction(fn () => $procurement_category->delete());

        return response()->json([
            'success' => true,
            'message' => 'Procurement category deleted successfully',
            'data' => null,
            'meta' => null,
        ]);
    }
}
