<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\EnsureCanReviewConsents;
use App\Http\Middleware\EnsureCanUseAccountOpenings;
use App\Http\Middleware\EnsureCanUseDataUpdates;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account-openings' => EnsureCanUseAccountOpenings::class,
            'data-updates' => EnsureCanUseDataUpdates::class,
            'review-consents' => EnsureCanReviewConsents::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
