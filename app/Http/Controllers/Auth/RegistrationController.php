<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\HandlesMediaUploads;
use App\Mail\Compte\InscriptionRecue;
use App\Services\CourrielsPortail;
use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Application;
use App\Models\User;
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

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'lastname' => ['required', 'string', 'max:80'],
            'sexe' => ['required', Rule::in(['M', 'F'])],
            /*
             * Matricule et adresse sont l'un et l'autre facultatifs — un
             * nouvel arrivant n'a pas encore de matricule, et tout le
             * personnel n'a pas d'adresse professionnelle — mais il en faut
             * au moins un : c'est avec lui qu'on se connecte.
             */
            'matricule' => ['nullable', 'required_without:email', 'string', 'max:40', 'unique:users,matricule'],
            'email' => ['nullable', 'required_without:matricule', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::min(8)],
            // Photo de profil exigee : elle identifie l'employe dans le portail.
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            // Facultatif : l'administrateur attribue les accès à la validation.
            'instituts' => ['nullable', 'array'],
            'instituts.*' => [Rule::in($instituts->pluck('id')->all())],
            'postes' => ['nullable', 'array'],
        ], [
            'instituts.*.in' => __("Cet institut n'est pas disponible."),
            'photo.required' => __('Une photo de profil est obligatoire.'),
            'matricule.required_without' => __('Indiquez votre matricule ou votre adresse professionnelle : c’est avec l’un des deux que vous vous connecterez.'),
            'email.required_without' => __('Indiquez votre adresse professionnelle ou votre matricule : c’est avec l’un des deux que vous vous connecterez.'),
        ]);

        $choisis = $data['instituts'] ?? [];

        // Un poste reste exige pour chaque institut effectivement coche.
        $request->validate(
            collect($choisis)
                ->mapWithKeys(fn ($id) => ["postes.{$id}" => ['required', 'string', 'max:120']])
                ->all(),
            collect($choisis)
                ->mapWithKeys(fn ($id) => [
                    "postes.{$id}.required" => __('Précisez votre poste à :institut.', [
                        'institut' => $instituts->firstWhere('id', (int) $id)?->name,
                    ]),
                ])
                ->all()
        );

        $user = User::create([
            'name' => $data['name'],
            'lastname' => $data['lastname'],
            'sexe' => $data['sexe'],
            // Vide plutot que chaine vide : le matricule est unique en base.
            'matricule' => ($data['matricule'] ?? null) ?: null,
            'email' => ($data['email'] ?? null) ?: null,
            'phone' => $data['phone'],
            'password' => $data['password'],
            'avatar' => $request->file('photo')->store('utilisateurs/photos', 'public'),
            // Poste et entite ne sont renseignes que si un institut a ete choisi.
            'poste' => $choisis ? $request->input("postes.{$choisis[0]}") : null,
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
