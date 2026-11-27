<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: [
            'webhooks/payment/*',
            'driver/trip/*/status',
            'driver/trip/*/issue',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->expectsJson(),
        );

        $exceptions->dontReportDuplicates();

        $exceptions->context(function (Throwable $e): array {
            $scrub = static fn (string $value): string => (string) preg_replace(
                [
                    '#(booking/[^/]+/)[^/\s?]+#i',
                    '#(driver/trip/)[^/\s?]+#i',
                    '/AIza[0-9A-Za-z\-_]{15,}/',
                    '/Bearer\s+[A-Za-z0-9\-._~+\/=]+/i',
                    '/base64:[A-Za-z0-9+\/=]{16,}/i',
                    '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
                    '/(?<!\d)\+?\d{8,15}(?!\d)/',
                    '/-?\d{1,2}\.\d{4,},\s*-?\d{1,3}\.\d{4,}/',
                ],
                [
                    '$1[REDACTED_TOKEN]',
                    '$1[REDACTED_TOKEN]',
                    '[REDACTED_API_KEY]',
                    'Bearer [REDACTED_TOKEN]',
                    'base64:[REDACTED_SECRET]',
                    '[REDACTED_EMAIL]',
                    '[REDACTED_PHONE]',
                    '[REDACTED_COORDINATES]',
                ],
                $value
            );

            $request = request();

            return [
                'safe_path' => $scrub($request->path()),
                'safe_exception' => mb_substr($scrub($e->getMessage()), 0, 240),
                'app_env' => config('app.env'),
            ];
        });
    })
    ->create();
