<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TranslationController;
use Illuminate\Support\Facades\Route;

Route::get('/translations/resources', [TranslationController::class, 'resources']);

Route::get('/ping', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working',
        'time' => now(),
    ]);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    Route::apiResource('admin/translations', TranslationController::class)->except(['show']);
});

require __DIR__ . '/auth.php';
require __DIR__ . '/user.php';
 
require __DIR__ . '/procurement.php';
