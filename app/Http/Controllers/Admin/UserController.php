<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesMediaUploads;
use App\Http\Controllers\Controller;
use App\Mail\Compte\CompteValide;
use App\Mail\Compte\DemandeRefusee;
use App\Models\Application;
use App\Models\User;
use App\Services\AttributionMatricules;
use App\Services\BulletinPdf;
use App\Services\CourrielsPortail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UserController extends Controller
{
    use HandlesMediaUploads;

    public function index(Request $request): Response
    {
        $ordre = $request->query('ordre') === 'asc' ? 'asc' : 'desc';

        $users = User::withCount('applications')
            ->with(['applications' => fn ($q) => $q->orderBy('name')])
            ->when($request->query('q'), function ($q, $term) {
                $q->where(fn ($sub) => $sub->where('name', 'like', "%{$term}%")
                    ->orWhere('lastname', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('matricule', 'like', "%{$term}%"));
            })
            ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            // Isoler ceux qui attendent un matricule : on les coche ensuite
            // tous d'un coup.
            ->when($request->boolean('sans_matricule'), fn ($q) => $q->whereNull('matricule'))
            // Le personnel d'un institut : les comptes ayant acces a son application.
            ->when($request->query('application'), fn ($q, $slug) => $q->whereHas(
                'applications', fn ($sub) => $sub->where('slug', $slug)
            ))
            // Les demandes en attente remontent en tete. CASE plutot que
            // FIELD() : la premiere forme fonctionne aussi sous SQLite.
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            // Puis par date d'inscription : les derniers arrives d'abord, ou
            // l'ordre d'arrivee si on demande l'inverse.
            ->orderBy('created_at', $ordre)
            ->orderBy('id', $ordre)
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($user) => $user->toUiArray());

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'pendingCount' => User::pending()->count(),
            'sansMatriculeCount' => User::whereNull('matricule')->count(),
            'prochainMatricule' => app(AttributionMatricules::class)->prochain(),
            'institutions' => Application::where('type', 'application')->whereNotNull('client_id')
                ->orderBy('name')->get()
                ->map(fn ($a) => ['slug' => $a->slug, 'name' => $a->name])->all(),
            'filters' => [
                'q' => $request->query('q'),
                'role' => $request->query('role'),
                'status' => $request->query('status'),
                'application' => $request->query('application'),
                'ordre' => $ordre,
                'sansMatricule' => $request->boolean('sans_matricule'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/form', [
            'user' => null,
            'applications' => $this->applications(),
            'assigned' => [],
            'postes' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $acces = $this->accessPayload($request);

        $user = User::create($data);
        $user->applications()->sync($acces);

        return redirect()->route('admin.users.index')
            ->with('status', "Le compte de {$user->fullName()} a été créé.");
    }

    public function edit(User $user): Response
    {
        $user->load('applications');

        return Inertia::render('admin/users/form', [
            'user' => $user->toUiArray(),
            'applications' => $this->applications(),
            'assigned' => $user->applications->mapWithKeys(
                fn ($application) => [$application->id => Application::pivotRoles($application->pivot)]
            )->all(),
            'postes' => $user->applications->mapWithKeys(
                fn ($application) => [$application->id => $application->pivot->poste]
            )->all(),
        ]);
    }

    public function update(Request $request, User $user, CourrielsPortail $courriels): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $acces = $this->accessPayload($request, $user);

        /*
         * Passer un compte a « actif » depuis sa fiche vaut validation : la
         * personne doit etre prevenue comme si l'on avait repondu a sa
         * demande depuis la liste. Sans cela, un compte active par cet
         * ecran s'ouvrait sans que son titulaire l'apprenne.
         */
        $ouverture = ($data['status'] ?? $user->status) === 'active' && $user->status !== 'active';

        $user->update($data);
        $user->applications()->sync($acces);

        $fait = "Le compte de {$user->fullName()} a été mis à jour.";

        if (! $ouverture) {
            return back()->with('status', $fait);
        }

        $courriel = $courriels->pourCompte($user);
        $parti = $courriel !== null && $courriels->envoyerA($user, $courriel);

        return $this->rendreCompte(
            $fait,
            $parti ? __('Le message d’ouverture est parti à :adresse.', ['adresse' => $user->email]) : null,
            $courriels->dernierEchec(),
        );
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }

        /*
         * Un compte qui a des bulletins ne se supprime pas : ce sont des
         * pieces de paie, et la base ne garantit pas le menage — les cles
         * etrangeres ne sont pas appliquees partout sur l'hebergement, si
         * bien qu'un compte efface laissait derriere lui un dossier et des
         * bulletins sans titulaire, impossibles a rattacher.
         */
        $dossier = $user->agent;

        if ($dossier && $dossier->bulletins()->exists()) {
            return back()->withErrors([
                'user' => __('Ce compte a des bulletins de paie : suspendez-le plutôt que de le supprimer, sinon ses bulletins resteraient sans titulaire.'),
            ]);
        }

        $name = $user->fullName();
        $this->deleteUploaded($user->avatar);

        // Le dossier part avec le compte : on ne compte pas sur la base.
        $dossier?->delete();
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "Le compte de {$name} a été supprimé.");
    }

    /**
     * Valide une demande d'inscription : le compte devient utilisable avec les
     * instituts qu'il avait demandes.
     */
    public function approve(User $user, CourrielsPortail $courriels): RedirectResponse
    {
        if (! $user->isPending()) {
            return back()->with('status', __("Ce compte n'est pas en attente de validation."));
        }

        $user->approve();

        $parti = $courriels->envoyerA($user, new CompteValide(
            $user->fullName(),
            $user->matricule,
            $user->applications()->pluck('name')->all(),
        ));

        return $this->rendreCompte(
            __('Le compte de :nom a été validé.', ['nom' => $user->fullName()]),
            $parti ? __('Le message d’ouverture est parti à :adresse.', ['adresse' => $user->email]) : null,
            $courriels->dernierEchec(),
        );
    }

    /**
     * Refuse une demande : le compte est suspendu et ses acces retires. Il
     * reste visible dans la liste, l'administrateur peut le supprimer ensuite.
     */
    public function reject(User $user, CourrielsPortail $courriels): RedirectResponse
    {
        if (! $user->isPending()) {
            return back()->with('status', __("Ce compte n'est pas en attente de validation."));
        }

        $user->forceFill(['status' => 'suspended'])->save();
        $user->applications()->detach();

        $parti = $courriels->envoyerA($user, new DemandeRefusee($user->fullName()));

        return $this->rendreCompte(
            __('La demande de :nom a été refusée.', ['nom' => $user->fullName()]),
            $parti ? __('Le message est parti à :adresse.', ['adresse' => $user->email]) : null,
            $courriels->dernierEchec(),
        );
    }

    /**
     * La procedure a abouti ; le courriel, peut-etre pas.
     *
     * Les deux sont dits ensemble : l'action reussie en message d'etat, et le
     * courriel manquant en avertissement. Sans cela, l'administrateur croit
     * que la personne a ete prevenue alors qu'elle n'a rien recu.
     */
    private function rendreCompte(string $fait, ?string $envoi, ?string $echec): RedirectResponse
    {
        $retour = back()->with('status', trim($fait.' '.($envoi ?? '')));

        return $echec === null ? $retour : $retour->withErrors(['courriel' => $echec]);
    }

    /**
     * Attribue les matricules aux comptes coches.
     *
     * Sans `remplacer`, ceux qui en portent deja un sont laisses tels quels.
     * Avec, ils sont renumerotes : c'est une decision qui se prend a
     * l'ecran, case cochee, et le message rappelle ce qui a change.
     */
    public function attribuerMatricules(Request $request, AttributionMatricules $attribution): RedirectResponse
    {
        $donnees = $request->validate([
            'users' => ['required', 'array', 'min:1'],
            'users.*' => ['integer', 'exists:users,id'],
            'remplacer' => ['boolean'],
        ]);

        $attribues = $attribution->attribuer($donnees['users'], $request->boolean('remplacer'));

        if ($attribues === []) {
            return back()->withErrors([
                'matricules' => __('Aucun de ces comptes n’attend un matricule.'),
            ]);
        }

        $remplaces = collect($attribues)->filter(fn ($a) => filled($a['ancien']))->count();

        $message = trans_choice(
            '{1}Un matricule attribué : :premier.|[2,*]:nombre matricules attribués, de :premier à :dernier.',
            count($attribues),
            [
                'nombre' => count($attribues),
                'premier' => $attribues[0]['matricule'],
                'dernier' => end($attribues)['matricule'],
            ],
        );

        if ($remplaces > 0) {
            $message .= ' '.trans_choice(
                '{1}Un ancien numéro a été remplacé ; il ne sera réattribué à personne.'
                .'|[2,*]:nombre anciens numéros ont été remplacés ; ils ne seront réattribués à personne.',
                $remplaces,
                ['nombre' => $remplaces],
            );
        }

        return back()->with('status', $message);
    }

    /**
     * Renvoie a la personne le message qui correspond a l'etat de son
     * compte. Utile quand une adresse etait fausse, ou le relais en panne.
     */
    public function renvoyerCourriel(User $user, CourrielsPortail $courriels): RedirectResponse
    {
        if (blank($user->email)) {
            return back()->withErrors([
                'courriel' => __(':nom n’a pas d’adresse e-mail.', ['nom' => $user->fullName()]),
            ]);
        }

        $courriel = $courriels->pourCompte($user);

        if (! $courriel || ! $courriels->envoyerA($user, $courriel)) {
            return back()->withErrors([
                'courriel' => $courriels->dernierEchec()
                    ?? __('L’envoi a échoué. Vérifiez les réglages e-mail.'),
            ]);
        }

        return back()->with('status', __('Message renvoyé à :adresse.', ['adresse' => $user->email]));
    }

    /**
     * La liste du personnel en PDF : nom, prenom, matricule.
     *
     * C'est la liste qu'on imprime pour un appel, un emargement ou une
     * transmission. L'export CSV du module RH, lui, porte tout le detail :
     * ici on ne veut que les noms.
     *
     * `?apercu=1` la sert en ligne pour la previsualiser.
     */
    public function listePdf(Request $request, BulletinPdf $pdf): SymfonyResponse|RedirectResponse
    {
        return $this->sansPageBlanche($request, fn () => $this->fabriquerLaListe($request, $pdf));
    }

    private function fabriquerLaListe(Request $request, BulletinPdf $pdf): SymfonyResponse
    {
        // `?format=html` sert la meme liste dans le navigateur, qui
        // l'imprime ou l'enregistre en PDF lui-meme. C'est un chemin de
        // secours : il ne depend pas du moteur PDF du serveur.
        $navigateur = $request->query('format') === 'html';

        $personnel = User::duPersonnel()
            ->orderByRaw('LOWER(COALESCE(lastname, name)) ASC')
            ->orderByRaw('LOWER(name) ASC')
            ->get(['id', 'name', 'lastname', 'matricule']);

        $donnees = [
            'personnel' => $personnel,
            'editeLe' => now()->translatedFormat('j F Y'),
            'couleur' => '#0f766e',
            'logo' => $pdf->logoDuGroupe(),
        ];

        if ($navigateur) {
            return response()->view('pdf.liste-personnel', $donnees + ['navigateur' => true]);
        }

        $document = Pdf::setOptions($pdf->optionsDocument())
            ->loadView('pdf.liste-personnel', $donnees)
            ->setPaper('a4');

        $nom = 'personnel-la-majestueuse-'.now()->format('Y-m-d').'.pdf';

        return $request->boolean('apercu') ? $document->stream($nom) : $document->download($nom);
    }

    /**
     * Enveloppe la fabrication d'un PDF.
     *
     * Une panne du moteur rendait une page blanche d'erreur serveur, qui
     * n'apprend rien a personne. On journalise la cause et on la ramene a
     * l'ecran : l'administrateur voit le detail technique, les autres un
     * message clair. Sans cela, diagnostiquer demande l'acces aux journaux
     * du serveur.
     */
    private function sansPageBlanche(Request $request, callable $fabrique): SymfonyResponse|RedirectResponse
    {
        try {
            return $fabrique();
        } catch (\Throwable $erreur) {
            Log::error('Document PDF impossible à produire', [
                'erreur' => $erreur::class,
                'message' => $erreur->getMessage(),
                'fichier' => $erreur->getFile().':'.$erreur->getLine(),
            ]);

            return back()->withErrors([
                'pdf' => $request->user()?->isAdmin()
                    ? __('Le document n’a pas pu être produit : :detail', [
                        'detail' => $erreur::class.' — '.$erreur->getMessage(),
                    ])
                    : __('Le document n’a pas pu être produit. Signalez-le à l’administration du portail.'),
            ]);
        }
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'lastname' => ['nullable', 'string', 'max:80'],
            'matricule' => ['nullable', 'string', 'max:40', Rule::unique('users', 'matricule')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'poste' => ['nullable', 'string', 'max:120'],
            'entite' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::in(['admin', 'manager', 'employee'])],
            // Un administrateur technique entre dans le portail sans figurer
            // dans les dossiers du personnel.
            'dans_le_personnel' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'suspended', 'pending'])],
            'locale' => ['required', Rule::in(['fr', 'en'])],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'avatar_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        unset($data['avatar_file']);
        $data['avatar'] = $this->resolveMedia($request, $user?->avatar, 'avatar', 'utilisateurs/photos');

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function applications(): array
    {
        return Application::active()->orderBy('name')->get()->map->toUiArray()->all();
    }

    /**
     * Construit la table des acces a synchroniser. Le poste occupe dans chaque
     * institut est conserve : il vient du formulaire d'inscription et ne doit
     * pas etre efface par une simple modification des acces.
     *
     * @return array<int, array<string, mixed>>
     */
    private function accessPayload(Request $request, ?User $user = null): array
    {
        $postes = $user
            ? $user->applications()->pluck('application_user.poste', 'applications.id')->all()
            : [];

        $ids = array_map('intval', (array) $request->input('applications', []));
        $applications = Application::whereIn('id', $ids)->get()->keyBy('id');

        $sync = [];

        foreach ($ids as $id) {
            $application = $applications->get($id);

            if (! $application) {
                continue;
            }

            $roles = Application::normalizeRoleInput($request->input("roles.{$id}", []));
            $codes = $application->roleCodes();

            foreach ($roles as $role) {
                if (! is_string($role) || mb_strlen($role) > 60 || ($codes && ! in_array($role, $codes, true))) {
                    throw ValidationException::withMessages([
                        "roles.{$id}" => __("Ce rôle n'est pas reconnu par :app.", ['app' => $application->name]),
                    ]);
                }
            }

            $sync[$id] = Application::rolesAttributes($application->sortRoles($roles)) + [
                'poste' => $postes[$id] ?? null,
            ];
        }

        return $sync;
    }
}
