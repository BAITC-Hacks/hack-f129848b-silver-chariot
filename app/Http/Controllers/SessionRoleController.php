<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SessionRoleController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['role' => 'required|in:employee,hr']);
        if ($data['role'] === 'hr') {
            Gate::authorize('switch-to-hr');
        }

        $request->session()->regenerate();
        $request->session()->put('role', $data['role']);

        if ($request->expectsJson()) {
            return response()->json(['role' => $data['role']]);
        }

        return $data['role'] === 'hr'
            ? redirect()->route('employees.index')
            : redirect()->route('employees.show', $request->user()->employee_id);
    }
}
