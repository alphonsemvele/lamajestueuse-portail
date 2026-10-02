<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Concerns\ServesModule;
use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Models\User;
use App\Services\BulletinPdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as ReponseInertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Mes bulletins de paie : chacun consulte et telecharge les siens.
 *
 * Un bulletin n'apparait qu'une fois valide par le service des ressources
 * humaines. Tant qu'il est au brouillon, ses montants peuvent encore
 * changer : le montrer reviendrait a annoncer un salaire qui bougera.
 */
class MesBulletinsController extends Controller
{
    use ServesModule;

    public const MODULE = 'bulletins';

    /** Ce qui est visible du salarie : ni plus tot, ni autrement. */
    private const VISIBLES = ['valide', 'paye'];

    public function index(Request $request): ReponseInertia
    {
        $this->autoriserAcces($request->user());

        $recherche = trim((string) $request->query('q'));
        $annee = $request->query('annee');

        $bulletins = $this->miens($request->user())
            ->with(['employeur', 'contrat'])
            ->when($annee, fn ($q) => $q->where('annee', (int) $annee))
            ->when($recherche !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->whereHas('employeur', fn ($e) => $e->where('nom', 'like', "%{$recherche}%")
                    ->orWhere('sigle', 'like', "%{$recherche}%"))
                ->orWhereHas('contrat', fn ($c) => $c->where('poste', 'like', "%{$recherche}%"))))
            ->orderByDesc('annee')->orderByDesc('mois')
            ->paginate(12)->withQueryString()
            ->through(fn (Bulletin $b) => $b->toUiArray());

        return Inertia::render('modules/bulletins/index', [
            'bulletins' => $bulletins,
            'filtres' => ['q' => $recherche, 'annee' => $annee],
            'annees' => $this->miens($request->user())
                ->distinct()->orderByDesc('annee')->pluck('annee')->all(),
            'identite' => [
                'nom' => $request->user()->fullName(),
                'matricule' => $request->user()->matricule,
            ],
            'enTete' => $this->enTete($request->user()),
        ]);
    }

    /**
     * Le bulletin en PDF, a l'ecran ou enregistre.
     *
     * `?apercu=1` le sert en ligne, pour la previsualisation ; sans ce
     * drapeau le navigateur propose de l'enregistrer.
     */
    public function telecharger(Request $request, Bulletin $bulletin, BulletinPdf $pdf): SymfonyResponse
    {
        $this->autoriserAcces($request->user());

        // Un bulletin ne se consulte que par son titulaire, et seulement une
        // fois valide.
        abort_unless($bulletin->agent?->user_id === $request->user()->id, 403);
        abort_unless(in_array($bulletin->statut, self::VISIBLES, true), 404);

        return $pdf->reponse($bulletin, $request->boolean('apercu'));
    }

    /**
     * L'en-tete du bulletin : logo et nom.
     *
     * Une personne rattachee a un seul institut porte ses couleurs ; servir
     * plusieurs instituts n'en privilegie aucun, c'est le groupe qui
     * l'emporte.
     *
     * @return array{nom: string, logo: ?string, couleur: string, groupe: bool}
     */
    private function enTete(User $utilisateur): array
    {
        $instituts = $utilisateur->applications()
            ->where('applications.type', 'application')
            ->where('applications.is_active', true)
            ->get();

        if ($instituts->count() === 1) {
            $institut = $instituts->first();

            return [
                'nom' => $institut->name,
                'logo' => $this->fichierEnBase64($institut->logo),
                'couleur' => $institut->color ?: '#0f766e',
                'groupe' => false,
            ];
        }

        return ['nom' => 'LA MAJESTUEUSE', 'logo' => null, 'couleur' => '#0f766e', 'groupe' => true];
    }

    /**
     * Le logo encode dans le document : dompdf ne va pas chercher les images
     * sur le reseau, et une URL laisserait un cadre vide.
     */
    private function fichierEnBase64(?string $chemin): ?string
    {
        if (blank($chemin) || str_starts_with($chemin, 'http')) {
            return null;
        }

        foreach ([Storage::disk('public')->path($chemin), public_path($chemin)] as $fichier) {
            if (is_file($fichier)) {
                return 'data:'.(mime_content_type($fichier) ?: 'image/png')
                    .';base64,'.base64_encode((string) file_get_contents($fichier));
            }
        }

        return null;
    }

    /** Les bulletins de la personne connectee, visibles seulement. */
    private function miens(User $utilisateur): Builder
    {
        return Bulletin::whereIn('statut', self::VISIBLES)
            ->whereHas('agent', fn ($a) => $a->where('user_id', $utilisateur->id));
    }
}
