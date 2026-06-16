<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AssignUserRoleRequest;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\ResetUserPasswordRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function index(IndexUserRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $this->userService->paginateUsers($request->validated(), $request->user());

        return response()->json($this->userService->transformPaginatedUsers($users));
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $user = $this->userService->getUser($id, $request->user());
        $this->authorize('view', $user);

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $this->userService->transformUser($user),
            'meta' => null,
        ]);
    }

    public function rolesLite(Request $request): JsonResponse
    {
        $this->authorize('rolesLite', User::class);

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data' => $this->userService->getRolesLite($request->user()),
            'meta' => null,
        ]);
    }

    public function officesLite(Request $request): JsonResponse
    {
        $this->authorize('officesLite', User::class);

        return response()->json([
            'success' => true,
            'message' => 'Offices retrieved successfully',
            'data' => $this->userService->getOfficesLite(
                $request->user(),
                $request->query('type'),
                $request->query('parent_id')
            ),
            'meta' => null,
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->userService->createUser($request->validated(), $request->user(), $request->file('signature'), $request->file('stamp'), $request->file('titer'));

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $this->userService->transformUser($user),
            'meta' => null,
        ], 201);
    }

    public function update(UpdateUserRequest $request, int|string $id): JsonResponse
    {
        $user = $this->userService->getUser($id, $request->user());
        $this->authorize('update', $user);

        $updatedUser = $this->userService->updateUser($user, $request->validated(), $request->user(), $request->file('signature'), $request->file('stamp'), $request->file('titer'));

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $this->userService->transformUser($updatedUser),
            'meta' => null,
        ]);
    }

    public function assignRole(AssignUserRoleRequest $request, int|string $id): JsonResponse
    {
        $user = $this->userService->getUser($id, $request->user());
        $this->authorize('assignRole', $user);

        $updatedUser = $this->userService->assignRole($user, $request->validated()['role'], $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $this->userService->transformUser($updatedUser),
            'meta' => null,
        ]);
    }

    public function toggle(Request $request, int|string $id): JsonResponse
    {
        $user = $this->userService->getUser($id, $request->user());
        $this->authorize('toggle', $user);

        $updatedUser = $this->userService->toggleUser($user);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => $this->userService->transformUser($updatedUser),
            'meta' => null,
        ]);
    }

    public function resetPassword(ResetUserPasswordRequest $request, int|string $id): JsonResponse
    {
        $user = $this->userService->getUser($id, $request->user());
        $this->authorize('resetPassword', $user);

        $this->userService->resetPassword($user, $request->validated()['new_password']);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful',
            'data' => ['id' => $user->id],
            'meta' => null,
        ]);
    }


    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $updatedUser = $this->userService->updateProfile(
            $request->user(),
            $request->validated(),
            $request->file('profile')
        );

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $this->userService->transformUser($updatedUser),
            'meta' => null,
        ]);
    }


    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['new_password']),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
            'data' => null,
            'meta' => null,
        ]);
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $user = $this->userService->getUser($id, $request->user());
        $this->authorize('delete', $user);

        $this->userService->deleteUser($user, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
            'data' => null,
            'meta' => null,
        ]);
    }
}
