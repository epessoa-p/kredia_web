<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Support\LoginThrottle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $login = trim((string) $request->login);
        $throttleKey = LoginThrottle::key($login, $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, LoginThrottle::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withInput($request->only('login'))
                ->withErrors(['login' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos."]);
        }

        try {
            $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

            if (!Auth::attempt([$field => $login, 'password' => $request->password], $request->boolean('remember'))) {
                RateLimiter::hit($throttleKey, LoginThrottle::DECAY_SECONDS);
                return back()->withInput($request->only('login'))->withErrors(['login' => 'Las credenciales no son válidas']);
            }

            RateLimiter::clear($throttleKey);
            // Nueva sesión tras autenticarse (evita fijación de sesión).
            $request->session()->regenerate();

            $user = Auth::user();

            if (!$user->active) {
                Auth::logout();
                return back()->withErrors(['login' => 'Tu usuario ha sido desactivado']);
            }

            if ($user->is_super_admin) {
                session(['current_company_id' => null]);
                return redirect()->route('dashboard');
            }

            $companies = $user->activeCompanies()->get();

            if ($companies->isEmpty()) {
                Auth::logout();
                return back()->withErrors(['login' => 'No tienes acceso a ninguna empresa']);
            }

            if ($companies->count() === 1) {
                session(['current_company_id' => $companies->first()->id]);
                return redirect()->route('dashboard');
            }

            return redirect()->route('select-company')->with('companies', $companies);
        } catch (\Throwable $exception) {
            Log::error('Error al iniciar sesión', [
                'login' => $request->login,
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['login' => 'No fue posible iniciar sesión en este momento.']);
        }
    }

    public function selectCompany()
    {
        $user = auth()->user();
        $companies = $user->activeCompanies()->get();

        if ($companies->isEmpty()) {
            Auth::logout();
            return redirect('login')->with('error', 'No tienes acceso a ninguna empresa');
        }

        return view('auth.select-company', compact('companies'));
    }

    public function setCompany($companyId)
    {
        $user = auth()->user();
        $company = $user->activeCompanies()->findOrFail($companyId);

        session(['current_company_id' => $company->id]);

        return redirect()->route('dashboard');
    }

    public function logout()
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect('/login');
    }
}
