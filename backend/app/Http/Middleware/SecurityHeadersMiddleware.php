<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Security headers (OWASP Recommended)
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // CSP compatible with SPA production build without unsafe-eval or unsafe-inline scripts
        $csp = "default-src 'self'; "
            ."script-src 'self'; "
            ."style-src 'self' 'unsafe-inline'; "
            ."font-src 'self' data:; "
            ."img-src 'self' data: blob:; "
            ."connect-src 'self'; "
            ."frame-ancestors 'self';";

        $response->headers->set('Content-Security-Policy', $csp);

        // HSTS when HTTPS is used or in production
        if ($request->isSecure() || app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
