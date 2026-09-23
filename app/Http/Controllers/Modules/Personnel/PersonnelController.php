<?php

namespace App\Http\Controllers\Modules\Personnel;

use App\Http\Controllers\Concerns\ServesModule;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Contrat;
use App\Models\Diplome;
use App\Models\Employeur;
use App\Models\EvenementCarriere;
use App\Models\ProfilSalaire;
use App\Models\User;
use App\Services\PaieService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestion administrative du personnel : dossier, diplomes, contrats et
 * carriere. La paie vit dans son propre controleur, mais s'appuie sur les
 * contrats saisis ici.
 */
class PersonnelController extends Controller
{
    use ServesModule;

    public const MODULE = 'personnel';

    public function index(Request $request, PaieService $paie): Response
    {
        $this->autoriserAcces($request->user());

        $mois = (int) ($request->query('mois') ?: now()->month);
        $annee = (int) ($request->query('annee') ?: now()->year);

        // Tout l'ecran se limite aux entites confiees a l'utilisateur.
        $perimetre = $this->perimetre($request);

        $employeurs = Employeur::withCount(['contrats as effectif' => fn ($q) => $q->where('statut', 'actif')])
            ->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre))
            ->orderBy('sigle')->get();

        return Inertia::render('modules/personnel/index', [
            'periode' => ['mois' => $mois, 'annee' => $annee],
            'employeurs' => $employeurs->map(fn (Employeur $e) => $e->toUiArray() + [
                'effectif' => (int) $e->effectif,
                'masse' => $paie->masseSalariale($mois, $annee, $e->id),
            ])->all(),
            'perimetreLimite' => $perimetre !== null,
            'chiffres' => [
                'agents' => Agent::duPerimetre($perimetre)->count(),
                'contratsActifs' => Contrat::where('statut', 'actif')
                    ->when($perimetre !== null, fn ($q) => $q->whereIn('employeur_id', $perimetre))
                    ->count(),
                'sansDossier' => User::where('status', 'active')
                    ->whereDoesntHave('agent')->count(),
                'masse' => $paie->masseSalariale($mois, $annee, null, $perimetre),
            ],
            // Un CDD qui se termine dans les deux mois demande une decision.
            'echeances' => Contrat::with(['agent.user', 'employeur'])
                ->when($perimetre !== null, fn ($q) => $q->whereIn('employeur_id', $perimetre))
                ->where('statut', 'actif')->whereNotNull('date_fin')
                ->whereDate('date_fin', '>=', now()->toDateString())
                ->whereDate('date_fin', '<=', now()->addMonths(2)->toDateString())
                ->orderBy('date_fin')->get()
                ->map(fn (Contrat $c) => $c->toUiArray())->all(),
            'evenements' => EvenementCarriere::with('agent.user')
                ->whereHas('agent', fn ($q) => $q->duPerimetre($perimetre))
                ->orderByDesc('date_evenement')->limit(8)->get()
                ->map(fn (EvenementCarriere $e) => $e->toUiArray())->all(),
            'peutGerer' => $this->peutGerer($request->user()),
        ]);
    }

    public function agents(Request $request): Response
    {
        $this->autoriserAcces($request->user());

        $recherche = trim((string) $request->query('q'));
        $employeur = $request->query('employeur');
        $statut = $request->query('statut');
        $perimetre = $this->perimetre($request);

        $agents = Agent::query()
            ->duPerimetre($perimetre)
            ->with([
                'user',
                // Les contrats affiches sont eux aussi limites au perimetre :
                // le poste d'un agent dans un autre institut ne regarde pas
                // le gestionnaire de celui-ci.
                'contratsActifs' => fn ($q) => $q->when($perimetre !== null, fn ($c) => $c->whereIn('employeur_id', $perimetre)),
                'contratsActifs.employeur', 'contratsActifs.echelon', 'contratsActifs.profil',
            ])
            ->when($recherche !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%{$recherche}%")
                ->orWhere('lastname', 'like', "%{$recherche}%")
                ->orWhere('matricule', 'like', "%{$recherche}%")
                ->orWhere('email', 'like', "%{$recherche}%")))
            ->when($employeur, fn ($q, $id) => $q->whereHas('contrats', fn ($c) => $c
                ->where('employeur_id', $id)->where('statut', 'actif')
                ->when($perimetre !== null, fn ($sub) => $sub->whereIn('employeur_id', $perimetre))))
            ->when($statut === 'sans_contrat', fn ($q) => $q->whereDoesntHave('contrats', fn ($c) => $c
                ->where('statut', 'actif')
                ->when($perimetre !== null, fn ($sub) => $sub->whereIn('employeur_id', $perimetre))))
            ->when($statut === 'plusieurs', fn ($q) => $q->has('contratsActifs', '>', 1))
            ->join('users', 'users.id', '=', 'agents.user_id')
            ->orderBy('users.lastname')->orderBy('users.name')
            ->select('agents.*')
            ->paginate(20)->withQueryString()
            ->through(fn (Agent $a) => $a->toUiArray() + [
                'contrats' => $a->contratsActifs->map(fn (Contrat $c) => $c->toUiArray())->all(),
            ]);

        return Inertia::render('modules/personnel/agents/index', [
            'agents' => $agents,
            'filtres' => ['q' => $recherche, 'employeur' => $employeur, 'statut' => $statut],
            'employeurs' => Employeur::when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre))
                ->orderBy('sigle')->get()->map(fn ($e) => $e->toUiArray())->all(),
            'peutGerer' => $this->peutGerer($request->user()),
            // Comptes du portail qui n'ont pas encore de dossier personnel.
            'comptesSansDossier' => $this->peutGerer($request->user())
                ? User::where('status', 'active')->whereDoesntHave('agent')
                    ->orderBy('lastname')->get()
                    ->map(fn (User $u) => [
                        'id' => $u->id,
                        'nom' => $u->fullName(),
                        'matricule' => $u->matricule,
                        'email' => $u->email,
                        'poste' => $u->poste,
                    ])->all()
                : [],
        ]);
    }

    public function show(Request $request, Agent $agent): Response
    {
        $this->autoriserAcces($request->user());
        $this->verifierAgent($request, $agent);

        $perimetre = $this->perimetre($request);
        $dansPerimetre = fn ($q) => $q->when($perimetre !== null, fn ($sub) => $sub->whereIn('employeur_id', $perimetre));

        $agent->load([
            'user', 'diplomes',
            'contrats.employeur', 'contrats.echelon.categorie', 'contrats.profil',
            'evenements.contrat.employeur',
            'bulletins.employeur',
        ]);

        return Inertia::render('modules/personnel/agents/fiche', [
            'agent' => $agent->toUiArray(),
            'diplomes' => $agent->diplomes->map(fn (Diplome $d) => $d->toUiArray())->all(),
            // Un agent partage entre deux instituts a deux contrats : chaque
            // gestionnaire ne voit que le sien, et la paie qui va avec.
            'contrats' => $agent->contrats()->tap($dansPerimetre)->with(['employeur', 'echelon.categorie', 'profil'])
                ->get()->map(fn (Contrat $c) => $c->toUiArray())->all(),
            'evenements' => $agent->evenements()->with('contrat.employeur')
                ->when($perimetre !== null, fn ($q) => $q->where(fn ($sub) => $sub
                    ->whereNull('contrat_id')
                    ->orWhereHas('contrat', fn ($c) => $c->whereIn('employeur_id', $perimetre))))
                ->get()->map(fn (EvenementCarriere $e) => $e->toUiArray())->all(),
            'bulletins' => $agent->bulletins()->with('employeur')->tap($dansPerimetre)
                ->orderByDesc('annee')->orderByDesc('mois')->limit(12)->get()
                ->map(fn ($b) => $b->toUiArray())->all(),
            'referentiels' => [
                'employeurs' => Employeur::where('actif', true)
                    ->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre))
                    ->orderBy('sigle')->get()
                    ->map(fn ($e) => $e->toUiArray())->all(),
                'profils' => ProfilSalaire::where('actif', true)->with('echelon')->orderBy('nom')->get()
                    ->map(fn ($p) => $p->toUiArray())->all(),
                'types' => Contrat::TYPES,
                'evenements' => EvenementCarriere::TYPES,
                'niveaux' => Diplome::NIVEAUX,
            ],
            'peutGerer' => $this->peutGerer($request->user()),
        ]);
    }

    /** Ouvre un dossier personnel pour un compte existant du portail. */
    public function store(Request $request): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $donnees = $request->validate([
            'user_id' => ['required', 'exists:users,id', 'unique:agents,user_id'],
        ]);

        $agent = Agent::create($donnees);

        return redirect()->route('personnel.agents.show', $agent)
            ->with('status', __('Dossier ouvert.'));
    }

    public function update(Request $request, Agent $agent): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierAgent($request, $agent);

        $agent->update($request->validate([
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:255'],
            'situation_familiale' => ['nullable', Rule::in(['celibataire', 'marie', 'divorce', 'veuf'])],
            'enfants' => ['nullable', 'integer', 'min:0', 'max:30'],
            'cni' => ['nullable', 'string', 'max:60'],
            'numero_cnps' => ['nullable', 'string', 'max:40'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'urgence_nom' => ['nullable', 'string', 'max:255'],
            'urgence_telephone' => ['nullable', 'string', 'max:40'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]));

        return back()->with('status', __('Dossier mis à jour.'));
    }

    // ------------------------------------------------------------ diplomes

    public function storeDiplome(Request $request, Agent $agent): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierAgent($request, $agent);

        $agent->diplomes()->create($this->reglesDiplome($request));

        return back()->with('status', __('Diplôme ajouté.'));
    }

    public function updateDiplome(Request $request, Diplome $diplome): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierAgent($request, $diplome->agent);

        $diplome->update($this->reglesDiplome($request));

        return back()->with('status', __('Diplôme mis à jour.'));
    }

    public function destroyDiplome(Request $request, Diplome $diplome): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierAgent($request, $diplome->agent);

        $diplome->delete();

        return back()->with('status', __('Diplôme supprimé.'));
    }

    /** @return array<string, mixed> */
    private function reglesDiplome(Request $request): array
    {
        return $request->validate([
            'intitule' => ['required', 'string', 'max:255'],
            'niveau' => ['nullable', Rule::in(Diplome::NIVEAUX)],
            'specialite' => ['nullable', 'string', 'max:255'],
            'etablissement' => ['nullable', 'string', 'max:255'],
            'annee_obtention' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'piece_fournie' => ['boolean'],
        ]);
    }

    // ------------------------------------------------------------ contrats

    public function storeContrat(Request $request, Agent $agent): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierAgent($request, $agent);

        $donnees = $this->reglesContrat($request);
        $this->verifierEntite($request, (int) $donnees['employeur_id']);

        $contrat = $agent->contrats()->create($donnees);

        // Le recrutement s'inscrit tout seul dans la carriere.
        $agent->evenements()->create([
            'contrat_id' => $contrat->id,
            'date_evenement' => $contrat->date_debut,
            'type' => 'recrutement',
            'libelle' => $contrat->poste.' — '.$contrat->employeur?->sigle,
            'saisi_par' => $request->user()->id,
        ]);

        return back()->with('status', __('Contrat enregistré.'));
    }

    public function updateContrat(Request $request, Contrat $contrat): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        // L'entite actuelle comme la nouvelle doivent etre dans le perimetre :
        // on ne sort pas un contrat de son institut par la bande.
        $this->verifierEntite($request, $contrat->employeur_id);

        $donnees = $this->reglesContrat($request);
        $this->verifierEntite($request, (int) $donnees['employeur_id']);

        $contrat->update($donnees);

        return back()->with('status', __('Contrat mis à jour.'));
    }

    public function destroyContrat(Request $request, Contrat $contrat): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierEntite($request, $contrat->employeur_id);

        // Un contrat qui a deja produit un bulletin ne se supprime plus : on le
        // termine, sinon la paie du mois perdrait sa piece justificative.
        if ($contrat->bulletins()->exists()) {
            return back()->withErrors([
                'contrat' => __('Ce contrat a déjà des bulletins : terminez-le au lieu de le supprimer.'),
            ]);
        }

        $contrat->delete();

        return back()->with('status', __('Contrat supprimé.'));
    }

    /** @return array<string, mixed> */
    private function reglesContrat(Request $request): array
    {
        $donnees = $request->validate([
            'employeur_id' => ['required', 'exists:employeurs,id'],
            'type' => ['required', Rule::in(array_keys(Contrat::TYPES))],
            'poste' => ['required', 'string', 'max:255'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after:date_debut'],
            'quotite' => ['required', 'integer', 'min:1', 'max:100'],
            'profil_salaire_id' => ['nullable', 'exists:profils_salaire,id'],
            'echelon_id' => ['nullable', 'exists:echelons,id'],
            'statut' => ['required', Rule::in(['actif', 'suspendu', 'termine'])],
            'motif_fin' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($donnees['statut'] !== 'termine') {
            $donnees['motif_fin'] = null;
        }

        return $donnees;
    }

    // ------------------------------------------------------------ carriere

    public function storeEvenement(Request $request, Agent $agent): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierAgent($request, $agent);

        $donnees = $request->validate([
            'contrat_id' => ['nullable', 'exists:contrats,id'],
            'date_evenement' => ['required', 'date'],
            'type' => ['required', Rule::in(array_keys(EvenementCarriere::TYPES))],
            'libelle' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $agent->evenements()->create($donnees + ['saisi_par' => $request->user()->id]);

        return back()->with('status', __('Événement ajouté au dossier.'));
    }

    public function destroyEvenement(Request $request, EvenementCarriere $evenement): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierAgent($request, $evenement->agent);

        $evenement->delete();

        return back()->with('status', __('Événement supprimé.'));
    }
}
