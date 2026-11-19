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
        // Toda petición web: fija la empresa activa y corta si la suscripción no está vigente.
        // Orden por request: auth → set-tenant → subscription → plan → permission → (controlador) cupo.
        $middleware->web(append: [
            \App\Http\Middleware\SetTenant::class,
            \App\Http\Middleware\EnsureSubscriptionActive::class,
        ]);

        // La empresa activa debe fijarse ANTES de resolver los modelos de la ruta ({client}, {loan}…),
        // para que el filtro por empresa también aplique al route-model binding (→ 404 si es de otra empresa).
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\SetTenant::class,
        );
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\AuthenticateApiToken::class,
        );

        $middleware->alias([
            'check-role' => \App\Http\Middleware\CheckRole::class,
            'super-admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'check-company' => \App\Http\Middleware\CheckCompany::class,
            'check-permission' => \App\Http\Middleware\CheckPermission::class,
            'plan' => \App\Http\Middleware\CheckPlanModule::class,
            'no-tenant' => \App\Http\Middleware\WithoutTenant::class,
            'auth-api' => \App\Http\Middleware\AuthenticateApiToken::class,
            'api.subscription' => \App\Http\Middleware\EnsureSubscriptionActiveApi::class,
            'api-permission' => \App\Http\Middleware\ApiCheckPermission::class,
            'mobile-license' => \App\Http\Middleware\CheckMobileLicense::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
