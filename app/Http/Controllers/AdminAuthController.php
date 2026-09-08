<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (session('saklshi_admin', false)) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = 'saklshi-admin-login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'ძალიან ბევრი მცდელობაა. სცადეთ დაახლოებით ერთ წუთში.',
            ]);
        }

        $login = (string) config('saklshi.admin_login');
        $password = (string) config('saklshi.admin_password');

        if ($password === '') {
            throw ValidationException::withMessages([
                'login' => 'Laravel Cloud-ში ჯერ დააყენეთ ADMIN_PASSWORD.',
            ]);
        }

        $valid = hash_equals(mb_strtolower($login), mb_strtolower(trim((string) $validated['login'])))
            && hash_equals($password, (string) $validated['password']);

        if (! $valid) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'login' => 'ლოგინი ან პაროლი არასწორია.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('saklshi_admin', true);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('saklshi_admin');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('reservation.index');
    }
}
