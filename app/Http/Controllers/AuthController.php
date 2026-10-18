<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        return view('auth.login', ['email' => $request->cookie('del_last_user_email', '')]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->with('error', 'Email atau kata sandi salah.');
        }
        $request->session()->regenerate();
        session(['login_timestamp' => now()->format('d-m-Y H:i:s')]);
        Cookie::queue('del_last_user_email', $request->email, 10080);

        return redirect()->intended(route('buku.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
