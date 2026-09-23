<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|max:1024',
        ]);
        $credentials['email'] = Str::lower($credentials['email']);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'Неверный email или пароль.']);
        }

        $request->session()->regenerate();
        $request->session()->forget('employee_id');
        $role = $request->user()->can('switch-to-hr') ? 'hr' : 'employee';
        $request->session()->put('role', $role);

        if ($role === 'employee') {
            $request->session()->forget('url.intended');
        }

        return $request->expectsJson()
            ? response()->json(['role' => $role])
            : redirect()->intended(route('employees.index'));
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson() ? response()->json(['logged_out' => true]) : redirect()->route('login');
    }
}
