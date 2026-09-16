<?php

namespace App\Services;

use App\Models\Application;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Synchronisation avec une application raccordée : elle fournit son catalogue
 * de rôles et son personnel. Le portail met à jour le catalogue, puis rattache
 * chaque employé à un compte du portail (créé au besoin).
 *
 * Une fois un employé rattaché, ses rôles se gèrent au portail : une nouvelle
 * synchronisation ne les écrase pas.
 */
class ApplicationDirectorySync
{
    /**
     * Le portail va chercher l'annuaire chez l'application.
     *
     * @return array<string, int>
     */
    public function pull(Application $application): array
    {
        if (! $application->usesPortalSignOn() || blank($application->url)) {
            throw new RuntimeException(__("Cette application n'est pas raccordée au portail."));
        }

        $maintenant = time();
        $jeton = JWT::encode([
            'iss' => config('app.url'),
            'aud' => $application->client_id,
            'usage' => 'annuaire',
            'jti' => (string) Str::uuid(),
            'iat' => $maintenant,
            'exp' => $maintenant + 60,
        ], $application->client_secret, PortalIdentityToken::ALGORITHME);

        try {
            $reponse = Http::acceptJson()->timeout(20)->withToken($jeton)
                ->get(rtrim($application->url, '/').'/portail/annuaire');
        } catch (\Throwable $e) {
            throw new RuntimeException(__("L'application :app ne répond pas.", ['app' => $application->name]));
        }

        if ($reponse->failed() || ! is_array($reponse->json())) {
            throw new RuntimeException(__("L'application :app a refusé la synchronisation (code :code). Vérifiez l'identifiant client et le secret partagé.", [
                'app' => $application->name, 'code' => $reponse->status(),
            ]));
        }

        return $this->apply($application, $reponse->json());
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, int>
     */
    public function apply(Application $application, array $donnees): array
    {
        $resultat = ['roles' => 0, 'crees' => 0, 'rattaches' => 0, 'deja_lies' => 0, 'ignores' => 0];

        return DB::transaction(function () use ($application, $donnees, $resultat) {
            $catalogue = collect(is_array($donnees['roles'] ?? null) ? $donnees['roles'] : [])
                ->filter(fn ($role) => is_array($role) && preg_match('/^[A-Za-z0-9_.-]{1,60}$/', (string) ($role['code'] ?? '')))
                ->map(fn (array $role) => [
                    'code' => (string) $role['code'],
                    'libelle' => Str::limit((string) ($role['libelle'] ?? $role['code']), 80, ''),
                    'description' => filled($role['description'] ?? null) ? Str::limit((string) $role['description'], 250, '') : null,
                ])
                ->unique('code')
                ->values()
                ->all();

            if ($catalogue) {
                $application->forceFill(['roles' => $catalogue, 'roles_synchronises_le' => now()])->save();
                $resultat['roles'] = count($catalogue);
            }

            $codes = $application->roleCodes();
            $lies = $application->users()->get();
            $parReference = $lies->filter(fn ($u) => filled($u->pivot->reference_locale))
                ->keyBy(fn ($u) => Str::lower($u->pivot->reference_locale));

            // Comptes du portail pas encore lies a cette application, par nom complet.
            $parNom = User::whereDoesntHave('applications', fn ($q) => $q->whereKey($application->id))
                ->get(['id', 'name', 'lastname'])
                ->groupBy(fn ($u) => self::nameKey("{$u->name} {$u->lastname}") ?? '');

            $traites = [];

            foreach (is_array($donnees['personnel'] ?? null) ? $donnees['personnel'] : [] as $employe) {
                if (! is_array($employe)) {
                    $resultat['ignores']++;

                    continue;
                }

                $reference = trim((string) ($employe['reference'] ?? $employe['matricule'] ?? $employe['email'] ?? ''));
                $prenom = trim((string) ($employe['prenom'] ?? ''));
                $nom = trim((string) ($employe['nom'] ?? ''));

                if ($reference === '' || ($prenom === '' && $nom === '')) {
                    $resultat['ignores']++;

                    continue;
                }

                if ($parReference->has(Str::lower($reference))) {
                    $resultat['deja_lies']++;

                    continue;
                }

                $email = filter_var($employe['email'] ?? null, FILTER_VALIDATE_EMAIL) ? Str::lower($employe['email']) : null;
                $user = $email ? User::where('email', $email)->first() : null;

                // Le meme employe exerce souvent dans plusieurs instituts, avec une
                // adresse differente dans chacun : on le reconnait a son nom complet,
                // pourvu qu'il ne designe qu'un seul compte pas encore lie ici.
                $cle = self::nameKey("{$prenom} {$nom}");

                if (! $user && $cle !== null && $parNom->get($cle, collect())->count() === 1) {
                    $user = User::find($parNom->get($cle)->first()->id);
                }

                // Deux lignes du fichier pour le meme compte : on ne le lie qu'une fois.
                if ($user && isset($traites[$user->id])) {
                    $resultat['ignores']++;

                    continue;
                }

                if ($user) {
                    $resultat['rattaches']++;
                } else {
                    $matricule = trim((string) ($employe['matricule'] ?? ''));

                    $user = User::create([
                        'name' => Str::limit($prenom ?: $nom, 80, ''),
                        'lastname' => $prenom ? (Str::limit($nom, 80, '') ?: null) : null,
                        'email' => $email,
                        // Le matricule local sert d'identifiant de connexion au portail,
                        // sauf s'il est deja pris par un employe d'un autre institut.
                        'matricule' => $matricule !== '' && ! User::where('matricule', $matricule)->exists() ? Str::limit($matricule, 40, '') : null,
                        'phone' => Str::limit((string) ($employe['telephone'] ?? ''), 40, '') ?: null,
                        'poste' => Str::limit((string) ($employe['poste'] ?? ''), 120, '') ?: null,
                        'entite' => $application->name,
                        'role' => 'employee',
                        'status' => 'active',
                        'locale' => 'fr',
                        // Aucun mot de passe connu : l'administrateur en definit un.
                        'password' => Str::random(48),
                    ]);
                    $resultat['crees']++;
                }

                $roles = $application->sortRoles(array_intersect((array) ($employe['roles'] ?? []), $codes));
                $existant = $lies->firstWhere('id', $user->id);

                if ($existant) {
                    // Deja autorise au portail sans reference : on complete le lien,
                    // les roles attribues au portail restent.
                    $application->users()->updateExistingPivot($user->id, array_filter([
                        'reference_locale' => $reference,
                        'poste' => $existant->pivot->poste ?: ($employe['poste'] ?? null),
                    ]) + (Application::pivotRoles($existant->pivot) ? [] : Application::rolesAttributes($roles)));
                } else {
                    $application->users()->attach($user->id, Application::rolesAttributes($roles) + [
                        'reference_locale' => Str::limit($reference, 120, ''),
                        'poste' => Str::limit((string) ($employe['poste'] ?? ''), 120, '') ?: null,
                    ]);
                }

                $parReference->put(Str::lower($reference), $user);
                $traites[$user->id] = true;
            }

            return $resultat;
        });
    }

    /**
     * Cle de rapprochement d'un nom complet : sans accents ni casse, mots
     * tries (prenom et nom sont souvent inverses d'un institut a l'autre).
     * Un nom d'un seul mot (« Admin ») est trop vague pour rapprocher.
     */
    private static function nameKey(string $nomComplet): ?string
    {
        $mots = collect(explode(' ', Str::of(Str::ascii($nomComplet))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString()))
            ->filter()
            ->sort()
            ->values();

        return $mots->count() >= 2 ? $mots->implode(' ') : null;
    }
}
