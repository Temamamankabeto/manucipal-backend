<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working',
        'time' => now(),
    ]);
});

require __DIR__ . '/auth.php';
require __DIR__ . '/user.php';
 
require __DIR__ . '/procurement.php';
