<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Eximir endpoints API de CSRF — clientes son helper Python, la PWA del guia
        // y los jobs de reenvio entre instancias (local <-> remoto).
        $middleware->validateCsrfTokens(except: [
            'api/positions/ingest',
            'api/sync/batch',
            'api/waypoints/*/photo',
            'api/sessions/upsert',
            'api/sessions/*/notes',
        ]);

        // Donde redirigir cuando no hay sesion autenticada.
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Alias para middleware de rol admin.
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
