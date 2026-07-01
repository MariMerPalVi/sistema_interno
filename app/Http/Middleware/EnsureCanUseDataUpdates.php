<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanUseDataUpdates
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canUseDataUpdates(),
            403,
            'No tiene permisos para acceder al módulo de actualización de datos.'
        );

        return $next($request);
    }
}
