<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionRoleController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['role' => 'required|in:employee,hr']);
        $request->session()->put('role', $data['role']);

        if ($request->expectsJson()) {
            return response()->json(['role' => $data['role']]);
        }

        return $data['role'] === 'hr'
            ? redirect()->route('employees.index')
            : redirect()->route('employees.show', $request->session()->get('employee_id', 'E0001'));
    }
}
