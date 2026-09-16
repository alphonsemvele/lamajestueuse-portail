<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le portail est le SEUL endroit de l'ecosysteme ou un mot de passe est saisi
 * et verifie. Les applications metier n'ont plus de page de connexion : elles
 * redirigent ici et recuperent l'identite via le portail.
 */
class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('auth/login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        // On accepte l'adresse professionnelle ou le matricule.
        $field = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'matricule';

        $attempt = Auth::attempt(
            [$field => $credentials['username'], 'password' => $credentials['password']],
            $request->boolean('remember')
        );

        if (! $attempt) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'username' => __("Identifiants incorrects. Vérifiez votre adresse ou votre matricule."),
            ]);
        }

        if (! $request->user()->isActive()) {
            $status = $request->user()->status;
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'username' => $status === 'suspended'
                    ? __("Ce compte a été suspendu. Contactez l'administration.")
                    : __("Ce compte n'est pas encore activé."),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate();

        $user = $request->user();

        // Si le visiteur a choisi une langue sur la page de connexion, ce choix
        // l'emporte sur la preference enregistree et devient la nouvelle
        // preference du compte. Sinon on applique celle du compte.
        $chosen = $request->session()->get('locale');

        if ($chosen && $chosen !== $user->locale) {
            $user->locale = $chosen;
        } else {
            $request->session()->put('locale', $user->locale);
        }

        $user->forceFill(['last_login_at' => now(), 'locale' => $user->locale])->save();

        AccessLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            AccessLog::create([
                'user_id' => $user->id,
                'action' => 'logout',
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('Vous avez été déconnecté.'));
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'username' => __("Trop de tentatives. Réessayez dans :seconds secondes.", ['seconds' => $seconds]),
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('username')).'|'.$request->ip());
    }
}
