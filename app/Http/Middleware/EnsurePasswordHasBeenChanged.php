<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordHasBeenChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && !$request->routeIs('password.*', 'logout')) {
            return redirect()->route('password.edit')
                ->with('success', 'Debe cambiar su contraseña temporal antes de continuar.');
        }

        return $next($request);
    }
}
