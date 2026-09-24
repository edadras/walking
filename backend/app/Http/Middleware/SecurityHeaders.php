<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every response. API responses additionally get
 * a deny-all CSP (they're JSON, never rendered); panel pages can't be framed
 * by other origins.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->remove('X-Powered-By');

        if ($request->is('api/*')) {
            $h->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
            $h->set('X-Frame-Options', 'DENY');
            // Personal data: never cached by proxies or the device HTTP cache.
            $h->set('Cache-Control', 'no-store, private');
        } else {
            $h->set('X-Frame-Options', 'SAMEORIGIN');
            $h->set('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
        }

        if ($request->isSecure() && app()->isProduction()) {
            $h->set('Strict-Transport-Security', 'max-age='.config('walk.security.hsts_max_age').'; includeSubDomains');
        }

        return $response;
    }
}
