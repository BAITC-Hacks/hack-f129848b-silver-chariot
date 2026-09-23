<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHrRole
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->session()->get('role', 'employee') === 'hr', 403);

        return $next($request);
    }
}
