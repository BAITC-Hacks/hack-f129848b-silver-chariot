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

        return $request->expectsJson() ? response()->json(['role' => $data['role']]) : redirect()->route('employees.index');
    }
}
