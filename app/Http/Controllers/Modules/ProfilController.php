<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Concerns\ServesModule;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Application;
use App\Models\Contrat;
use App\Models\DemandeBadge;
use App\Models\Diplome;
use App\Models\DocumentAgent;
use App\Models\EvenementCarriere;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mon profil : ce que chacun voit de lui-meme.
 *
 * Le module est ouvert a tout le personnel et ne demande aucune
 * attribution. On y lit son identite, ses instituts, son parcours et sa
 * remuneration, et on y depose ses diplomes et ses pieces — qui attendent
 * alors la validation du service du personnel.
 *
 * Tout y est en lecture seule, sauf ce depot : l'identite et la carriere
 * restent la main de la RH, pour que le dossier garde sa valeur.
 */
class ProfilController extends Controller
{
    use ServesModule;

    public const MODULE = 'profil';

    public function index(Request $request): Response
    {
        $this->autoriserAcces($request->user());

        $moi = $request->user()->load([
            'applications' => fn ($q) => $q->where('applications.type', 'application'),
            'employeur',
        ]);

        $agent = $moi->agent;

        return Inertia::render('modules/profil/index', [
            // Un lien peut designer l'onglet a ouvrir : le message qui annonce
            // un badge validé renvoie droit sur sa visualisation.
            'ongletInitial' => $request->query('onglet'),

            'identite' => [
                'nom' => $moi->fullName(),
                'prenom' => $moi->name,
                'nomFamille' => $moi->lastname,
                'matricule' => $moi->matricule,
                'email' => $moi->email,
                'telephone' => $moi->phone,
                'poste' => $moi->poste,
                'sexe' => $moi->sexe,
                'photoUrl' => $moi->avatarUrl(),
                'initiales' => $moi->initials(),
                'inscritLe' => $moi->created_at?->format('d/m/Y'),
                'employeur' => $moi->employeurDeRattachement()?->toUiArray(),
            ],

            // Le dossier administratif, tel que la RH l'a rempli.
            'dossier' => $agent ? [
                'ouvert' => true,
                'dateNaissance' => $agent->date_naissance?->format('d/m/Y'),
                'lieuNaissance' => $agent->lieu_naissance,
                'situationFamiliale' => $agent->situation_familiale,
                'enfants' => (int) $agent->enfants,
                'cni' => $agent->cni,
                'numeroCnps' => $agent->numero_cnps,
                'adresse' => $agent->adresse,
                'urgenceNom' => $agent->urgence_nom,
                'urgenceTelephone' => $agent->urgence_telephone,
                'anciennete' => $agent->anciennete(),
            ] : ['ouvert' => false],

            /*
             * Les memes donnees, brutes, pour le formulaire : la date y est
             * en format ISO, et chaque champ vaut '' plutot que null pour
             * qu'un champ vide reste un champ vide et non « null ».
             */
            'saisie' => [
                'name' => $moi->name,
                'lastname' => $moi->lastname ?? '',
                'email' => $moi->email ?? '',
                'phone' => $moi->phone ?? '',
                'date_naissance' => $agent?->date_naissance?->format('Y-m-d') ?? '',
                'lieu_naissance' => $agent?->lieu_naissance ?? '',
                'situation_familiale' => $agent?->situation_familiale ?? '',
                'enfants' => (int) ($agent?->enfants ?? 0),
                'cni' => $agent?->cni ?? '',
                'numero_cnps' => $agent?->numero_cnps ?? '',
                'adresse' => $agent?->adresse ?? '',
                'urgence_nom' => $agent?->urgence_nom ?? '',
                'urgence_telephone' => $agent?->urgence_telephone ?? '',
            ],

            'situationsFamiliales' => [
                'celibataire' => 'Célibataire',
                'marie' => 'Marié(e)',
                'divorce' => 'Divorcé(e)',
                'veuf' => 'Veuf(ve)',
            ],

            /*
             * Son badge, tel qu'il a ete demande : on le regarde ici, on ne
             * le compose pas. Le choix du logo appartient a l'ecran de
             * demande — ici, c'est une piece, pas un formulaire.
             */
            'badge' => [
                'demande' => DemandeBadge::with('institut')
                    ->where('user_id', $moi->id)->latest()->first()?->toUiArray(),
                'validite' => (int) config('badges.validite_annees'),
                'mention' => config('badges.mention'),
                'moduleOuvert' => Application::active()->where('module_key', 'badges')->exists(),
            ],

            'instituts' => $moi->applications->map(fn ($institut) => [
                'id' => $institut->id,
                'nom' => $institut->name,
                'logoUrl' => $institut->logoUrl(),
                'couleur' => $institut->color,
                'poste' => $institut->pivot->poste,
            ])->all(),

            'affectations' => $agent
                ? $agent->contrats()->with(['employeur', 'echelon.categorie', 'profil'])
                    ->orderByDesc('date_debut')->get()
                    ->map(fn (Contrat $c) => $c->toUiArray())->all()
                : [],

            'remuneration' => $this->remuneration($agent),

            'parcours' => $agent
                ? $agent->evenements()->with('contrat.employeur')
                    ->orderByDesc('date_evenement')->get()
                    ->map(fn (EvenementCarriere $e) => $e->toUiArray())->all()
                : [],

            'diplomes' => $agent
                ? $agent->diplomes()->orderByDesc('annee_obtention')->get()
                    ->map(fn (Diplome $d) => $d->toUiArray())->all()
                : [],

            'documents' => $agent
                ? $agent->documents()->latest()->get()
                    ->map(fn (DocumentAgent $d) => $d->toUiArray())->all()
                : [],

            'referentiels' => [
                'niveaux' => Diplome::NIVEAUX,
                'typesDocument' => DocumentAgent::TYPES,
            ],
        ]);
    }

