<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin, 403, 'Acesso exclusivo para administradores.');

        $response=$next($request);
        $response->headers->set('Cache-Control','private, no-store');
        return $response;
    }
}
