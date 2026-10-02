<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\HandlesMediaUploads;
use App\Http\Controllers\Controller;
use App\Mail\Compte\InscriptionRecue;
use App\Models\AccessLog;
use App\Models\Application;
use App\Models\User;
use App\Services\CourrielsPortail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inscription du personnel.
 *
 * L'employe renseigne son identite, choisit un ou plusieurs instituts et
 * precise le poste qu'il occupe dans chacun. Le compte est cree EN ATTENTE :
 * il n'ouvre aucune application tant qu'un administrateur ne l'a pas valide.
 */
class RegistrationController extends Controller
{
    use HandlesMediaUploads;

    public function show(): Response
    {
        return Inertia::render('auth/register', [
            'instituts' => $this->instituts()->map->toUiArray()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $instituts = $this->instituts();

        /*
         * L'inscription ne demande rien d'autre que le prenom et le mot de
         * passe : le prenom parce qu'il faut bien nommer la demande dans la
         * liste de l'administrateur, le mot de passe parce qu'il n'y a pas
         * de compte sans lui. Tout le reste se complete ensuite, par
         * l'interesse ou par le service du personnel.
         *
         * Matricule et adresse restent uniques quand ils sont donnes : ils
         * servent a se connecter.
         */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'lastname' => ['nullable', 'string', 'max:80'],
            'sexe' => ['nullable', Rule::in(['M', 'F'])],
            'matricule' => ['nullable', 'string', 'max:40', 'unique:users,matricule'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'instituts' => ['nullable', 'array'],
            'instituts.*' => [Rule::in($instituts->pluck('id')->all())],
            'postes' => ['nullable', 'array'],
        ], [
            'instituts.*.in' => __("Cet institut n'est pas disponible."),
        ]);

        $choisis = $data['instituts'] ?? [];

        // Le poste declare pour chaque institut reste libre : la RH le pose
        // au contrat, et la fiche le reprend alors.
        $request->validate(
            collect($choisis)
                ->mapWithKeys(fn ($id) => ["postes.{$id}" => ['nullable', 'string', 'max:120']])
                ->all()
        );

        $user = User::create([
            'name' => $data['name'],
            'lastname' => $data['lastname'] ?? null,
            'sexe' => $data['sexe'] ?? null,
            // Vide plutot que chaine vide : le matricule est unique en base.
            'matricule' => ($data['matricule'] ?? null) ?: null,
            'email' => ($data['email'] ?? null) ?: null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'avatar' => $request->file('photo')?->store('utilisateurs/photos', 'public'),
            // Poste et entite ne sont renseignes que si un institut a ete choisi.
            'poste' => $choisis ? ($request->input("postes.{$choisis[0]}") ?: null) : null,
            'entite' => $choisis ? $instituts->firstWhere('id', (int) $choisis[0])?->name : null,
            'role' => 'employee',
            'status' => 'pending',
            'self_registered' => true,
            'locale' => $request->session()->get('locale', config('app.locale')),
        ]);

        $user->applications()->sync(
            collect($choisis)
                ->mapWithKeys(fn ($id) => [(int) $id => ['poste' => $request->input("postes.{$id}")]])
                ->all()
        );

        AccessLog::create([
            'user_id' => $user->id,
            'action' => 'register',
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        // On renvoie l'identifiant avec lequel l'employe pourra se connecter.
        // L'accuse part au mieux : un relais muet ne doit pas faire echouer
        // l'inscription elle-meme.
        app(CourrielsPortail::class)->envoyerA($user, new InscriptionRecue(
            $user->fullName(),
            $user->applications()->pluck('name')->all(),
        ));

        return redirect()->route('register')->with('registered', $user->email ?: $user->matricule);
    }

    /**
     * Les instituts proposes : uniquement les applications metier creees et
     * actives. Les liens rapides et les applications desactivees sont exclus.
     */
    private function instituts()
    {
        return Application::active()
            ->where('type', 'application')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
