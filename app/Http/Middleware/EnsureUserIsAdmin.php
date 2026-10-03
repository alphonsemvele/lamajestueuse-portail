<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le tableau de bord de l'administration.
 *
 * Il revient au seul super administrateur : un administrateur garde tout le
 * reste — le portail, les modules, le perimetre RH — mais n'entre pas ici.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isSuperAdmin()) {
            abort(403, 'Cet espace est réservé aux super administrateurs du portail.');
        }

        return $next($request);
    }
}
