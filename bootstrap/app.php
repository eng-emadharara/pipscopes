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
    ->withMiddleware(function (Middleware $middleware) {
        // Public site only; the Filament admin panel has its own middleware stack and stays in Arabic
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\MaintenanceMode::class,
            \App\Http\Middleware\PrelaunchGate::class,
        ]);

        // Laravel moves "auth" to the front of the stack; channel owners must get their message
        // ("owners can't write reviews") before being sent to the members' login page
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\BlockChannelOwners::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
