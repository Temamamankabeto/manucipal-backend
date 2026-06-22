<?php

use App\Http\Controllers\Api\Administration\DepartmentController;
use App\Http\Controllers\Api\Administration\OfficeController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/profile/update', [UserController::class, 'updateProfile']);
    Route::post('/profile/change-password', [UserController::class, 'changePassword']);
});

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::apiResource('offices', OfficeController::class);
    Route::apiResource('departments', DepartmentController::class);
    Route::get('offices/{office}/departments', [DepartmentController::class, 'byOffice']);

    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/{id}', [AuditLogController::class, 'show']);

    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/role-permissions', [RoleController::class, 'permissions']);
    Route::get('/roles/{id}/permissions', [RoleController::class, 'rolePermissions']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::post('/roles/{id}/permissions', [RoleController::class, 'assignPermissions']);

    Route::get('/users/roles-lite', [UserController::class, 'rolesLite']);
    Route::get('/users/offices-lite', [UserController::class, 'officesLite']);
    Route::get('/users/departments-lite', [UserController::class, 'departmentsLite']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::post('/users/{id}', [UserController::class, 'update']);
    Route::patch('/users/{id}/toggle', [UserController::class, 'toggle']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
    Route::post('/users/{id}/roles', [UserController::class, 'assignRole']);

    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::post('/permissions', [PermissionController::class, 'store']);
    Route::put('/permissions/{id}', [PermissionController::class, 'update']);
    Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);
});
