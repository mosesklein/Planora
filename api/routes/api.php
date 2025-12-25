<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StopController;

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'app' => 'planora-api',
        'time' => now()->toISOString(),
    ]);
});


Route::prefix('v1')->group(function () {
    Route::get('/ping', function () {
        return response()->json([
            'ok' => true,
            'service' => 'planora-api',
        ]);
    });

    Route::get('/stops', [StopController::class, 'index']);
});
