<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanReviewConsents
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user?->canReviewConsents() || $user?->isAdministrator(),
            403,
            'Solo el perfil de la abogada o administrador puede revisar el control de consentimientos.'
        );

        return $next($request);
    }
}
