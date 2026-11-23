<?php

namespace App\Http\Middleware;

use App\Models\AccessLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde la trace des pages visitees.
 *
 * Le journal savait qui se connectait ; il sait maintenant ou chacun va. On
 * ne retient que les pages reellement ouvertes : ni les envois de
 * formulaire, ni les rafraichissements qu'un ecran se commande tout seul,
 * ni les telechargements — sans quoi l'ecran de presence, qui se recharge
 * toutes les dix secondes, remplirait le journal a lui seul.
 *
 * Une meme page revue dans la foulee ne s'inscrit qu'une fois : on retient
 * la visite, pas le va-et-vient.
 */
class JournaliserLaVisite
{
    /** Au-dela de ce delai, revenir sur une page compte pour une visite. */
    private const REPOS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        if ($this->vautLaPeine($request, $reponse)) {
            $this->inscrire($request);
        }

        return $reponse;
    }

    private function vautLaPeine(Request $request, Response $reponse): bool
    {
        return $request->isMethod('GET')
            && $request->user() !== null
            && $reponse->getStatusCode() === 200
            // Un rechargement partiel d'Inertia n'est pas une visite : c'est
            // la meme page qui se met a jour toute seule.
            && ! $request->hasHeader('X-Inertia-Partial-Data')
            && ! $request->ajax()
            && $this->estUnePage($reponse);
    }

    /** Une page, et non un fichier : le journal ne compte pas les telechargements. */
    private function estUnePage(Response $reponse): bool
    {
        $type = (string) $reponse->headers->get('Content-Type');

        return str_contains($type, 'text/html') || str_contains($type, 'application/json');
    }

    private function inscrire(Request $request): void
    {
        $cible = $request->route()?->getName() ?: '/'.ltrim($request->path(), '/');
        $cle = 'visite:'.$request->user()->id.':'.md5($cible);

        if (! Cache::add($cle, true, self::REPOS)) {
            return;
        }

        AccessLog::create([
            'user_id' => $request->user()->id,
            'action' => 'visite',
            'cible' => mb_substr($cible, 0, 255),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
