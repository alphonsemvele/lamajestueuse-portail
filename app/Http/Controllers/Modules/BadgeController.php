<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Concerns\HandlesMediaUploads;
use App\Http\Controllers\Concerns\ServesModule;
use App\Http\Controllers\Controller;
use App\Mail\Badge\BadgePret;
use App\Mail\Badge\BadgeRefuse;
use App\Mail\Badge\BadgeValide;
use App\Mail\Badge\DemandeEnregistree;
use App\Mail\Badge\DemandeRouverte;
use App\Models\Application;
use App\Models\DemandeBadge;
use App\Models\User;
use App\Services\CourrielsPortail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

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
            // Le verso de la carte la porte : elle vient d'un seul endroit.
            'mention' => config('badges.mention'),
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

        // Le groupe est toujours propose a cote des instituts : on peut
        // porter les couleurs de la maison plutot que celles d'une ecole.
        $choix = [...$instituts, DemandeBadge::LOGO_GROUPE];

        $donnees = $request->validate([
            'nom_affiche' => ['required', 'string', 'max:80'],
            'poste_affiche' => ['nullable', 'string', 'max:120'],
            // Le formulaire ne propose plus de choix : un seul modele existe.
            'modele' => ['nullable', Rule::in(array_keys(config('badges.modeles')))],
            'motif' => ['required', Rule::in(array_keys(DemandeBadge::MOTIFS))],
            'application_id' => [
                // Obligatoire des que la personne sert au moins un institut :
                // c'est ce choix qui decide du logo imprime. Sans aucun
                // rattachement, le groupe va de soi et le champ peut rester
                // vide.
                $instituts === [] ? 'nullable' : 'required',
                Rule::in($choix),
            ],
            'commentaire' => ['nullable', 'string', 'max:500'],
            'photo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'application_id.required' => __('Choisissez le logo qui figurera sur votre badge.'),
            'application_id.in' => __('Vous n’êtes pas rattaché à cet institut.'),
        ]);

        $demande = $this->deposer($request, $utilisateur, $donnees);

        return back()->with('status', __('Demande :numero enregistrée.', ['numero' => $demande->numero]));
    }

    /**
     * Le guichet depose une demande pour un employe.
     *
     * Celui qui n'a pas de compte, celui qui ne s'y retrouve pas, celui qu'on
     * inscrit au comptoir : sa demande n'a pas a attendre qu'il la fasse
     * lui-meme. Elle suit ensuite le meme circuit que les autres, et porte le
     * nom de qui l'a deposee.
     */
    public function storePour(Request $request): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $personne = User::find($request->integer('user_id'));

        if ($personne === null) {
            return back()->withErrors([
                'user_id' => __('Choisissez la personne pour qui déposer la demande.'),
            ]);
        }

        if (DemandeBadge::where('user_id', $personne->id)->enCours()->exists()) {
            return back()->withErrors([
                'badge' => __(':nom a déjà une demande en cours : traitez-la ou annulez-la d’abord.', [
                    'nom' => $personne->fullName(),
                ]),
            ]);
        }

        // Le logo doit rester un institut que cette personne sert — ou celui
        // du groupe. Le guichet depose a sa place, il ne la rattache pas.
        $instituts = collect($this->institutsDe($personne))->pluck('id')->all();

        $donnees = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'nom_affiche' => ['required', 'string', 'max:80'],
            'poste_affiche' => ['nullable', 'string', 'max:120'],
            'motif' => ['required', Rule::in(array_keys(DemandeBadge::MOTIFS))],
            'application_id' => [
                $instituts === [] ? 'nullable' : 'required',
                Rule::in([...$instituts, DemandeBadge::LOGO_GROUPE]),
            ],
            'commentaire' => ['nullable', 'string', 'max:500'],
            'photo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'application_id.required' => __('Choisissez le logo qui figurera sur le badge.'),
            'application_id.in' => __(':nom n’est pas rattaché à cet institut.', ['nom' => $personne->fullName()]),
        ]);

        unset($donnees['user_id']);

        $demande = $this->deposer($request, $personne, $donnees, $request->user());

        return back()->with('status', __('Demande :numero déposée pour :nom.', [
            'numero' => $demande->numero,
            'nom' => $personne->fullName(),
        ]));
    }

    /**
     * Enregistre la demande et previent son titulaire.
     *
     * La photo jointe est facultative : sans elle, le badge reprend celle du
     * compte. Et c'est bien le titulaire qu'on previent, jamais celui qui a
     * depose a sa place.
     *
     * @param  array<string, mixed>  $donnees
     */
    private function deposer(Request $request, User $pour, array $donnees, ?User $par = null): DemandeBadge
    {
        if (($donnees['application_id'] ?? null) === DemandeBadge::LOGO_GROUPE) {
            $donnees['application_id'] = null;
        }

        $photo = $this->resolveMedia($request, null, 'photo', 'badges/photos');
        unset($donnees['photo_file']);

        $demande = DemandeBadge::create($donnees + [
            'modele' => 'classique',
            'numero' => DemandeBadge::prochainNumero(),
            'user_id' => $pour->id,
            'depose_par' => $par?->id,
            'photo' => $photo,
            'statut' => 'en_attente',
        ]);

        app(CourrielsPortail::class)->envoyerA($pour, new DemandeEnregistree(
            $pour->fullName(),
            $demande->numero,
            $demande->nom_affiche,
            $demande->logoLibelle(),
        ));

        return $demande;
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
            // « groupe » n'est pas un identifiant : ce sont les badges sans
            // institut, ceux qui portent le logo de la maison.
            ->when($institut === DemandeBadge::LOGO_GROUPE, fn ($q) => $q->whereNull('application_id'))
            ->when($institut && $institut !== DemandeBadge::LOGO_GROUPE,
                fn ($q) => $q->where('application_id', $institut))
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
            'motifs' => DemandeBadge::MOTIFS,
            /*
             * Le personnel, pour deposer une demande a la place de quelqu'un.
             * Chacun vient avec ses instituts : le guichet choisit un logo
             * que cette personne sert, pas n'importe lequel.
             */
            'personnel' => User::duPersonnel()
                ->with(['applications' => fn ($q) => $q->where('applications.type', 'application')
                    ->where('applications.is_active', true)])
                ->orderByRaw('LOWER(COALESCE(lastname, name)) ASC')
                ->get()
                ->map(fn (User $personne) => [
                    'id' => $personne->id,
                    'nom' => $personne->fullName(),
                    'matricule' => $personne->matricule,
                    'poste' => $personne->poste,
                    'instituts' => $personne->applications
                        ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->all(),
                ])->all(),
            'compteurs' => DemandeBadge::selectRaw('statut, count(*) as total')
                ->groupBy('statut')->pluck('total', 'statut')->all(),
            'validite' => (int) config('badges.validite_annees'),
            'mention' => config('badges.mention'),
        ]);
    }

    /**
     * Le guichet corrige une demande : un nom mal saisi, un institut qui
     * n'est pas le bon, une photo inexploitable.
     *
     * C'est le geste qui evite un refus pour une faute de frappe. Une
     * demande remise ou refusee, elle, est close : on ne la retouche plus.
     */
    public function modifier(Request $request, DemandeBadge $demande): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        if ($demande->estFigee()) {
            return back()->withErrors([
                'badge' => __('Cette demande est close : elle ne se modifie plus.'),
            ]);
        }

        // Le guichet n'est pas tenu par les rattachements du demandeur : il
        // corrige, et peut donc designer n'importe quel institut en service.
        $instituts = Application::active()->where('type', 'application')->pluck('id')->all();

        $donnees = $request->validate([
            'nom_affiche' => ['required', 'string', 'max:80'],
            'poste_affiche' => ['nullable', 'string', 'max:120'],
            'application_id' => ['nullable', Rule::in([...$instituts, DemandeBadge::LOGO_GROUPE])],
            'photo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'application_id.in' => __('Cet institut n’est pas en service.'),
        ]);

        if (($donnees['application_id'] ?? null) === DemandeBadge::LOGO_GROUPE) {
            $donnees['application_id'] = null;
        }

        $photo = $this->resolveMedia($request, $demande->photo, 'photo', 'badges/photos');
        unset($donnees['photo_file']);

        $demande->update($donnees + ['photo' => $photo]);

        return back()->with('status', __('Demande :numero mise à jour.', ['numero' => $demande->numero]));
    }

    /**
     * Revenir sur un refus.
     *
     * Un clic malheureux, un motif qui ne tenait pas : la demande repart a
     * l'etude plutot que d'obliger le demandeur a tout refaire. C'est la
     * seule porte qui rouvre une demande close, et elle ne s'ouvre que sur
     * un refus — un badge remis, lui, est une affaire terminee.
     */
    public function rouvrir(Request $request, DemandeBadge $demande): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        if ($demande->statut !== 'refusee') {
            return back()->withErrors([
                'badge' => __('Seule une demande refusée se rouvre.'),
            ]);
        }

        /*
         * Le demandeur a pu redeposer entre-temps, comme le refus l'y
         * invitait : rouvrir lui ferait deux demandes en cours, et la regle
         * du portail n'en admet qu'une.
         */
        $autre = DemandeBadge::where('user_id', $demande->user_id)
            ->whereKeyNot($demande->id)->enCours()->first();

        if ($autre) {
            return back()->withErrors([
                'badge' => __('Impossible : :nom a déjà déposé la demande :numero, qui est en cours.', [
                    'nom' => $demande->user?->fullName() ?? $demande->nom_affiche,
                    'numero' => $autre->numero,
                ]),
            ]);
        }

        $demande->update([
            'statut' => 'en_attente',
            'motif_refus' => null,
            'traite_par' => $request->user()->id,
            'traite_le' => now(),
        ]);

        // Il avait recu un refus et l'invitation a recommencer : sans ce
        // message, il depose une seconde demande pour rien.
        $demande->loadMissing('user');
        app(CourrielsPortail::class)->envoyerA($demande->user, new DemandeRouverte(
            $demande->user?->fullName() ?? $demande->nom_affiche,
            $demande->numero,
        ));

        return back()->with('status', __('Demande :numero rouverte : elle attend de nouveau d’être traitée.', [
            'numero' => $demande->numero,
        ]));
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

        // Deux clics sur le meme bouton ne traitent pas deux fois.
        if ($donnees['statut'] === $demande->statut) {
            return back();
        }

        /*
         * Les etapes se suivent, et une demande close le reste : sans cette
         * garde, un refus se defaisait en remettant la demande a « approuvee ».
         */
        if (! $demande->peutPasserA($donnees['statut'])) {
            return back()->withErrors(['badge' => $demande->estFigee()
                ? __('La demande :numero est close (:etat) : elle ne se rouvre pas. Le demandeur peut en déposer une nouvelle.', [
                    'numero' => $demande->numero,
                    'etat' => mb_strtolower(DemandeBadge::STATUTS[$demande->statut]),
                ])
                : __('Une demande « :etat » ne peut pas passer à « :vers ».', [
                    'etat' => DemandeBadge::STATUTS[$demande->statut],
                    'vers' => DemandeBadge::STATUTS[$donnees['statut']],
                ]),
            ]);
        }

        $demande->update([
            'statut' => $donnees['statut'],
            'motif_refus' => $donnees['statut'] === 'refusee' ? $donnees['motif_refus'] : null,
            'traite_par' => $request->user()->id,
            'traite_le' => now(),
        ]);

        $this->prevenirLeDemandeur($demande);

        return back()->with('status', __('Demande :numero : :statut.', [
            'numero' => $demande->numero,
            'statut' => mb_strtolower(DemandeBadge::STATUTS[$demande->statut]),
        ]));
    }

    /**
     * Previent le demandeur a chaque etape qui le concerne : sa demande
     * validee, son badge pret a retirer, ou son refus. L'impression, elle,
     * ne regarde que le guichet.
     */
    private function prevenirLeDemandeur(DemandeBadge $demande): void
    {
        $courriels = app(CourrielsPortail::class);
        $demande->loadMissing(['user', 'institut']);
        $nom = $demande->user?->fullName() ?? $demande->nom_affiche;

        if ($demande->statut === 'approuvee') {
            $courriels->envoyerA($demande->user, new BadgeValide($nom, $demande->numero, $demande->logoLibelle()));
        }

        if ($demande->statut === 'imprimee') {
            $courriels->envoyerA($demande->user, new BadgePret($nom, $demande->numero, $demande->logoLibelle()));
        }

        if ($demande->statut === 'refusee') {
            $courriels->envoyerA($demande->user, new BadgeRefuse($nom, $demande->numero, $demande->motif_refus));
        }
    }

    /** Renvoie au demandeur le message correspondant a l'etat de sa demande. */
    public function renvoyerCourriel(Request $request, DemandeBadge $demande, CourrielsPortail $courriels): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $demande->loadMissing('user');

        if (blank($demande->user?->email)) {
            return back()->withErrors([
                'courriel' => __('Ce demandeur n’a pas d’adresse e-mail.'),
            ]);
        }

        $courriel = $courriels->pourBadge($demande);

        if (! $courriel || ! $courriels->envoyerA($demande->user, $courriel)) {
            return back()->withErrors([
                'courriel' => __('L’envoi a échoué. Vérifiez les réglages e-mail.'),
            ]);
        }

        return back()->with('status', __('Message renvoyé à :adresse.', ['adresse' => $demande->user->email]));
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

    /**
     * Les photos des badges, en un seul fichier ZIP.
     *
     * Le fabricant travaille souvent hors du portail : il lui faut les
     * portraits, nommes de facon a retrouver chaque personne sans ouvrir les
     * fichiers un par un.
     */
    public function photos(Request $request): StreamedResponse
    {
        $this->autoriserGestion($request->user());

        $choisies = array_filter(explode(',', (string) $request->query('demandes')));

        $demandes = DemandeBadge::with(['user', 'institut'])
            ->when($choisies !== [], fn ($q) => $q->whereIn('id', $choisies))
            ->when($choisies === [], fn ($q) => $q->whereIn('statut', ['approuvee', 'imprimee']))
            ->orderBy('numero')->get();

        $archive = tempnam(sys_get_temp_dir(), 'badges');
        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::OVERWRITE | ZipArchive::CREATE);

        $manquantes = [];

        foreach ($demandes as $demande) {
            $chemin = $this->cheminPhoto($demande);

            if ($chemin === null) {
                $manquantes[] = $demande->numero.' — '.$demande->nom_affiche;

                continue;
            }

            // Un nom lisible : matricule, nom affiche, numero de demande.
            $etiquette = trim(implode(' - ', array_filter([
                $demande->user?->matricule,
                Str::ascii($demande->nom_affiche),
                $demande->numero,
            ])));

            $zip->addFile($chemin, Str::slug($etiquette).'.'.pathinfo($chemin, PATHINFO_EXTENSION));
        }

        // Ceux dont la photo manque sont signales dans l'archive elle-meme,
        // sans quoi leur absence passerait inapercue.
        if ($manquantes !== []) {
            $zip->addFromString(
                'PHOTOS-MANQUANTES.txt',
                "Ces demandes n'ont pas de photo exploitable :\n\n".implode("\n", $manquantes)."\n"
            );
        }

        $zip->close();

        $nom = 'photos-badges-'.now()->format('Y-m-d').'.zip';

        return response()->streamDownload(function () use ($archive) {
            readfile($archive);
            @unlink($archive);
        }, $nom, ['Content-Type' => 'application/zip']);
    }

    /**
     * Fichier de la photo a joindre : celle du badge, sinon celle du compte
     * si elle a ete televersee. Une URL externe n'est pas rapatriee.
     */
    private function cheminPhoto(DemandeBadge $demande): ?string
    {
        foreach ([$demande->photo, $demande->user?->avatar] as $chemin) {
            if (blank($chemin) || str_starts_with((string) $chemin, 'http')) {
                continue;
            }

            if (Storage::disk('public')->exists($chemin)) {
                return Storage::disk('public')->path($chemin);
            }
        }

        return null;
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
}
