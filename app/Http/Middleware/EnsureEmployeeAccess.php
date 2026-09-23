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
        abort_unless($request->session()->get('role', 'employee') === 'hr' || $employee->employee_id === $request->session()->get('employee_id', 'E0001'), 404);

        return $next($request);
    }
}
