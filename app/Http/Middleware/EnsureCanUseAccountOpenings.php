<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanUseAccountOpenings
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canUseAccountOpenings(),
            403,
            'No tiene permisos para acceder al módulo de apertura de cuentas.'
        );

        return $next($request);
    }
}
