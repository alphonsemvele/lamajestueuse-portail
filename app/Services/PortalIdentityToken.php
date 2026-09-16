<?php

namespace App\Services;

use App\Models\Application;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

/**
 * Jeton d'identite remis a une application metier.
 *
 * Le portail signe une assertion courte (60 s) avec le secret partage de
 * l'application. Celle-ci la verifie, ouvre la session locale, et l'employe
 * arrive directement dans son espace : plus de formulaire de connexion.
 *
 * Le jeton ne transporte que l'identite professionnelle et le role dans
 * l'application — jamais de mot de passe.
 */
class PortalIdentityToken
{
    public const ALGORITHME = 'HS256';

    /** Duree de vie volontairement courte : le jeton ne sert qu'a la redirection. */
    public const DUREE = 60;

    public function pour(User $user, Application $application): string
    {
        $maintenant = time();
        $liaison = $user->applications()
            ->where('applications.id', $application->id)
            ->first()?->pivot;

        $charge = [
            'iss' => config('app.url'),
            'aud' => $application->client_id,
            'sub' => (string) $user->id,
            'jti' => (string) Str::uuid(),
            'iat' => $maintenant,
            'exp' => $maintenant + self::DUREE,

            'matricule' => $user->matricule,
            'prenom' => $user->name,
            'nom' => $user->lastname,
            'email' => $user->email,
            'telephone' => $user->phone,
            // Le poste occupe DANS cette application prime sur le poste general.
            'poste' => $liaison?->poste ?: $user->poste,
            'entite' => $user->entite,
            'sexe' => $user->sexe,
            'photo' => $user->avatarUrl(),
            // Tous les roles attribues dans cette application ; `role` garde le
            // principal pour les applications qui n'en lisent qu'un.
            'roles' => Application::pivotRoles($liaison),
            'role' => Application::pivotRoles($liaison)[0] ?? null,
            // Identifiant de l'employe dans l'application ciblee : il permet
            // de retrouver son compte existant plutot que d'en creer un autre.
            'reference' => $liaison?->reference_locale,
        ];

        return JWT::encode($charge, $application->client_secret, self::ALGORITHME);
    }

    /**
     * Adresse d'entree de l'application, jeton compris.
     */
    public function urlDeConnexion(User $user, Application $application): string
    {
        return rtrim((string) $application->url, '/')
            .'/sso/portail?token='.$this->pour($user, $application);
    }
}
