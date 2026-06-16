<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcurementType\StoreProcurementTypeRequest;
use App\Http\Requests\ProcurementType\UpdateProcurementTypeRequest;
use App\Models\ProcurementType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('procurement.view') || $request->user()->can('procurement.read') || $request->user()->can('procurement.create'), 403);

        $query = ProcurementType::query()->with('category:id,name');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->string('search') . '%');
        }

        $types = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Procurement types retrieved successfully',
            'data' => $types->items(),
            'meta' => [
                'current_page' => $types->currentPage(),
                'per_page' => $types->perPage(),
                'total' => $types->total(),
                'last_page' => $types->lastPage(),
            ],
        ]);
    }

    public function store(StoreProcurementTypeRequest $request): JsonResponse
    {
        $type = DB::transaction(fn () => ProcurementType::create($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Procurement type created successfully',
            'data' => $type->load('category:id,name'),
            'meta' => null,
        ], 201);
    }

    public function show(Request $request, ProcurementType $procurement_type): JsonResponse
    {
        abort_unless($request->user()->can('procurement.view') || $request->user()->can('procurement.read') || $request->user()->can('procurement.create'), 403);

        return response()->json([
            'success' => true,
            'message' => 'Procurement type retrieved successfully',
            'data' => $procurement_type->load('category:id,name'),
            'meta' => null,
        ]);
    }

    public function update(UpdateProcurementTypeRequest $request, ProcurementType $procurement_type): JsonResponse
    {
        DB::transaction(fn () => $procurement_type->update($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Procurement type updated successfully',
            'data' => $procurement_type->fresh()->load('category:id,name'),
            'meta' => null,
        ]);
    }

    public function destroy(Request $request, ProcurementType $procurement_type): JsonResponse
    {
        abort_unless($request->user()->can('procurement.delete') || $request->user()->can('procurement.approve'), 403);

        DB::transaction(fn () => $procurement_type->delete());

        return response()->json([
            'success' => true,
            'message' => 'Procurement type deleted successfully',
            'data' => null,
            'meta' => null,
        ]);
    }
}
