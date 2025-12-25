<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OsrmController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\StopController;
use App\Http\Controllers\RoutingJobController;

Route::get('/health', HealthController::class);

Route::get('/osrm/route', [OsrmController::class, 'route']);

Route::post('/routing-jobs', [RoutingJobController::class, 'store']);
Route::get('/routing-jobs', [RoutingJobController::class, 'index']);
Route::get('/routing-jobs/{id}', [RoutingJobController::class, 'show']);
Route::post('/routing-jobs/{id}/process', [RoutingJobController::class, 'process']);


Route::prefix('v1')->group(function () {
    Route::get('/ping', function () {
        return response()->json([
            'ok' => true,
            'service' => 'planora-api',
        ]);
    });

    Route::get('/stops', [StopController::class, 'index']);
    Route::get('/osrm/table', [OsrmController::class, 'table']);
});
