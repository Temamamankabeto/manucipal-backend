<?php

namespace App\Http\Controllers\Api\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Office\StoreOfficeRequest;
use App\Http\Requests\Office\UpdateOfficeRequest;
use App\Models\Office;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfficeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Office::query()->withCount(['users', 'departments']);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->string('search') . '%');
        }

        if ($request->boolean('all')) {
            return $this->success('Offices retrieved successfully', $query->orderBy('name')->get());
        }

        $offices = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return $this->paginated('Offices retrieved successfully', $offices);
    }

    public function store(StoreOfficeRequest $request): JsonResponse
    {
        $office = DB::transaction(function () use ($request) {
            $name = trim($request->validated('name'));

            return Office::create([
                'name' => $name,
                'code' => $this->makeUniqueCode($name),
                'type' => Office::TYPE_CITY,
                'parent_id' => null,
                'is_active' => true,
            ]);
        });

        return $this->success('Office created successfully', $office, 201);
    }

    public function show(Request $request, Office $office): JsonResponse
    {
        return $this->success('Office retrieved successfully', $office->loadCount(['users', 'departments']));
    }

    public function update(UpdateOfficeRequest $request, Office $office): JsonResponse
    {
        DB::transaction(function () use ($request, $office) {
            $office->update(['name' => trim($request->validated('name'))]);
        });

        return $this->success('Office updated successfully', $office->fresh()->loadCount(['users', 'departments']));
    }

    public function destroy(Request $request, Office $office): JsonResponse
    {
        if ($office->users()->exists() || $office->departments()->exists() || $office->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Office cannot be deleted because it is already used by users, departments, or child offices.',
                'data' => null,
                'meta' => null,
            ], 422);
        }

        DB::transaction(fn () => $office->delete());

        return $this->success('Office deleted successfully', null);
    }

    private function canView(Request $request): bool
    {
        return $request->user() !== null;
    }

    private function makeUniqueCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '-')) ?: 'OFFICE';
        $code = $base;
        $counter = 1;

        while (Office::where('code', $code)->exists()) {
            $code = $base . '-' . ++$counter;
        }

        return $code;
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
