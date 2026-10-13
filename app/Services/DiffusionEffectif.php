<?php

namespace App\Services;

use App\Models\Application;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Diffusion de l'effectif vers une application raccordée.
 *
 * Le portail est la seule autorité sur les comptes : une application ne crée
 * personne de son côté. Mais elle a besoin de connaître son personnel AVANT
 * la première connexion — pour affecter un enseignant à une spécialité, lui
 * demander un document, le placer dans un emploi du temps.
 *
 * On lui envoie donc la liste complète de ses agents à chaque changement
 * d'accès. Liste complète et non pas « ce qui a changé » : c'est idempotent,
 * une diffusion perdue est rattrapée par la suivante, et l'application peut
 * aussi venir la chercher elle-même (ApplicationRosterController).
 *
 * L'envoi est synchrone et volontairement court : il n'y a pas de worker de
 * file d'attente en production. Un échec est journalisé, jamais fatal —
 * l'administrateur garde son écran, et le rattrapage remettra tout d'aplomb.
 */
class DiffusionEffectif
{
    /** Au-delà, on renonce : l'écran d'administration ne doit pas attendre. */
    private const DELAI = 8;

    /**
     * L'effectif d'une application : tous les comptes qui la portent.
     *
     * @return array<string, mixed>
     */
    public function charge(Application $application): array
    {
        $agents = $application->users()
            ->orderBy('users.name')->orderBy('users.lastname')
            ->get()
            ->map(fn (User $user) => [
                // Clé de rapprochement principale : l'identifiant du compte au
                // portail. Il ne change jamais, contrairement au matricule et
                // à l'adresse, que l'administration corrige régulièrement.
                'portail_id' => $user->id,
                'reference' => $user->pivot->reference_locale,
                'matricule' => $user->matricule,
                'prenom' => $user->name,
                'nom' => $user->lastname,
                'email' => $user->email,
                'telephone' => $user->phone,
                // Le poste occupé DANS cette application prime sur le général.
                'poste' => $user->pivot->poste ?: $user->poste,
                'entite' => $user->entite,
                'sexe' => $user->sexe,
                'photo' => $user->avatarUrl(),
                'roles' => Application::pivotRoles($user->pivot),
                'statut' => $user->status,
                /*
                 * Figure-t-il aux effectifs ? Un administrateur technique ou
                 * un compte de service ouvre l'application sans etre un
                 * employe : l'institut le laisse entrer, mais ne l'inscrit
                 * pas dans son personnel.
                 */
                'dans_le_personnel' => (bool) $user->dans_le_personnel,
                // Un compte en attente de validation existe déjà, mais n'entre
                // nulle part : l'application le crée sans lui ouvrir l'accès.
                'peut_se_connecter' => $user->status === 'active',
            ])
            ->values()
            ->all();

        return [
            'application' => $application->client_id,
            'genere_le' => now()->toIso8601String(),
            // La liste fait foi : tout agent absent d'ici n'a plus accès.
            'complet' => true,
            'effectif' => $agents,
        ];
    }

    /**
     * Envoie l'effectif à l'application. Retourne le message d'échec, ou null
     * si la diffusion a abouti.
     */
    public function pousser(Application $application): ?string
    {
        if (! $application->usesPortalSignOn() || blank($application->url)) {
            return null;
        }

        $maintenant = time();
        $jeton = JWT::encode([
            'iss' => config('app.url'),
            'aud' => $application->client_id,
            'usage' => 'effectif',
            'jti' => (string) Str::uuid(),
            'iat' => $maintenant,
            'exp' => $maintenant + 60,
        ], $application->client_secret, PortalIdentityToken::ALGORITHME);

        try {
            $reponse = Http::acceptJson()->timeout(self::DELAI)->withToken($jeton)
                ->post(rtrim($application->url, '/').'/portail/effectif', $this->charge($application));
        } catch (Throwable $e) {
            return $this->echec($application, $e->getMessage());
        }

        if ($reponse->failed()) {
            return $this->echec($application, "code {$reponse->status()}");
        }

        $application->forceFill(['effectif_diffuse_le' => now()])->save();

        return null;
    }

    /**
     * Diffuse vers plusieurs applications à la fois (un employé peut perdre
     * l'accès à l'une en gagnant celui d'une autre dans le même écran).
     *
     * @param  iterable<int|Application>  $applications
     * @return array<int, string> les échecs, par nom d'application
     */
    public function pousserVers(iterable $applications): array
    {
        $echecs = [];

        foreach ($this->resoudre($applications) as $application) {
            if ($message = $this->pousser($application)) {
                $echecs[$application->name] = $message;
            }
        }

        return $echecs;
    }

    /**
     * Phrase à montrer à l'administrateur quand une diffusion a échoué : son
     * enregistrement, lui, a bien eu lieu.
     *
     * @param  array<int, string>  $echecs
     */
    public static function avertissement(array $echecs): ?string
    {
        if (! $echecs) {
            return null;
        }

        return __("Enregistré. En revanche :apps n'a pas reçu la mise à jour de son personnel (:raisons) ; elle la récupérera à sa prochaine synchronisation.", [
            'apps' => implode(', ', array_keys($echecs)),
            'raisons' => implode(' · ', $echecs),
        ]);
    }

    /**
     * @param  iterable<int|Application>  $applications
     * @return Collection<int, Application>
     */
    private function resoudre(iterable $applications): Collection
    {
        $liste = collect($applications);
        $ids = $liste->filter(fn ($a) => ! $a instanceof Application)->map(fn ($a) => (int) $a)->filter()->unique();

        return $liste->filter(fn ($a) => $a instanceof Application)
            ->merge($ids->isNotEmpty() ? Application::whereKey($ids)->get() : collect())
            ->unique('id')
            ->filter(fn (Application $a) => $a->usesPortalSignOn() && filled($a->url))
            ->values();
    }

    private function echec(Application $application, string $raison): string
    {
        Log::warning('Diffusion de l\'effectif : échec.', [
            'application' => $application->slug,
            'raison' => $raison,
        ]);

        return $raison;
    }
}
