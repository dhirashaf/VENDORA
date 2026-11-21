<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function login(): View
    {
        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'work_email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt([
            'work_email' => $credentials['work_email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();
            AuditLogger::record(AuditLogger::LOGIN, $request->user());

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'work_email' => 'Email atau kata sandi tidak sesuai.',
        ])->onlyInput('work_email');
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()) {
            AuditLogger::record(AuditLogger::LOGOUT, $request->user());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
