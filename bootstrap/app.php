<?php

use App\Http\Middleware\EnsureTwoFactorIsConfigured;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'two-factor.configured' => EnsureTwoFactorIsConfigured::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Never reveal model class names or internal ids in "not found" responses.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() && $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['success' => false, 'message' => 'The requested item was not found.'], 404);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->expectsJson() && $e->getPrevious() instanceof AuthorizationException) {
                return response()->json(['success' => false, 'message' => 'You do not have permission for this action.'], 403);
            }
        });
    })->create();
