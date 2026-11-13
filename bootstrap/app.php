<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\CanonicalHost::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // Демо только для чтения: закрытые маршруты, noindex, выход при выключенном демо (и в панели — AdminPanelProvider)
        $middleware->appendToGroup('web', \App\Http\Middleware\DemoGuard::class);
        $middleware->redirectGuestsTo('/app/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
