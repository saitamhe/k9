<?php

use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MapController::class, 'index'])->name('map');
Route::get('/field', [FieldController::class, 'index'])->name('field');

Route::prefix('api')->group(function () {
    Route::get ('/positions/latest', [PositionController::class, 'latest'])->name('api.positions.latest');
    Route::get ('/dogs/{dog}/track', [PositionController::class, 'track']) ->name('api.dogs.track');
    Route::post('/positions/ingest', [PositionController::class, 'ingest'])->name('api.positions.ingest');

    // Sync desde la PWA del guia (batch posiciones+waypoints, fotos por separado)
    Route::post('/sync/batch',              [SyncController::class, 'batch'])         ->name('api.sync.batch');
    Route::post('/waypoints/{uuid}/photo',  [SyncController::class, 'waypointPhoto'])->name('api.waypoints.photo');
});
