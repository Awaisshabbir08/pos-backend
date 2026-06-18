<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\SetTenantContext;
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
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->alias([
            'permission' => CheckPermission::class,
            'role'       => CheckRole::class,
            'tenant'     => SetTenantContext::class,
        ]);

        // CRITICAL: SetTenantContext must run BEFORE SubstituteBindings,
        // otherwise route-model binding fires queries with no tenant context
        // and the BelongsToTenant global scope can't apply — letting a tenant
        // admin read another tenant's resource by ID. Auth must also run before
        // tenant so $request->user() is populated.
        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            SetTenantContext::class,                                              // <- runs before bindings
            \Illuminate\Routing\Middleware\SubstituteBindings::class,             // <- bindings now see tenant context
            CheckPermission::class,
            CheckRole::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
