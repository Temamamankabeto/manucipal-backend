<?php

namespace App\Http\Controllers\Api\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Department::query()->with('office:id,name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->string('search') . '%');
        }

        if ($request->filled('office_id') && $request->input('office_id') !== 'all') {
            $query->where('office_id', $request->integer('office_id'));
        }

        if ($request->boolean('all')) {
            return $this->success('Departments retrieved successfully', $query->orderBy('name')->get());
        }

        $departments = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return $this->paginated('Departments retrieved successfully', $departments);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = DB::transaction(fn () => Department::create($request->validated()))->load('office:id,name');

        return $this->success('Department created successfully', $department, 201);
    }


    public function byOffice(Request $request, int|string $office): JsonResponse
    {
        $departments = Department::query()
            ->with('office:id,name')
            ->where('office_id', $office)
            ->orderBy('name')
            ->get();

        return $this->success('Departments retrieved successfully', $departments);
    }

    public function show(Request $request, Department $department): JsonResponse
    {
        return $this->success('Department retrieved successfully', $department->load('office:id,name'));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        DB::transaction(fn () => $department->update($request->validated()));

        return $this->success('Department updated successfully', $department->fresh()->load('office:id,name'));
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        DB::transaction(fn () => $department->delete());

        return $this->success('Department deleted successfully', null);
    }

    private function canView(Request $request): bool
    {
        return $request->user() !== null;
    }

    private function success(string $message, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data, 'meta' => null], $status);
    }

    private function paginated(string $message, $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
