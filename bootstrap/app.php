<?php

use App\Exceptions\InvalidKycTransitionException;
use App\Http\Middleware\EnsureExpertEmailIsVerified;
use App\Http\Middleware\EnsureUserEmailIsVerified;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsExpert;
use App\Http\Middleware\EnsureUserIsRegularUser;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'admin' => EnsureUserIsAdmin::class,
            'expert' => EnsureUserIsExpert::class,
            'expert.verified' => EnsureExpertEmailIsVerified::class,
            'regular-user' => EnsureUserIsRegularUser::class,
            'user.verified' => EnsureUserEmailIsVerified::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'status' => false,
                'message' => 'The provided data is invalid.',
                'errors' => $exception->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'status' => false,
                'message' => 'Too many attempts. Please try again later.',
            ], 429, $exception->getHeaders());
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->routeIs('auth.user', 'auth.email.send', 'auth.logout', 'auth.logout-all')) {
                return null;
            }

            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to access the user area.',
            ], 403);
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->is('api/admin/*')) {
                return null;
            }

            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to access the administration area.',
            ], 403);
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->is('api/expert/*')) {
                return null;
            }

            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to access the expert area.',
            ], 403);
        });

        $exceptions->render(function (InvalidSignatureException $exception, Request $request) {
            if (! $request->is('api/email/verify/*', 'api/expert/auth/email/verify/*')) {
                return null;
            }

            return response()->json([
                'status' => false,
                'message' => 'The email verification link is invalid or has expired.',
            ], 403);
        });

        $exceptions->render(function (InvalidKycTransitionException $exception, Request $request) {
            if (! $request->is('api/expert/kyc*', 'api/admin/kyc*')) {
                return null;
            }

            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 409);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/expert/kyc*', 'api/admin/kyc*')) {
                return null;
            }

            return response()->json([
                'status' => false,
                'message' => 'The requested KYC resource was not found.',
            ], 404);
        });
    })->create();
