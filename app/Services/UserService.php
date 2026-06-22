<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Office;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Services\AuditLogService;
use Spatie\Permission\Models\Role;

class UserService
{
    public function paginateUsers(array $filters, ?User $actor = null): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 10), 100));
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $role = $filters['role'] ?? null;
        $adminLevel = $filters['admin_level'] ?? null;

        $query = User::query()
            ->with(['roles:id,name,guard_name', 'office:id,name,type,code,parent_id', 'department:id,office_id,name', 'subCity:id,name,type,code,parent_id', 'woreda:id,name,type,code,parent_id', 'zone:id,name,type,code,parent_id']);

        if ($actor) {
            $query->visibleTo($actor);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'disabled') {
            $query->where('is_active', false);
        }

        if ($role) {
            $query->role($role, 'sanctum');
        }

        if ($adminLevel) {
            $query->where('admin_level', $adminLevel);
        }

        foreach (['office_id', 'department_id', 'sub_city_id', 'woreda_id', 'zone_id'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        return $query->latest()->paginate($perPage);
    }

    public function transformPaginatedUsers(LengthAwarePaginator $users): array
    {
        return [
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => collect($users->items())->map(fn (User $user) => $this->transformUser($user))->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ];
    }

    public function transformUser(User $user): array
    {
        $user->loadMissing(['roles:id,name,guard_name', 'office:id,name,type,code,parent_id', 'department:id,office_id,name', 'subCity:id,name,type,code,parent_id', 'woreda:id,name,type,code,parent_id', 'zone:id,name,type,code,parent_id']);

        return [
            'id' => $user->id,
            'created_by' => $user->created_by,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'status' => $user->is_active ? 'active' : 'disabled',
            'role' => $user->roles->pluck('name')->first(),
            'roles' => $user->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values(),
            'admin_level' => $user->admin_level,
            'professional_level' => $user->professional_level,
            'office_id' => $user->office_id,
            'department_id' => $user->department_id,
            'sub_city_id' => $user->sub_city_id,
            'woreda_id' => $user->woreda_id,
            'zone_id' => $user->zone_id,
            'office' => $this->officePayload($user->office),
            'department' => $user->department ? ['id' => $user->department->id, 'office_id' => $user->department->office_id, 'name' => $user->department->name] : null,
            'sub_city' => $this->officePayload($user->subCity),
            'woreda' => $this->officePayload($user->woreda),
            'zone' => $this->officePayload($user->zone),
            'profile_image_url' => $user->profile_image_url,
            'signature_url' => $user->signature_url,
            'stamp_url' => $user->stamp_url,
            'titer_url' => $user->titer_url,
            'last_login_at' => $user->last_login_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    public function getUser(int|string $id, ?User $actor = null): User
    {
        $query = User::query()
            ->with(['roles:id,name,guard_name', 'office:id,name,type,code,parent_id', 'department:id,office_id,name', 'subCity:id,name,type,code,parent_id', 'woreda:id,name,type,code,parent_id', 'zone:id,name,type,code,parent_id']);

        if ($actor) {
            $query->visibleTo($actor);
        }

        return $query->findOrFail($id);
    }

    public function getRolesLite(?User $actor = null)
    {
        $allowedRoles = User::userManagementRoleNames();

        if ($actor && ! $actor->isSuperAdmin()) {
            $allowedRoles = array_values(array_filter(
                $allowedRoles,
                fn (string $role) => $role !== User::ROLE_SUPER_ADMIN
            ));
        }

        return Role::query()
            ->where('guard_name', 'sanctum')
            ->whereIn('name', $allowedRoles)
            ->select('id', 'name')
            ->orderByRaw("CASE name
                WHEN ? THEN 1
                WHEN ? THEN 2
                WHEN ? THEN 3
                WHEN ? THEN 4
                WHEN ? THEN 5
                WHEN ? THEN 6
                WHEN ? THEN 7
                WHEN ? THEN 8
                WHEN ? THEN 9
                ELSE 99 END", User::userManagementRoleNames())
            ->get();
    }

    public function getOfficesLite(?User $actor = null, ?string $type = null, int|string|null $parentId = null)
    {
        $query = Office::query()
            ->active()
            ->select('id', 'name', 'code', 'type', 'parent_id')
            ->orderBy('type')
            ->orderBy('name');

        if ($type) {
            $query->where('type', $type);
        }

        if ($parentId) {
            $query->where('parent_id', $parentId);
        }

        if ($actor && ! $actor->isSuperAdmin() && $actor->isAdmin() && $actor->admin_level !== User::LEVEL_CITY) {
            $query->where(function ($q) use ($actor) {
                if ($actor->office_id) {
                    $q->whereKey($actor->office_id)->orWhere('parent_id', $actor->office_id);
                }

                if ($actor->sub_city_id) {
                    $q->orWhere('id', $actor->sub_city_id)->orWhere('parent_id', $actor->sub_city_id);
                }

                if ($actor->woreda_id) {
                    $q->orWhere('id', $actor->woreda_id)->orWhere('parent_id', $actor->woreda_id);
                }

                if ($actor->zone_id) {
                    $q->orWhere('id', $actor->zone_id);
                }
            });
        }

        return $query->get();
    }

    public function getDepartmentsLite(?User $actor = null, int|string|null $officeId = null)
    {
        $query = Department::query()
            ->select('id', 'office_id', 'name')
            ->where('is_active', true)
            ->orderBy('name');

        if ($officeId) {
            $query->where('office_id', (int) $officeId);
        }

        if ($actor && ! $actor->isSuperAdmin() && $actor->office_id) {
            $query->where('office_id', (int) $actor->office_id);
        }

        return $query->get();
    }

    public function createUser(array $data, User $actor, ?UploadedFile $signatureFile = null, ?UploadedFile $stampFile = null, ?UploadedFile $titerFile = null): User
    {
        $role = $this->findRole($data['role']);
        $scope = $this->normalizeOfficeScope($data);
        $adminLevel = $this->normalizeAdminLevel($role->name, $data['admin_level'] ?? null, $scope);

        $this->ensureRoleAssignable($actor, $role->name, $adminLevel);
        $this->ensureScopeAssignable($actor, $adminLevel, $scope);

        $user = User::create([
            'created_by' => $actor->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address' => $data['address'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'admin_level' => $adminLevel,
            'professional_level' => $this->normalizeProfessionalLevel($role->name, $data['professional_level'] ?? null),
            ...$scope,
            'department_id' => $this->normalizeDepartment($data['department_id'] ?? null, $scope['office_id'] ?? null),
        ]);

        $this->storeSignatureAndStamp($user, $signatureFile, $stampFile, $titerFile);

        $user->syncRoles([$role->name]);

        AuditLogService::log('user_management', 'CREATE', $user, [
            'after' => ['role' => $role->name, 'admin_level' => $adminLevel, 'professional_level' => $user->professional_level, ...$scope],
        ]);

        return $user->load(['roles', 'office', 'department', 'subCity', 'woreda', 'zone']);
    }

    public function updateUser(User $user, array $data, User $actor, ?UploadedFile $signatureFile = null, ?UploadedFile $stampFile = null, ?UploadedFile $titerFile = null): User
    {
        $role = $this->findRole($data['role']);
        $scope = $this->normalizeOfficeScope($data);
        $adminLevel = $this->normalizeAdminLevel($role->name, $data['admin_level'] ?? null, $scope);

        $this->ensureRoleAssignable($actor, $role->name, $adminLevel);
        $this->ensureScopeAssignable($actor, $adminLevel, $scope);

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'role' => $user->roles->pluck('name')->first(),
            'admin_level' => $user->admin_level,
            'professional_level' => $user->professional_level,
            'office_id' => $user->office_id,
            'department_id' => $user->department_id,
            'sub_city_id' => $user->sub_city_id,
            'woreda_id' => $user->woreda_id,
            'zone_id' => $user->zone_id,
        ];

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address' => $data['address'] ?? $user->address,
            'admin_level' => $adminLevel,
            'professional_level' => $this->normalizeProfessionalLevel($role->name, $data['professional_level'] ?? null),
            ...$scope,
            'department_id' => $this->normalizeDepartment($data['department_id'] ?? null, $scope['office_id'] ?? null),
        ]);

        $this->storeSignatureAndStamp($user, $signatureFile, $stampFile, $titerFile);

        $user->syncRoles([$role->name]);

        AuditLogService::log('user_management', 'UPDATE', $user, [
            'before' => $before,
            'after' => ['role' => $role->name, 'admin_level' => $adminLevel, 'professional_level' => $user->professional_level, ...$scope],
        ]);

        return $user->load(['roles', 'office', 'department', 'subCity', 'woreda', 'zone']);
    }

    public function assignRole(User $user, string $roleName, User $actor): User
    {
        $role = $this->findRole($roleName);
        $adminLevel = $roleName === User::ROLE_SUPER_ADMIN ? null : ($user->admin_level ?: User::LEVEL_ZONE);

        $this->ensureRoleAssignable($actor, $role->name, $adminLevel);

        $beforeRole = $user->roles->pluck('name')->first();

        $user->forceFill(['admin_level' => $adminLevel])->save();
        $user->syncRoles([$role->name]);

        AuditLogService::log('user_management', 'ASSIGN_ROLE', $user, [
            'before' => ['role' => $beforeRole],
            'after' => ['role' => $role->name, 'admin_level' => $adminLevel],
        ]);

        return $user->load(['roles', 'office', 'department', 'subCity', 'woreda', 'zone']);
    }

    public function toggleUser(User $user): User
    {
        $before = $user->is_active;
        $user->forceFill(['is_active' => ! $user->is_active])->save();

        AuditLogService::log('user_management', 'TOGGLE_STATUS', $user, [
            'before' => ['is_active' => $before],
            'after' => ['is_active' => $user->is_active],
        ]);

        return $user->load(['roles', 'office', 'department', 'subCity', 'woreda', 'zone']);
    }

    public function resetPassword(User $user, string $newPassword): User
    {
        $user->forceFill(['password' => Hash::make($newPassword)])->save();

        AuditLogService::log('user_management', 'RESET_PASSWORD', $user);

        return $user;
    }


    public function updateProfile(User $user, array $data, ?UploadedFile $profileFile = null): User
    {
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'];

        if (! empty($data['new_password'])) {
            if (empty($data['old_password'])) {
                throw ValidationException::withMessages(['old_password' => ['Old password is required when setting a new password.']]);
            }

            if (! Hash::check($data['old_password'], $user->password)) {
                throw ValidationException::withMessages(['old_password' => ['The provided old password is incorrect.']]);
            }

            $user->password = Hash::make($data['new_password']);
        }

        if ($profileFile) {
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $user->profile_image = $profileFile->store('users/profile-images', 'public');
        }

        $user->save();

        return $user->load(['roles', 'office', 'department', 'subCity', 'woreda', 'zone']);
    }


    protected function storeSignatureAndStamp(User $user, ?UploadedFile $signatureFile = null, ?UploadedFile $stampFile = null, ?UploadedFile $titerFile = null): void
    {
        if ($signatureFile) {
            if ($user->signature_path && Storage::disk('public')->exists($user->signature_path)) {
                Storage::disk('public')->delete($user->signature_path);
            }

            $user->signature_path = $signatureFile->store('users/signatures', 'public');
        }

        if ($stampFile) {
            if ($user->stamp_path && Storage::disk('public')->exists($user->stamp_path)) {
                Storage::disk('public')->delete($user->stamp_path);
            }

            $user->stamp_path = $stampFile->store('users/stamps', 'public');
        }

        if ($titerFile) {
            if ($user->titer_path && Storage::disk('public')->exists($user->titer_path)) {
                Storage::disk('public')->delete($user->titer_path);
            }

            $user->titer_path = $titerFile->store('users/titers', 'public');
        }

        if ($signatureFile || $stampFile || $titerFile) {
            $user->save();
        }
    }

    public function deleteUser(User $user, ?int $authId = null): void
    {
        if ($authId && $authId === $user->id) {
            throw ValidationException::withMessages(['user' => ['You cannot delete your own account.']]);
        }

        AuditLogService::log('user_management', 'DELETE', $user, [
            'before' => $this->transformUser($user),
        ]);

        $user->syncRoles([]);

        foreach ([$user->profile_image, $user->signature_path, $user->stamp_path, $user->titer_path] as $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $user->delete();
    }

 protected function normalizeOfficeScope(array $data): array
 {
 $scope = [
 'office_id' => $data['office_id'] ?? null,
 'sub_city_id' => $data['sub_city_id'] ?? null,
 'woreda_id' => $data['woreda_id'] ?? null,
 'zone_id' => $data['zone_id'] ?? null,
 ];

 $selectedOfficeId =
 $scope['zone_id']
 ?? $scope['woreda_id']
 ?? $scope['sub_city_id']
 ?? $scope['office_id']
 ?? null;

 if ($selectedOfficeId) {
 $office = Office::query()
 ->with('parent.parent.parent')
 ->findOrFail($selectedOfficeId);

 $current = $office;

 while ($current) {
 if ($current->type === Office::TYPE_SUBCITY) {
 $scope['sub_city_id'] = $current->id;
 }

 if ($current->type === Office::TYPE_WOREDA) {
 $scope['woreda_id'] = $current->id;
 }

 if ($current->type === Office::TYPE_ZONE) {
 $scope['zone_id'] = $current->id;
 }

 $current = $current->parent;
 }

 // Keep the selected office on every user. Legacy municipality scope fields
 // are still populated for existing workflow compatibility.
 $scope['office_id'] = $office->id;
 }

 return $scope;
 }


    protected function normalizeDepartment(int|string|null $departmentId, int|string|null $officeId): ?int
    {
        if (! $departmentId) {
            return null;
        }

        $department = Department::query()->findOrFail($departmentId);

        if ($officeId && (int) $department->office_id !== (int) $officeId) {
            throw ValidationException::withMessages([
                'department_id' => ['Selected department does not belong to the selected office.'],
            ]);
        }

        return (int) $department->id;
    }

    protected function isPaymentProcurementRole(string $roleName): bool
    {
        return in_array($roleName, [
            User::ROLE_MANAGER,
            User::ROLE_HEAD_DEVELOPMENT_BRANCH,
            User::ROLE_HEAD_SERVICE_BRANCH,
            User::ROLE_PLANNING_BUDGET_TEAM_LEADER,
            User::ROLE_PLANNING_BUDGET_EXPERT,
            User::ROLE_PROCUREMENT_REQUESTER,
            User::ROLE_PAYMENT_REQUESTER,
            User::ROLE_RECORDS_OFFICE,
            User::ROLE_FINANCE,
            User::ROLE_FINANCE_ACCOUNTANT,
            User::ROLE_ASSET_TEAM_LEADER,
            User::ROLE_MACHINERY_TEAM_LEADER,
        ], true);
    }

    protected function isBusinessAssignableRole(string $roleName): bool
    {
        if ($roleName === User::ROLE_SUPER_ADMIN) {
            return false;
        }

        return in_array($roleName, User::userManagementRoleNames(), true);
    }

    protected function normalizeProfessionalLevel(string $roleName, ?string $professionalLevel): ?string
    {
        if ($roleName !== User::ROLE_PLANNING_BUDGET_EXPERT) {
            return null;
        }

        if (! in_array($professionalLevel, ['III', 'IV'], true)) {
            throw ValidationException::withMessages([
                'professional_level' => ['Level is required for Planning & Budget Expert.'],
            ]);
        }

        return $professionalLevel;
    }

    protected function normalizeAdminLevel(string $roleName, ?string $adminLevel, array $scope): ?string
    {
        if ($roleName === User::ROLE_SUPER_ADMIN || in_array($roleName, User::userManagementRoleNames(), true) || $this->isPaymentProcurementRole($roleName)) {
            return null;
        }

        $adminLevel = $adminLevel ?: User::LEVEL_ZONE;

        if (! in_array($adminLevel, [User::LEVEL_CITY, User::LEVEL_SUBCITY, User::LEVEL_WOREDA, User::LEVEL_ZONE], true)) {
            throw ValidationException::withMessages(['admin_level' => ['Invalid admin level.']]);
        }

        if ($adminLevel === User::LEVEL_SUBCITY && empty($scope['sub_city_id'])) {
            throw ValidationException::withMessages(['sub_city_id' => ['Subcity is required for subcity admin.']]);
        }

        if ($adminLevel === User::LEVEL_WOREDA && empty($scope['woreda_id'])) {
            throw ValidationException::withMessages(['woreda_id' => ['Woreda is required for woreda admin.']]);
        }

        if ($adminLevel === User::LEVEL_ZONE && empty($scope['zone_id'])) {
            throw ValidationException::withMessages(['zone_id' => ['Zone is required for zone admin.']]);
        }

        return $adminLevel;
    }

    protected function ensureScopeAssignable(User $actor, ?string $targetLevel, array $scope): void
    {
        if ($actor->isSuperAdmin() || $targetLevel === null) {
            return;
        }

        if (! $actor->isAdmin()) {
            $this->denyScope();
        }

        if ($this->levelRank($targetLevel) > $this->levelRank($actor->admin_level)) {
            throw ValidationException::withMessages(['admin_level' => ['You cannot assign a higher administrative level.']]);
        }

        if ($actor->admin_level === User::LEVEL_SUBCITY && $actor->sub_city_id !== ($scope['sub_city_id'] ?? null)) {
            $this->denyScope();
        }

        if ($actor->admin_level === User::LEVEL_WOREDA && $actor->woreda_id !== ($scope['woreda_id'] ?? null)) {
            $this->denyScope();
        }

        if ($actor->admin_level === User::LEVEL_ZONE && $actor->zone_id !== ($scope['zone_id'] ?? null)) {
            $this->denyScope();
        }
    }

    protected function ensureRoleAssignable(User $actor, string $roleName, ?string $targetLevel): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($roleName === User::ROLE_SUPER_ADMIN) {
            throw ValidationException::withMessages(['role' => ['Only Super Admin can assign Super Admin.']]);
        }

        if (! $actor->isAdmin()) {
            if ($actor->isBusinessManager() && $this->isBusinessAssignableRole($roleName)) {
                return;
            }

            throw ValidationException::withMessages(['role' => ['You cannot assign this role.']]);
        }

        if ($this->levelRank($targetLevel) > $this->levelRank($actor->admin_level)) {
            throw ValidationException::withMessages(['admin_level' => ['You cannot assign a higher administrative level.']]);
        }
    }

    protected function levelRank(?string $level): int
    {
        return match ($level) {
            User::LEVEL_CITY => 80,
            User::LEVEL_SUBCITY => 70,
            User::LEVEL_WOREDA => 60,
            User::LEVEL_ZONE => 50,
            default => 0,
        };
    }

    protected function denyScope(): void
    {
        throw ValidationException::withMessages(['office_id' => ['Selected office is outside your administrative scope.']]);
    }

    protected function findRole(string $roleName): Role
    {
        return Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'sanctum')
            ->firstOrFail();
    }

    protected function officePayload(?Office $office): ?array
    {
        if (! $office) {
            return null;
        }

        return [
            'id' => $office->id,
            'name' => $office->name,
            'type' => $office->type,
            'code' => $office->code,
            'parent_id' => $office->parent_id,
        ];
    }
}
