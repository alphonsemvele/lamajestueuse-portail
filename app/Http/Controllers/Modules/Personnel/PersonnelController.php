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
                // L'effectif, c'est le personnel du portail relevant des
                // entites suivies, dossier ouvert ou non.
                'agents' => User::where('status', 'active')->duPerimetreRh($perimetre)->count(),
                'contratsActifs' => Contrat::where('statut', 'actif')
                    ->when($perimetre !== null, fn ($q) => $q->whereIn('employeur_id', $perimetre))
                    ->count(),
                'sansDossier' => User::where('status', 'active')->duPerimetreRh($perimetre)
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

        /*
         * On liste le personnel du portail, pas les dossiers deja ouverts :
         * quelqu'un qui vient d'etre rattache a un institut doit apparaitre
         * tout de suite, meme si personne n'a encore rempli sa fiche.
         */
        $personnel = User::query()
            ->where('status', 'active')
            ->duPerimetreRh($perimetre)
            ->with([
                'agent',
                // Les contrats affiches sont eux aussi limites au perimetre :
                // le poste d'un agent dans un autre institut ne regarde pas
                // le gestionnaire de celui-ci.
                'agent.contratsActifs' => fn ($q) => $q->when($perimetre !== null, fn ($c) => $c->whereIn('employeur_id', $perimetre)),
                'agent.contratsActifs.employeur', 'agent.contratsActifs.echelon', 'agent.contratsActifs.profil',
            ])
            ->when($recherche !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$recherche}%")
                ->orWhere('lastname', 'like', "%{$recherche}%")
                ->orWhere('matricule', 'like', "%{$recherche}%")
                ->orWhere('email', 'like', "%{$recherche}%")))
            ->when($employeur, fn ($q, $id) => $q->whereHas('agent.contrats', fn ($c) => $c
                ->where('employeur_id', $id)->where('statut', 'actif')
                ->when($perimetre !== null, fn ($sub) => $sub->whereIn('employeur_id', $perimetre))))
            ->when($statut === 'sans_contrat', fn ($q) => $q->whereDoesntHave('agent.contrats', fn ($c) => $c
                ->where('statut', 'actif')
                ->when($perimetre !== null, fn ($sub) => $sub->whereIn('employeur_id', $perimetre))))
            ->when($statut === 'sans_dossier', fn ($q) => $q->whereDoesntHave('agent'))
            ->when($statut === 'plusieurs', fn ($q) => $q->whereHas('agent', fn ($a) => $a->has('contratsActifs', '>', 1)))
            ->orderBy('lastname')->orderBy('name')
            ->paginate(20)->withQueryString()
            ->through(fn (User $membre) => $this->ligneDePersonnel($membre));

        return Inertia::render('modules/personnel/agents/index', [
            'agents' => $personnel,
            'filtres' => ['q' => $recherche, 'employeur' => $employeur, 'statut' => $statut],
            'employeurs' => Employeur::when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre))
                ->orderBy('sigle')->get()->map(fn ($e) => $e->toUiArray())->all(),
            'peutGerer' => $this->peutGerer($request->user()),
            'sansDossier' => User::where('status', 'active')->duPerimetreRh($perimetre)
                ->whereDoesntHave('agent')->count(),
        ]);
    }

    /**
     * Une ligne de la liste : l'identite vient du compte du portail, le reste
     * du dossier RH quand il existe.
     *
     * @return array<string, mixed>
     */
    private function ligneDePersonnel(User $membre): array
    {
        $agent = $membre->agent;

        return [
            'userId' => $membre->id,
            'id' => $agent?->id,
            'dossierOuvert' => $agent !== null,
            'nom' => $membre->fullName(),
            'matricule' => $membre->matricule,
            'email' => $membre->email,
            'telephone' => $membre->phone,
            'poste' => $membre->poste,
            'entite' => $membre->entite,
            'photoUrl' => $membre->avatarUrl(),
            'initiales' => $membre->initials(),
            'anciennete' => $agent?->anciennete(),
            'contrats' => $agent?->contratsActifs->map(fn (Contrat $c) => $c->toUiArray())->all() ?? [],
        ];
    }

    public function show(Request $request, User $user): Response
    {
        $this->autoriserAcces($request->user());
        $this->verifierPersonne($request, $user);

        $perimetre = $this->perimetre($request);
        $dansPerimetre = fn ($q) => $q->when($perimetre !== null, fn ($sub) => $sub->whereIn('employeur_id', $perimetre));

        // Le dossier n'existe pas tant que personne n'y a rien saisi : la
        // fiche s'ouvre quand meme, vide, prete a etre remplie.
        $agent = $user->agent;

        return Inertia::render('modules/personnel/agents/fiche', [
            'agent' => $this->ficheAdministrative($user, $agent),
            'diplomes' => $agent ? $agent->diplomes->map(fn (Diplome $d) => $d->toUiArray())->all() : [],
            // Un agent partage entre deux instituts a deux contrats : chaque
            // gestionnaire ne voit que le sien, et la paie qui va avec.
            'contrats' => $agent
                ? $agent->contrats()->tap($dansPerimetre)->with(['employeur', 'echelon.categorie', 'profil'])
                    ->get()->map(fn (Contrat $c) => $c->toUiArray())->all()
                : [],
            'evenements' => $agent
                ? $agent->evenements()->with('contrat.employeur')
                    ->when($perimetre !== null, fn ($q) => $q->where(fn ($sub) => $sub
                        ->whereNull('contrat_id')
                        ->orWhereHas('contrat', fn ($c) => $c->whereIn('employeur_id', $perimetre))))
                    ->get()->map(fn (EvenementCarriere $e) => $e->toUiArray())->all()
                : [],
            'bulletins' => $agent
                ? $agent->bulletins()->with('employeur')->tap($dansPerimetre)
                    ->orderByDesc('annee')->orderByDesc('mois')->limit(12)->get()
                    ->map(fn ($b) => $b->toUiArray())->all()
                : [],
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

    /**
     * Identite du portail et dossier RH reunis. Sans dossier, les champs
     * administratifs sont vides : la fiche reste consultable.
     *
     * @return array<string, mixed>
     */
    private function ficheAdministrative(User $user, ?Agent $agent): array
    {
        $donnees = $agent?->toUiArray() ?? [
            'id' => null,
            'dateNaissance' => null, 'lieuNaissance' => null, 'situationFamiliale' => null,
            'enfants' => 0, 'cni' => null, 'numeroCnps' => null, 'adresse' => null,
            'urgenceNom' => null, 'urgenceTelephone' => null, 'observations' => null,
            'anciennete' => null,
        ];

        return $donnees + [
            'userId' => $user->id,
            'dossierOuvert' => $agent !== null,
            'nom' => $user->fullName(),
            'matricule' => $user->matricule,
            'email' => $user->email,
            'telephone' => $user->phone,
            'poste' => $user->poste,
            'entite' => $user->entite,
            'photoUrl' => $user->avatarUrl(),
            'initiales' => $user->initials(),
        ];
    }

    /** Le dossier nait a la premiere saisie, pas avant. */
    private function dossierDe(User $user): Agent
    {
        return Agent::firstOrCreate(['user_id' => $user->id]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierPersonne($request, $user);

        $this->dossierDe($user)->update($request->validate([
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

    public function storeDiplome(Request $request, User $user): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierPersonne($request, $user);

        $this->dossierDe($user)->diplomes()->create($this->reglesDiplome($request));

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

    public function storeContrat(Request $request, User $user): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierPersonne($request, $user);

        $donnees = $this->reglesContrat($request);
        $this->verifierEntite($request, (int) $donnees['employeur_id']);

        $agent = $this->dossierDe($user);
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

    public function storeEvenement(Request $request, User $user): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierPersonne($request, $user);

        $donnees = $request->validate([
            'contrat_id' => ['nullable', 'exists:contrats,id'],
            'date_evenement' => ['required', 'date'],
            'type' => ['required', Rule::in(array_keys(EvenementCarriere::TYPES))],
            'libelle' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->dossierDe($user)->evenements()->create($donnees + ['saisi_par' => $request->user()->id]);

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
