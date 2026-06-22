<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', 'min:8'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);

        if (class_exists(\Spatie\Permission\Models\Role::class) && \Spatie\Permission\Models\Role::where('name', 'Admin')->where('guard_name', 'sanctum')->exists()) {
            $user->assignRole('Admin');
        }

        $accessToken = $user->createToken('municipality-api-token')->plainTextToken;
        $refreshToken = Str::random(64);
        $user->forceFill(['refresh_token' => hash('sha256', $refreshToken), 'refresh_token_expires_at' => now()->addDays(30)])->save();

        return response()->json($this->authPayload($user->fresh(['office','department','subCity','woreda','zone']), $accessToken, $refreshToken), 201)->cookie($this->refreshCookie($refreshToken));
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $login = trim((string) $request->input('login'));

        $user = User::with(['office','department','subCity','woreda','zone'])
            ->where(function ($query) use ($login) {
                $query->where('email', $login)
                    ->orWhere('phone', $login);
            })
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Invalid email/phone number or password.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['success' => false, 'message' => 'Your account is disabled. Please contact the administrator.'], 403);
        }

        $accessToken = $user->createToken('municipality-api-token')->plainTextToken;
        $refreshToken = Str::random(64);
        $user->forceFill(['refresh_token' => hash('sha256', $refreshToken), 'refresh_token_expires_at' => now()->addDays(30), 'last_login_at' => now()])->save();

        return response()->json($this->authPayload($user->fresh(['office','department','subCity','woreda','zone']), $accessToken, $refreshToken))->cookie($this->refreshCookie($refreshToken));
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['office','department','subCity','woreda','zone']);
        return response()->json(['success' => true, 'message' => 'Authenticated user retrieved successfully', 'data' => $this->userPayload($user), 'user' => $this->userPayload($user), 'roles' => $user->getRoleNames()->values()->all(), 'permissions' => $user->getAllPermissions()->pluck('name')->values()->all()]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $user?->currentAccessToken()?->delete();
        $user?->forceFill(['refresh_token' => null, 'refresh_token_expires_at' => null])->save();
        return response()->json(['success' => true, 'message' => 'Logged out successfully', 'data' => null])->withoutCookie('refresh_token');
    }

    protected function authPayload(User $user, string $accessToken, string $refreshToken): array
    {
        return ['success' => true, 'message' => 'Authenticated successfully', 'data' => ['token' => $accessToken, 'refresh_token' => $refreshToken, 'user' => $this->userPayload($user), 'roles' => $user->getRoleNames()->values()->all(), 'permissions' => $user->getAllPermissions()->pluck('name')->values()->all()], 'token' => $accessToken, 'refresh_token' => $refreshToken, 'user' => $this->userPayload($user), 'roles' => $user->getRoleNames()->values()->all(), 'permissions' => $user->getAllPermissions()->pluck('name')->values()->all()];
    }

    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'status' => $user->is_active ? 'active' : 'disabled',
            'office_id' => $user->office_id,
            'department_id' => $user->department_id,
            'admin_level' => $user->admin_level,
            'sub_city_id' => $user->sub_city_id,
            'subcity_id' => $user->sub_city_id,
            'woreda_id' => $user->woreda_id,
            'zone_id' => $user->zone_id,
            'office' => $this->officePayload($user->office),
            'department' => $this->departmentPayload($user->department),
            'sub_city' => $this->officePayload($user->subCity),
            'subcity' => $this->officePayload($user->subCity),
            'woreda' => $this->officePayload($user->woreda),
            'zone' => $this->officePayload($user->zone),
            'last_login_at' => optional($user->last_login_at)->toISOString(),
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }

    protected function officePayload($office): ?array
    {
        return $office ? ['id' => $office->id, 'name' => $office->name, 'code' => $office->code, 'type' => $office->type, 'parent_id' => $office->parent_id] : null;
    }

    protected function departmentPayload($department): ?array
    {
        return $department ? ['id' => $department->id, 'name' => $department->name, 'office_id' => $department->office_id] : null;
    }

    protected function refreshCookie(string $refreshToken)
    {
        return cookie('refresh_token', $refreshToken, 60 * 24 * 30, '/', null, app()->environment('production'), true)->withSameSite('Lax');
    }
}
