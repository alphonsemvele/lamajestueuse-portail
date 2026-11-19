<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TacheLecteur;
use App\Services\JetonRelais;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Le guichet du relais : il vient chercher les ordres, il rapporte.
 *
 * Le portail ne joint pas le lecteur — son adresse est privee. C'est donc le
 * relais, installe sur le site, qui sort : il demande ici ce qu'il y a a
 * faire, l'execute devant l'appareil, et revient dire ce qu'il en est. Aucun
 * port n'est ouvert sur le reseau de l'etablissement.
 */
class RelaisLecteurController extends Controller
{
    /** Combien d'ordres le relais emporte d'un coup. */
    private const PAR_PASSAGE = 5;

    /** Les ordres a executer, marques comme pris. */
    public function prendre(Request $request): JsonResponse
    {
        $relais = JetonRelais::verifier($request->bearerToken(), 'lecteur');

        if ($relais === null) {
            return response()->json(['message' => 'Relais inconnu.'], 401);
        }

        $relais->update(['derniere_visite' => now()]);

        TacheLecteur::reprendreLesAbandonnees();

        $taches = TacheLecteur::aFaire()->limit(self::PAR_PASSAGE)->get();

        TacheLecteur::whereIn('id', $taches->pluck('id'))
            ->update(['statut' => 'prise', 'prise_le' => now()]);

        return response()->json([
            'taches' => $taches->map(fn (TacheLecteur $tache) => [
                'id' => $tache->id,
                'action' => $tache->action,
                'parametres' => $tache->parametres ?? [],
            ])->all(),
        ]);
    }

    /** Le compte rendu d'un ordre : ce qui a ete fait, ou ce qui a manque. */
    public function rendre(Request $request, TacheLecteur $tache): JsonResponse
    {
        $relais = JetonRelais::verifier($request->bearerToken(), 'lecteur');

        if ($relais === null) {
            return response()->json(['message' => 'Relais inconnu.'], 401);
        }

        $donnees = $request->validate([
            'resultat' => ['nullable', 'array'],
            'erreur' => ['nullable', 'string', 'max:2000'],
        ]);

        $relais->update(['derniere_visite' => now()]);

        $tache->update([
            'statut' => filled($donnees['erreur'] ?? null) ? 'echouee' : 'faite',
            'resultat' => $donnees['resultat'] ?? null,
            'erreur' => $donnees['erreur'] ?? null,
            'terminee_le' => now(),
        ]);

        return response()->json(['message' => 'Reçu.']);
    }
}
