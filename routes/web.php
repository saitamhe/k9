<?php

use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

// --- Auth (publico) ---
Route::get ('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',  [AuthController::class, 'login'])    ->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])   ->name('logout')->middleware('auth');

// --- Mapa e historico (requiere login; admin u observador da igual para ver) ---
Route::middleware('auth')->group(function () {
    Route::get('/',      [MapController::class, 'index'])->name('map');
    Route::get('/field', [FieldController::class, 'index'])->name('field');
});

// --- API consumida por el JS del mapa (necesita sesion web) ---
Route::middleware('auth')->prefix('api')->group(function () {
    Route::get('/positions/latest', [PositionController::class, 'latest'])->name('api.positions.latest');
    Route::get('/dogs/{dog}/track', [PositionController::class, 'track']) ->name('api.dogs.track');
});

// --- API de ingesta (consumida por serial_to_api.py y la PWA del guia).
//     Sin auth de sesion. ---
Route::prefix('api')->group(function () {
    Route::post('/positions/ingest',       [PositionController::class, 'ingest'])    ->name('api.positions.ingest');
    Route::post('/sync/batch',             [SyncController::class, 'batch'])         ->name('api.sync.batch');
    Route::post('/waypoints/{uuid}/photo', [SyncController::class, 'waypointPhoto']) ->name('api.waypoints.photo');
});