    /**
     * Ma remuneration, telle que mon contrat en cours la porte.
     *
     * Rien n'est recalcule ici : on lit le profil attache au contrat. Les
     * montants reels d'un mois donne restent dans les bulletins.
     *
     * @return array<string, mixed>|null
     */
    private function remuneration(?Agent $agent): ?array
    {
        $contrat = $agent?->contrats()->where('statut', 'actif')
            ->with(['profil.indemnites', 'profil.retenues', 'echelon.categorie', 'employeur'])
            ->orderByDesc('date_debut')->first();

        if ($contrat === null) {
            return null;
        }

        $echelon = $contrat->echelonApplique();

        return [
            'employeur' => $contrat->employeur?->sigle,
            'profil' => $contrat->profil?->nom,
            'categorie' => $echelon?->categorie?->libelle,
            'echelon' => $echelon?->libelle ?: ($echelon?->numero ? 'échelon '.$echelon->numero : null),
            'salaireBase' => $contrat->salaireBase(),
            'quotite' => (int) $contrat->quotite,
            'indemnites' => $contrat->profil?->indemnites->map(fn ($i) => [
                'libelle' => $i->libelle,
                'typeCalcul' => $i->pivot->type_calcul,
                'valeur' => (float) $i->pivot->valeur,
            ])->all() ?? [],
            'retenues' => $contrat->profil?->retenues->map(fn ($r) => [
                'libelle' => $r->libelle,
                'typeCalcul' => $r->pivot->type_calcul,
                'valeur' => (float) $r->pivot->valeur,
            ])->all() ?? [],
        ];
    }

    // --------------------------------------------- ce que je corrige moi-meme

    /**
     * Je corrige mes informations.
     *
     * Tout ce qui me concerne en propre — identite, contacts, photo, dossier
     * administratif — se modifie ici : je le connais mieux que quiconque, et
     * attendre le service du personnel pour un changement de telephone n'a
     * pas de sens.
     *
     * Deux choses restent hors de ma main : le matricule et le poste. Ils ne
     * se declarent pas, ils s'attribuent — les corriger soi-meme viderait de
     * son sens tout ce qui en depend, de la paie aux bulletins.
     */
    public function mettreAJour(Request $request): RedirectResponse
    {
        $this->autoriserAcces($request->user());

        $moi = $request->user();

        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'lastname' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($moi->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

            'date_naissance' => ['nullable', 'date', 'before:today'],
            'lieu_naissance' => ['nullable', 'string', 'max:120'],
            'situation_familiale' => ['nullable', Rule::in(['celibataire', 'marie', 'divorce', 'veuf'])],
            'enfants' => ['nullable', 'integer', 'min:0', 'max:30'],
            'cni' => ['nullable', 'string', 'max:40'],
            'numero_cnps' => ['nullable', 'string', 'max:40'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'urgence_nom' => ['nullable', 'string', 'max:120'],
            'urgence_telephone' => ['nullable', 'string', 'max:40'],
        ], [
            'email.unique' => __('Cette adresse est déjà utilisée par un autre compte.'),
            'photo.max' => __('La photo ne doit pas dépasser 4 Mo.'),
        ]);

        $moi->update([
            'name' => $donnees['name'],
            'lastname' => $donnees['lastname'] ?? null,
            'email' => ($donnees['email'] ?? null) ?: null,
            'phone' => $donnees['phone'] ?? null,
            'avatar' => $request->hasFile('photo')
                ? $request->file('photo')->store('utilisateurs/photos', 'public')
                : $moi->avatar,
        ]);

        $this->monDossier($moi)->update(
            collect($donnees)->only([
                'date_naissance', 'lieu_naissance', 'situation_familiale', 'enfants',
                'cni', 'numero_cnps', 'adresse', 'urgence_nom', 'urgence_telephone',
            ])->all()
        );

        return back()->with('status', __('Vos informations sont à jour.'));
    }

