<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    private function guard()
    {
        return Auth::guard('admin');
    }

    public function showLogin()
    {
        if ($this->guard()->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'user'     => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $field = filter_var($credentials['user'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $attempt = $this->guard()->attempt([
            $field     => strtolower($credentials['user']),
            'password' => $credentials['password'],
        ], $request->boolean('remember'));

        if (! $attempt) {
            return back()->withErrors(['user' => 'Invalid credentials.'])->withInput();
        }

        // Verify admin flag — log out of admin guard immediately if not, so the
        // session is never left in a half-authenticated state.
        if (! $this->guard()->user()->isAdmin()) {
            $this->guard()->logout();
            return back()->withErrors(['user' => 'Access denied.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}