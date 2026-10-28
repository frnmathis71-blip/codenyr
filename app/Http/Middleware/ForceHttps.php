<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        // One address for visitors and search engines: www.codenyr.fr → codenyr.fr.
        $host = parse_url(config('app.url'), PHP_URL_HOST);
        if ($host && $request->getHost() === 'www.'.$host) {
            return redirect()->away(rtrim(config('app.url'), '/').$request->getRequestUri(), 301);
        }

        if (config('security.force_https') && ! $request->isSecure()) {
            return redirect()->secure($request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
