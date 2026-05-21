<?php

use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\SearchSessionController;
use Illuminate\Support\Facades\Route;

// --- Auth (publico) ---
Route::get ('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',  [AuthController::class, 'login'])    ->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])   ->name('logout')->middleware('auth');

// --- Mapa e historico (requiere login; admin u observador da igual para ver) ---
Route::middleware('auth')->group(function () {
    Route::get('/',      [MapController::class, 'index'])->name('map');
    Route::get('/field', [FieldController::class, 'index'])->name('field');

    // Operativos / sesiones de busqueda.
    // OJO: rutas estaticas antes de las dinamicas con {session}.
    Route::get ('/sessions',                  [SearchSessionController::class, 'index']) ->name('sessions.index');
    Route::get ('/sessions/create',           [SearchSessionController::class, 'create'])->name('sessions.create');
    Route::post('/sessions',                  [SearchSessionController::class, 'store']) ->name('sessions.store');
    Route::get ('/sessions/{session}',        [SearchSessionController::class, 'show'])  ->name('sessions.show');
    Route::post('/sessions/{session}/close',  [SearchSessionController::class, 'close']) ->name('sessions.close');
    Route::post('/sessions/{session}/notes',  [SearchSessionController::class, 'addNote'])->name('sessions.notes.add');
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
