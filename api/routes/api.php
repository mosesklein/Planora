<?php

use Illuminate\Support\Facades\Route;

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
});
