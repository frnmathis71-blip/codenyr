<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerificationIfEnabled
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        if (Features::enabled(Features::emailVerification())) {
            return app(EnsureEmailIsVerified::class)->handle($request, $next, $redirectToRoute);
        }

        return $next($request);
    }
}
