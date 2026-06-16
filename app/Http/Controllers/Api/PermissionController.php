<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\AuditLogService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Permission::class);

        $search = trim((string) $request->query('search', ''));
        $perPage = max(1, min((int) $request->query('per_page', 10), 100));
        $all = $request->boolean('all');

        $query = Permission::query()
            ->where('guard_name', 'sanctum')
            ->orderBy('name');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $columns = ['id', 'name', 'guard_name', 'created_at', 'updated_at'];

        if ($all) {
            $permissions = $query->get($columns);

            return response()->json([
                'success' => true,
                'message' => 'Permissions retrieved successfully',
                'data' => $permissions,
                'meta' => [
                    'current_page' => 1,
                    'per_page' => $permissions->count(),
                    'total' => $permissions->count(),
                    'last_page' => 1,
                ],
            ]);
        }

        $permissions = $query->paginate($perPage, $columns);

        return response()->json([
            'success' => true,
            'message' => 'Permissions retrieved successfully',
            'data' => $permissions->items(),
            'meta' => [
                'current_page' => $permissions->currentPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
                'last_page' => $permissions->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Permission::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:permissions,name'],
        ]);

        $permission = Permission::create([
            'name' => $data['name'],
            'guard_name' => 'sanctum',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        AuditLogService::log('permission_management', 'CREATE', $permission, [
            'after' => ['name' => $permission->name],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'data' => $permission,
            'meta' => null,
        ], 201);
    }

    public function update(Request $request, int|string $id)
    {
        $permission = Permission::where('guard_name', 'sanctum')->findOrFail($id);
        $this->authorize('update', $permission);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('permissions', 'name')->ignore($permission->id)],
        ]);

        $before = ['name' => $permission->name];
        $permission->update(['name' => $data['name']]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        AuditLogService::log('permission_management', 'UPDATE', $permission, [
            'before' => $before,
            'after' => ['name' => $permission->name],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully',
            'data' => $permission,
            'meta' => null,
        ]);
    }

    public function destroy(int|string $id)
    {
        $permission = Permission::where('guard_name', 'sanctum')->findOrFail($id);
        $this->authorize('delete', $permission);

        AuditLogService::log('permission_management', 'DELETE', $permission, [
            'before' => ['name' => $permission->name],
        ]);

        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully',
            'data' => null,
            'meta' => null,
        ]);
    }
}