    // ----------------------------------------------------- ce que je depose

    /** Declare un diplome : il attend la validation du service du personnel. */
    public function soumettreDiplome(Request $request): RedirectResponse
    {
        $this->autoriserAcces($request->user());

        $donnees = $request->validate([
            'intitule' => ['required', 'string', 'max:255'],
            'niveau' => ['nullable', Rule::in(Diplome::NIVEAUX)],
            'specialite' => ['nullable', 'string', 'max:255'],
            'etablissement' => ['nullable', 'string', 'max:255'],
            'annee_obtention' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
        ]);

        $this->monDossier($request->user())->diplomes()->create($donnees + [
            'statut' => 'en_attente',
            'soumis_par' => $request->user()->id,
            'piece_fournie' => false,
        ]);

        return back()->with('status', __('Diplôme soumis : le service du personnel le validera.'));
    }

    /** Depose une piece : elle attend elle aussi la validation. */
    public function soumettreDocument(Request $request): RedirectResponse
    {
        $this->autoriserAcces($request->user());

        $donnees = $request->validate([
            'type' => ['required', Rule::in(array_keys(DocumentAgent::TYPES))],
            'libelle' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'fichier' => [
                'required', 'file', 'max:10240',
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
            ],
        ], [
            'fichier.max' => __('Le fichier ne doit pas dépasser 10 Mo.'),
            'fichier.mimes' => __('Formats acceptés : PDF, image, Word ou Excel.'),
        ]);

        $fichier = $request->file('fichier');

        $this->monDossier($request->user())->documents()->create([
            'type' => $donnees['type'],
            'libelle' => ($donnees['libelle'] ?? null) ?: DocumentAgent::TYPES[$donnees['type']],
            'fichier' => $fichier->store('personnel/documents', DocumentAgent::DISQUE),
            'nom_origine' => $fichier->getClientOriginalName(),
            'type_mime' => $fichier->getClientMimeType(),
            'taille' => $fichier->getSize(),
            'note' => $donnees['note'] ?? null,
            'depose_par' => $request->user()->id,
            'statut' => 'en_attente',
            'soumis_par' => $request->user()->id,
        ]);

        return back()->with('status', __('Pièce déposée : le service du personnel la validera.'));
    }

    /** Je telecharge une de mes pieces, quel que soit son etat. */
    public function telechargerDocument(Request $request, DocumentAgent $document): StreamedResponse
    {
        $this->autoriserAcces($request->user());
        $this->verifierQueCestLaMienne($request, $document->agent_id);

        abort_unless($document->existe(), 404);

        return Storage::disk(DocumentAgent::DISQUE)->download($document->fichier, $document->nom_origine);
    }

    /**
     * Je retire ce que j'ai depose, tant que la RH ne l'a pas tranche.
     *
     * Une fois valide, la piece appartient au dossier : seul le service du
     * personnel peut l'en retirer.
     */
    public function retirerDocument(Request $request, DocumentAgent $document): RedirectResponse
    {
        $this->autoriserAcces($request->user());
        $this->verifierQueCestLaMienne($request, $document->agent_id);

        abort_unless($document->soumis_par === $request->user()->id && $document->statut === 'en_attente', 403);

        Storage::disk(DocumentAgent::DISQUE)->delete($document->fichier);
        $document->delete();

        return back()->with('status', __('Pièce retirée.'));
    }

    public function retirerDiplome(Request $request, Diplome $diplome): RedirectResponse
    {
        $this->autoriserAcces($request->user());
        $this->verifierQueCestLaMienne($request, $diplome->agent_id);

        abort_unless($diplome->soumis_par === $request->user()->id && $diplome->statut === 'en_attente', 403);

        $diplome->delete();

        return back()->with('status', __('Diplôme retiré.'));
    }

    /** Le dossier nait au premier depot, s'il n'existait pas encore. */
    private function monDossier(User $moi): Agent
    {
        return Agent::firstOrCreate(['user_id' => $moi->id]);
    }

    private function verifierQueCestLaMienne(Request $request, ?int $agentId): void
    {
        abort_unless($agentId !== null && $request->user()->agent?->id === $agentId, 403);
    }
}
