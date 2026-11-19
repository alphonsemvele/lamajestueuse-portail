<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pointage;
use App\Models\RelaisLecteur;
use App\Services\JetonRelais;
use App\Services\LecturePointage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * L'arrivee des pointages au portail.
 *
 * Deux portes, pour la meme chose. Le lecteur pousse lui-meme ses evenements
 * des qu'ils ont lieu : c'est le chemin court, et il ne sait porter qu'un
 * jeton dans l'adresse — ni en-tete, ni signature. Le relais, lui, rattrape
 * ce qui manque et signe comme pour le reste.
 */
class PointagesController extends Controller
{
    /** Le lecteur annonce un passage, a l'instant ou il a lieu. */
    public function recevoir(Request $request, string $jeton, LecturePointage $lecture): JsonResponse
    {
        $attendu = JetonRelais::jetonPointage();

        $relais = mb_strlen($jeton) >= 32 && $attendu !== null && hash_equals($attendu, $jeton)
            ? (RelaisLecteur::first() ?? new RelaisLecteur(['nom' => 'Relais du site', 'client_id' => 'relais-lecteur']))
            : RelaisLecteur::where('jeton_pointage', $jeton)->first();

        if ($relais === null || mb_strlen($jeton) < 32) {
            return response()->json(['message' => 'Jeton inconnu.'], 404);
        }

        $relais->forceFill(['derniere_visite' => now()])->save();

        $evenements = $this->evenementsDe($request);
        $gardes = 0;

        foreach ($evenements as $evenement) {
            if ($lecture->estUnPassage($evenement) && $lecture->enregistrer($evenement)) {
                $gardes++;
            }
        }

        if ($evenements === []) {
            Log::info('Pointage : annonce illisible du lecteur.', ['type' => $request->header('Content-Type')]);
        }

        return response()->json(['recus' => count($evenements), 'gardes' => $gardes]);
    }

    /** Le relais rapporte ce que le lecteur a garde pendant une coupure. */
    public function rattraper(Request $request, LecturePointage $lecture): JsonResponse
    {
        if (JetonRelais::verifier($request->bearerToken(), 'lecteur') === null) {
            return response()->json(['message' => 'Relais inconnu.'], 401);
        }

        $gardes = 0;

        foreach ((array) $request->input('evenements', []) as $evenement) {
            if (is_array($evenement) && $lecture->estUnPassage($evenement) && $lecture->enregistrer($evenement)) {
                $gardes++;
            }
        }

        return response()->json([
            'gardes' => $gardes,
            // De quoi reprendre la ou l'on s'est arrete au prochain passage.
            'dernier' => Pointage::max('moment'),
        ]);
    }

    /**
     * L'annonce du lecteur, quelle que soit la facon dont il l'habille.
     *
     * Selon son reglage il envoie du JSON brut, ou une piece jointe nommee
     * « event_log » dans un envoi multipart. On accepte les deux.
     *
     * @return array<int, array<string, mixed>>
     */
    private function evenementsDe(Request $request): array
    {
        $charges = [];

        if ($request->isJson() && is_array($request->json()->all())) {
            $charges[] = $request->json()->all();
        }

        // Selon le reglage, « event_log » arrive en texte JSON ou deja
        // decoupe par PHP : les deux formes se valent ici.
        foreach (['event_log', 'eventLog'] as $champ) {
            $valeur = $request->input($champ);

            if (is_string($valeur) && $valeur !== '') {
                $charges[] = json_decode($valeur, true);
            } elseif (is_array($valeur)) {
                $charges[] = $valeur;
            }
        }

        foreach ($request->allFiles() as $fichier) {
            $fichier = is_array($fichier) ? ($fichier[0] ?? null) : $fichier;
            $contenu = $fichier?->getMimeType() === 'application/json' || $fichier?->getClientOriginalExtension() === 'json'
                ? @file_get_contents($fichier->getRealPath())
                : null;

            if (is_string($contenu)) {
                $charges[] = json_decode($contenu, true);
            }
        }

        return collect($charges)
            ->filter(fn ($charge) => is_array($charge) && isset($charge['AccessControllerEvent']))
            ->values()
            ->all();
    }
}
