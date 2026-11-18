<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        // If user is already authenticated, redirect to dashboard
        if (Auth::check()) {
            $user = Auth::user();
            
            // Check if user is active and has proper role
            if ($user->is_active && ($user->isAdmin() || $user->isStaff())) {
                return redirect()->route('admin.dashboard')->with('info', 'You are already logged in.');
            } else {
                // If user is inactive or doesn't have proper role, logout and show login
                Auth::logout();
                return view('auth.login')->withErrors(['email' => 'Your account is inactive or unauthorized.']);
            }
        }
        
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            
            if (!$user->is_active) {
                Auth::logout();
                return back()->withErrors(['email' => 'Your account is inactive.']);
            }

            if ($user->isAdmin() || $user->isStaff()) {
                return redirect()->intended(route('admin.dashboard'));
            }

            Auth::logout();
            return back()->withErrors(['email' => 'Unauthorized access.']);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
