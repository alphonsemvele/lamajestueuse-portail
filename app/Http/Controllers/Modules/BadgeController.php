<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Concerns\HandlesMediaUploads;
use App\Http\Controllers\Concerns\ServesModule;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\DemandeBadge;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Badges du personnel : chacun demande le sien, le service qui les fabrique
 * traite les demandes.
 *
 * La demande est ouverte a tout le personnel connecte, sans tuile prealable :
 * un badge se demande une fois, souvent avant d'avoir la moindre habitude du
 * portail. Seul le traitement est reserve aux roles declares.
 */
class BadgeController extends Controller
{
    use HandlesMediaUploads;
    use ServesModule;

    public const MODULE = 'badges';

    // --------------------------------------------------- cote demandeur

    public function index(Request $request): Response
    {
        $utilisateur = $request->user();
        $this->autoriserAcces($utilisateur);

        $demandes = DemandeBadge::with(['institut', 'traitePar'])
            ->where('user_id', $utilisateur->id)
            ->latest()->get()
            ->map(fn (DemandeBadge $d) => $d->toUiArray())->all();

        return Inertia::render('modules/badges/index', [
            'demandes' => $demandes,
            'enCours' => DemandeBadge::where('user_id', $utilisateur->id)->enCours()->exists(),
            'instituts' => $this->institutsDe($utilisateur),
            'identite' => [
                'nom' => $utilisateur->fullName(),
                'matricule' => $utilisateur->matricule,
                'poste' => $utilisateur->poste,
                'photoUrl' => $utilisateur->avatarUrl(),
                'initiales' => $utilisateur->initials(),
            ],
            'motifs' => DemandeBadge::MOTIFS,
            'validite' => (int) config('badges.validite_annees'),
            'peutGerer' => $this->peutGerer($utilisateur),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $utilisateur = $request->user();
        $this->autoriserAcces($utilisateur);

        if (DemandeBadge::where('user_id', $utilisateur->id)->enCours()->exists()) {
            return back()->withErrors([
                'badge' => __('Une demande est déjà en cours : attendez qu’elle soit traitée ou annulez-la.'),
            ]);
        }

        $instituts = collect($this->institutsDe($utilisateur))->pluck('id')->all();

        $donnees = $request->validate([
            'nom_affiche' => ['required', 'string', 'max:80'],
            'poste_affiche' => ['nullable', 'string', 'max:120'],
            // Le formulaire ne propose plus de choix : un seul modele existe.
            'modele' => ['nullable', Rule::in(array_keys(config('badges.modeles')))],
            'motif' => ['required', Rule::in(array_keys(DemandeBadge::MOTIFS))],
            'application_id' => [
                // Obligatoire des que la personne sert au moins un institut :
                // c'est ce choix qui decide du logo imprime.
                $instituts === [] ? 'nullable' : 'required',
                Rule::in($instituts),
            ],
            'commentaire' => ['nullable', 'string', 'max:500'],
            'photo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'application_id.required' => __('Choisissez l’institut dont le logo figurera sur votre badge.'),
            'application_id.in' => __('Vous n’êtes pas rattaché à cet institut.'),
        ]);

        $photo = $this->resolveMedia($request, null, 'photo', 'badges/photos');
        unset($donnees['photo_file']);

        $demande = DemandeBadge::create($donnees + [
            'modele' => 'classique',
            'numero' => DemandeBadge::prochainNumero(),
            'user_id' => $utilisateur->id,
            'photo' => $photo,
            'statut' => 'en_attente',
        ]);

        return back()->with('status', __('Demande :numero enregistrée.', ['numero' => $demande->numero]));
    }

    /** Le demandeur retire sa demande tant qu'elle n'est pas traitée. */
    public function destroy(Request $request, DemandeBadge $demande): RedirectResponse
    {
        abort_unless($demande->user_id === $request->user()->id, 403);

        if ($demande->statut !== 'en_attente') {
            return back()->withErrors(['badge' => __('Cette demande est déjà en cours de traitement.')]);
        }

        $this->deleteUploaded($demande->photo);
        $demande->delete();

        return back()->with('status', __('Demande annulée.'));
    }

    // ---------------------------------------------------- cote traitement

    public function gestion(Request $request): Response
    {
        $this->autoriserGestion($request->user());

        $statut = $request->query('statut');
        $institut = $request->query('institut');
        $recherche = trim((string) $request->query('q'));

        $demandes = DemandeBadge::with(['user', 'institut', 'traitePar'])
            ->when(in_array($statut, array_keys(DemandeBadge::STATUTS), true),
                fn ($q) => $q->where('statut', $statut))
            ->when($institut, fn ($q, $id) => $q->where('application_id', $id))
            ->when($recherche !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('numero', 'like', "%{$recherche}%")
                ->orWhere('nom_affiche', 'like', "%{$recherche}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('name', 'like', "%{$recherche}%")
                    ->orWhere('lastname', 'like', "%{$recherche}%")
                    ->orWhere('matricule', 'like', "%{$recherche}%"))))
            ->orderByRaw("CASE statut WHEN 'en_attente' THEN 0 WHEN 'approuvee' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate(20)->withQueryString()
            ->through(fn (DemandeBadge $d) => $d->toUiArray());

        return Inertia::render('modules/badges/gestion', [
            'demandes' => $demandes,
            'filtres' => ['statut' => $statut, 'institut' => $institut, 'q' => $recherche],
            'instituts' => Application::active()->where('type', 'application')->orderBy('name')->get()
                ->map(fn (Application $a) => [
                    'id' => $a->id, 'name' => $a->name, 'color' => $a->color, 'logoUrl' => $a->logoUrl(),
                ])->all(),
            'statuts' => DemandeBadge::STATUTS,
            'compteurs' => DemandeBadge::selectRaw('statut, count(*) as total')
                ->groupBy('statut')->pluck('total', 'statut')->all(),
            'validite' => (int) config('badges.validite_annees'),
        ]);
    }

    public function traiter(Request $request, DemandeBadge $demande): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $donnees = $request->validate([
            'statut' => ['required', Rule::in(array_keys(DemandeBadge::STATUTS))],
            'motif_refus' => ['nullable', 'string', 'max:500'],
        ]);

        if ($donnees['statut'] === 'refusee' && blank($donnees['motif_refus'] ?? null)) {
            return back()->withErrors(['motif_refus' => __('Dites pourquoi la demande est refusée.')]);
        }

        $demande->update([
            'statut' => $donnees['statut'],
            'motif_refus' => $donnees['statut'] === 'refusee' ? $donnees['motif_refus'] : null,
            'traite_par' => $request->user()->id,
            'traite_le' => now(),
        ]);

        return back()->with('status', __('Demande :numero : :statut.', [
            'numero' => $demande->numero,
            'statut' => mb_strtolower(DemandeBadge::STATUTS[$demande->statut]),
        ]));
    }

    /** Planche d'impression : les badges approuvés, prêts à être tirés. */
    public function impression(Request $request): Response
    {
        $this->autoriserGestion($request->user());

        $choisies = array_filter(explode(',', (string) $request->query('demandes')));

        $demandes = DemandeBadge::with(['user', 'institut'])
            ->when($choisies !== [], fn ($q) => $q->whereIn('id', $choisies))
            ->when($choisies === [], fn ($q) => $q->where('statut', 'approuvee'))
            ->orderBy('numero')->get()
            ->map(fn (DemandeBadge $d) => $d->toUiArray())->all();

        return Inertia::render('modules/badges/impression', [
            'demandes' => $demandes,
            'validite' => (int) config('badges.validite_annees'),
            'mention' => config('badges.mention'),
        ]);
    }

    // ------------------------------------------------------------ outils

    /**
     * Instituts auxquels la personne est rattachée. S'il y en a plusieurs, le
     * formulaire lui fait choisir le logo ; s'il n'y en a qu'un, il est retenu
     * d'office.
     *
     * @return array<int, array<string, mixed>>
     */
    private function institutsDe(User $utilisateur): array
    {
        return $utilisateur->applications()
            ->where('applications.type', 'application')
            ->where('applications.is_active', true)
            ->orderBy('applications.name')->get()
            ->map(fn (Application $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'color' => $a->color,
                'logoUrl' => $a->logoUrl(),
                'poste' => $a->pivot->poste,
            ])->all();
    }

    /**
     * La demande est ouverte a tout le personnel : on verifie seulement que le
     * module est en service.
     */
    protected function autoriserAcces(User $utilisateur): Application
    {
        $module = $this->module();

        abort_if($module === null, 404);

        return $module;
    }
}
