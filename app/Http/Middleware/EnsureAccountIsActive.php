<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le statut du compte est verifie a CHAQUE requete, pas seulement a la
 * connexion. Sans cela, un compte remis en attente ou suspendu continuerait
 * d'ouvrir les applications jusqu'a l'expiration de sa session.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isActive()) {
            return $next($request);
        }

        $message = $user->status === 'suspended'
            ? __("Ce compte a été suspendu. Contactez l'administration.")
            : __("Ce compte est en attente de validation par l'administration.");

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', $message);
    }
}
