<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->route('employee');
        abort_unless($request->user()->can('access-hr') || $employee->employee_id === $request->user()->employee_id, 404);

        return $next($request);
    }
}
