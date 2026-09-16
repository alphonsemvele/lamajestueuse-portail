<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesMediaUploads;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use HandlesMediaUploads;

    public function index(Request $request): Response
    {
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
            // Le personnel d'un institut : les comptes ayant acces a son application.
            ->when($request->query('application'), fn ($q, $slug) => $q->whereHas(
                'applications', fn ($sub) => $sub->where('slug', $slug)
            ))
            // Les demandes en attente remontent en tete. CASE plutot que
            // FIELD() : la premiere forme fonctionne aussi sous SQLite.
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($user) => $user->toUiArray());

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'pendingCount' => User::pending()->count(),
            'institutions' => Application::where('type', 'application')->whereNotNull('client_id')
                ->orderBy('name')->get()
                ->map(fn ($a) => ['slug' => $a->slug, 'name' => $a->name])->all(),
            'filters' => [
                'q' => $request->query('q'),
                'role' => $request->query('role'),
                'status' => $request->query('status'),
                'application' => $request->query('application'),
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

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $acces = $this->accessPayload($request, $user);

        $user->update($data);
        $user->applications()->sync($acces);

        return redirect()->route('admin.users.index')
            ->with('status', "Le compte de {$user->fullName()} a été mis à jour.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }

        $name = $user->fullName();
        $this->deleteUploaded($user->avatar);
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "Le compte de {$name} a été supprimé.");
    }

    /**
     * Valide une demande d'inscription : le compte devient utilisable avec les
     * instituts qu'il avait demandes.
     */
    public function approve(User $user): RedirectResponse
    {
        if (! $user->isPending()) {
            return back()->with('status', __("Ce compte n'est pas en attente de validation."));
        }

        $user->approve();

        return back()->with('status', __('Le compte de :nom a été validé.', ['nom' => $user->fullName()]));
    }

    /**
     * Refuse une demande : le compte est suspendu et ses acces retires. Il
     * reste visible dans la liste, l'administrateur peut le supprimer ensuite.
     */
    public function reject(User $user): RedirectResponse
    {
        if (! $user->isPending()) {
            return back()->with('status', __("Ce compte n'est pas en attente de validation."));
        }

        $user->forceFill(['status' => 'suspended'])->save();
        $user->applications()->detach();

        return back()->with('status', __('La demande de :nom a été refusée.', ['nom' => $user->fullName()]));
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
