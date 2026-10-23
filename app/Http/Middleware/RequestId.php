<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        // Honour an upstream-provided request ID (e.g. from a load balancer)
        // or generate a new one
        $requestId = $request->header('X-Request-ID') ?: (string) Str::uuid();

        // Make the ID available to the entire request lifecycle
        $request->headers->set('X-Request-ID', $requestId);

        $response = $next($request);

        // Echo it back so clients can correlate logs
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}