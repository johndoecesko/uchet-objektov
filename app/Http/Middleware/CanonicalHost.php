<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Переводит старые и дополнительные адреса сайта на основной из APP_URL (301).
 * Только GET/HEAD: POST по 301 браузеры и вебхуки повторяют как GET или не повторяют вовсе.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.force_canonical_host') || ! $request->isMethodSafe()) {
            return $next($request);
        }

        $canonical = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! $canonical || strcasecmp($request->getHost(), $canonical) === 0) {
            return $next($request);
        }

        return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri(), 301);
    }
}
