<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Category;
use App\Http\Controllers\Concerns\HandlesMediaUploads;
use App\Models\User;
use App\Services\ApplicationDirectorySync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ApplicationController extends Controller
{
    use HandlesMediaUploads;

    public function index(Request $request): Response
    {
        $applications = Application::with('category')
            ->withCount('users')
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($application) => $application->toUiArray());

        return Inertia::render('admin/applications/index', [
            'applications' => $applications,
            'filters' => ['q' => $request->query('q'), 'type' => $request->query('type')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/applications/form', [
            'application' => null,
            'categories' => $this->categories(),
            'modules' => $this->modules(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $application = Application::create($data);

        return redirect()->route('admin.applications.index')
            ->with('status', "L'application « {$application->name} » a été créée.");
    }

    public function edit(Application $application): Response
    {
        return Inertia::render('admin/applications/form', [
            'application' => $application->toUiArray(),
            'categories' => $this->categories(),
            'modules' => $this->modules(),
        ]);
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $application->update($this->validated($request, $application));

        return redirect()->route('admin.applications.index')
            ->with('status', "L'application « {$application->name} » a été mise à jour.");
    }

    public function destroy(Application $application): RedirectResponse
    {
        $name = $application->name;
        $this->deleteUploaded($application->cover);
        $this->deleteUploaded($application->logo);
        $application->delete();

        return redirect()->route('admin.applications.index')
            ->with('status', "L'application « {$name} » a été supprimée.");
    }

    /**
     * Ecran d'affectation : qui a acces a cette application, et avec quels roles.
     */
    public function access(Application $application): Response
    {
        $application->load('users');

        return Inertia::render('admin/applications/access', [
            'application' => $application->toUiArray(),
            'users' => User::orderBy('name')->orderBy('lastname')->get()->map->toUiArray()->all(),
            'granted' => $application->users->mapWithKeys(
                fn ($user) => [$user->id => Application::pivotRoles($user->pivot)]
            )->all(),
            'references' => $application->users->mapWithKeys(
                fn ($user) => [$user->id => $user->pivot->reference_locale]
            )->all(),
            'raccordee' => $application->usesPortalSignOn(),
        ]);
    }

    public function updateAccess(Request $request, Application $application): RedirectResponse
    {
        $request->merge([
            'roles' => collect((array) $request->input('roles', []))
                ->map(fn ($roles) => Application::normalizeRoleInput($roles))
                ->all(),
        ]);

        $data = $request->validate([
            'users' => ['array'],
            'users.*' => ['integer', 'exists:users,id'],
            'roles' => ['array'],
            'roles.*' => ['array'],
            'roles.*.*' => ['string', 'max:60'],
            'references' => ['array'],
            'references.*' => ['nullable', 'string', 'max:120'],
        ]);

        // Un role affecte doit figurer parmi ceux que l'application declare.
        if ($codes = $application->roleCodes()) {
            $request->validate([
                'roles.*.*' => [Rule::in($codes)],
            ], [
                'roles.*.*.in' => __("Ce rôle n'est pas reconnu par :app.", ['app' => $application->name]),
            ]);
        }

        // On conserve le poste declare a l'inscription pour chaque employe.
        $postes = $application->users()->pluck('application_user.poste', 'users.id')->all();

        $sync = [];
        foreach ($data['users'] ?? [] as $userId) {
            $sync[$userId] = Application::rolesAttributes($application->sortRoles($data['roles'][$userId] ?? [])) + [
                'poste' => $postes[$userId] ?? null,
                'reference_locale' => $data['references'][$userId] ?? null,
            ];
        }

        $application->users()->sync($sync);

        return back()->with('status', 'Les accès ont été enregistrés.');
    }

    /**
     * Recupere aupres de l'application son catalogue de roles et son personnel.
     */
    public function synchronize(Application $application, ApplicationDirectorySync $sync): RedirectResponse
    {
        try {
            $resultat = $sync->pull($application);
        } catch (RuntimeException $e) {
            return back()->withErrors(['synchronisation' => $e->getMessage()]);
        }

        return back()->with('status', __('Synchronisation terminée : :roles rôle(s) reçu(s), :crees compte(s) créé(s), :rattaches rattaché(s) à un compte existant, :deja déjà lié(s).', [
            'roles' => $resultat['roles'],
            'crees' => $resultat['crees'],
            'rattaches' => $resultat['rattaches'],
            'deja' => $resultat['deja_lies'],
        ]));
    }

    /**
     * Les modules internes disponibles, declares dans config/modules.php.
     *
     * @return array<int, array<string, mixed>>
     */
    private function modules(): array
    {
        return collect(config('modules'))
            ->map(fn (array $module, string $key) => [
                'key' => $key,
                'name' => $module['name'],
                'description' => $module['description'] ?? null,
                'roles' => $module['manage_roles'] ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categories(): array
    {
        return Category::orderBy('sort_order')->get()->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'color' => $c->color,
        ])->all();
    }

    private function validated(Request $request, ?Application $application = null): array
    {
        // On deduit le slug AVANT la validation : sinon un slug laisse vide
        // dont la version derivee du nom existe deja passe la validation et
        // casse sur la contrainte d'unicite de la base.
        $request->merge([
            'slug' => Str::slug($request->input('slug') ?: (string) $request->input('name')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'max:120', 'alpha_dash',
                Rule::unique('applications', 'slug')->ignore($application?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            // Le lien de redirection vers lequel le portail envoie l'employe.
            // Facultatif : un module est servi par une route du portail, et une
            // application pas encore en ligne s'annonce « bientot disponible ».
            'url' => ['nullable', 'url', 'max:255'],
            'module_key' => [
                'nullable', 'required_if:type,module',
                Rule::in(array_keys(config('modules'))),
            ],
            'category_id' => ['nullable', 'exists:categories,id'],
            'type' => ['required', Rule::in(['application', 'quick_link', 'module'])],
            'cover' => ['nullable', 'string', 'max:255'],
            // Le SVG est volontairement exclu : servi depuis notre propre
            // domaine, il peut embarquer du script.
            'cover_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'logo' => ['nullable', 'string', 'max:255'],
            'logo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'icon' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'client_id' => [
                'nullable', 'string', 'max:120',
                Rule::unique('applications', 'client_id')->ignore($application?->id),
            ],
            'client_secret' => ['nullable', 'string', 'min:32', 'max:255'],
            'roles' => ['nullable', 'string', 'max:600'],
            'regenerer_secret' => ['nullable', 'boolean'],
        ]);

        unset($data['cover_file'], $data['logo_file']);

        $data['cover'] = $this->resolveMedia($request, $application?->cover, 'cover', 'applications/couvertures');
        $data['logo'] = $this->resolveMedia($request, $application?->logo, 'logo', 'applications/logos');

        // Les deux champs s'excluent : on efface celui qui ne s'applique pas.
        if ($data['type'] === 'module') {
            $data['url'] = null;
        } else {
            $data['module_key'] = null;
        }

        // Les roles sont saisis separes par des virgules. Ceux deja connus
        // gardent le libelle et la description envoyes par l'application.
        $connus = collect($application?->roleCatalogue() ?? [])->keyBy('code');

        $data['roles'] = collect(explode(',', (string) ($data['roles'] ?? '')))
            ->map(fn ($r) => trim($r))
            ->filter()
            ->unique()
            ->map(fn ($code) => $connus[$code] ?? ['code' => $code, 'libelle' => $code, 'description' => null])
            ->values()
            ->all() ?: null;

        // Le secret n'est jamais reaffiche : on ne le remplace que si un
        // nouveau est fourni, ou si l'administrateur demande a le regenerer.
        if ($request->boolean('regenerer_secret')) {
            $data['client_secret'] = Str::random(64);
        } elseif (blank($data['client_secret'] ?? null)) {
            unset($data['client_secret']);
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['opens_new_tab'] = $request->boolean('opens_new_tab');
        $data['sort_order'] ??= 0;
        $data['icon'] = ($data['icon'] ?? null) ?: 'grid';
        $data['color'] = ($data['color'] ?? null) ?: '#1d4ed8';

        return $data;
    }
}
