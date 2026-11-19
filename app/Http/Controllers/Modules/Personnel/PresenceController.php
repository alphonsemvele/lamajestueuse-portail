<?php

namespace App\Http\Controllers\Modules\Personnel;

use App\Http\Controllers\Concerns\ServesModule;
use App\Http\Controllers\Controller;
use App\Models\Pointage;
use App\Models\RelaisLecteur;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La presence : ce que le lecteur annonce, a mesure qu'il l'annonce.
 *
 * Le lecteur pousse chaque passage vers le portail des qu'il a lieu. L'ecran
 * se contente de montrer ce qui est arrive, et se rafraichit tout seul — on
 * le laisse ouvert sur un coin de bureau.
 */
class PresenceController extends Controller
{
    use ServesModule;

    public const MODULE = 'personnel';

    public function index(Request $request): Response
    {
        $this->autoriserAcces($request->user());

        $jour = $this->jour($request);
        $recherche = trim((string) $request->query('q'));

        $pointages = Pointage::with('user')
            ->whereDate('moment', $jour)
            ->when($recherche !== '', fn ($q) => $q->where(fn ($sous) => $sous
                ->where('numero', 'like', "%{$recherche}%")
                ->orWhere('nom', 'like', "%{$recherche}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('name', 'like', "%{$recherche}%")
                    ->orWhere('lastname', 'like', "%{$recherche}%")
                    ->orWhere('matricule', 'like', "%{$recherche}%"))))
            ->orderByDesc('moment')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (Pointage $pointage) => [
                'id' => $pointage->id,
                'numero' => $pointage->numero,
                'nom' => $pointage->nomAffiche(),
                'matricule' => $pointage->user?->matricule,
                'heure' => $pointage->moment?->format('H:i:s'),
                'jour' => $pointage->moment?->format('d/m/Y'),
                'sens' => $pointage->sens,
                'moyen' => $pointage->moyen,
                'moyenLibelle' => Pointage::MOYENS[$pointage->moyen] ?? $pointage->moyen,
                'connu' => $pointage->user_id !== null,
            ]);

        $duJour = Pointage::whereDate('moment', $jour);

        return Inertia::render('modules/personnel/presence', [
            'pointages' => $pointages,
            'filtres' => ['jour' => $jour, 'q' => $recherche],
            'resume' => [
                'passages' => (clone $duJour)->count(),
                'personnes' => (clone $duJour)->distinct()->count('numero'),
                'premier' => (clone $duJour)->min('moment'),
                'dernier' => (clone $duJour)->max('moment'),
                'inconnus' => (clone $duJour)->whereNull('user_id')->distinct()->count('numero'),
            ],
            'lecteur' => $this->etatDuLecteur(),
            'peutGerer' => $this->peutGerer($request->user()),
        ]);
    }

    /** Le jour regarde : celui demande, ou aujourd'hui. */
    private function jour(Request $request): string
    {
        $demande = (string) $request->query('jour');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $demande) === 1
            ? $demande
            : now()->toDateString();
    }

    /**
     * De quoi dire si le lecteur parle encore.
     *
     * @return array{vu: ?string, present: bool, dernierPointage: ?string}
     */
    private function etatDuLecteur(): array
    {
        $relais = RelaisLecteur::first();
        $dernier = Pointage::max('moment');

        return [
            'vu' => $relais?->derniere_visite?->diffForHumans(),
            'present' => (bool) $relais?->estPresent(),
            'dernierPointage' => $dernier ? \Illuminate\Support\Carbon::parse($dernier)->diffForHumans() : null,
        ];
    }
}
