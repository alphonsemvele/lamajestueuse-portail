<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\User;
use App\Services\PortalIdentityToken;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApplicationRolesTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-partage-de-soixante-quatre-caracteres-pour-le-test-12345';

    private function application(array $overrides = []): Application
    {
        return Application::factory()->create(array_merge([
            'url' => 'http://127.0.0.1:8002',
            'client_id' => 'ium-'.Str::random(6),
            'client_secret' => self::SECRET,
            'roles' => [
                ['code' => 'admin', 'libelle' => 'Administrateur', 'description' => null],
                ['code' => 'coordonnateur', 'libelle' => 'Coordonnateur', 'description' => null],
                ['code' => 'enseignant', 'libelle' => 'Enseignant', 'description' => 'Ses cours'],
            ],
        ], $overrides));
    }

    private function annuaire(): array
    {
        return [
            'roles' => [
                ['code' => 'admin', 'libelle' => 'Administrateur', 'description' => 'Tout'],
                ['code' => 'finance', 'libelle' => 'Finance & RH', 'description' => 'Paie'],
                ['code' => 'enseignant', 'libelle' => 'Enseignant', 'description' => null],
                ['code' => 'pas un code !', 'libelle' => 'Invalide'],
            ],
            'personnel' => [
                ['reference' => 'ENS-001', 'matricule' => 'ENS-001', 'prenom' => 'Awa', 'nom' => 'NGONO', 'email' => 'awa@ium.cm', 'poste' => 'Enseignante', 'roles' => ['enseignant', 'finance', 'inconnu']],
                ['reference' => 'ADM-002', 'matricule' => 'ADM-002', 'prenom' => 'Paul', 'nom' => 'ETOA', 'email' => null, 'roles' => ['admin']],
                ['reference' => '', 'prenom' => 'Sans', 'nom' => 'Reference'],
            ],
        ];
    }

    public function test_plusieurs_roles_sont_attribues_dans_lordre_du_catalogue_et_transmis(): void
    {
        $admin = User::factory()->admin()->create();
        $app = $this->application();
        $employe = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.applications.access.update', $app), [
            'users' => [$employe->id],
            'roles' => [$employe->id => ['enseignant', 'coordonnateur']],
        ])->assertSessionHasNoErrors();

        $pivot = $app->fresh()->users->find($employe->id)->pivot;
        $this->assertSame(['coordonnateur', 'enseignant'], Application::pivotRoles($pivot));
        $this->assertSame('coordonnateur', $pivot->role_in_app);

        $charge = JWT::decode(app(PortalIdentityToken::class)->pour($employe->fresh(), $app), new Key(self::SECRET, 'HS256'));
        $this->assertSame(['coordonnateur', 'enseignant'], $charge->roles);
        $this->assertSame('coordonnateur', $charge->role);
    }

    public function test_un_role_inconnu_dans_la_liste_est_refuse(): void
    {
        $admin = User::factory()->admin()->create();
        $app = $this->application();
        $employe = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.applications.access', $app))
            ->put(route('admin.applications.access.update', $app), [
                'users' => [$employe->id],
                'roles' => [$employe->id => ['enseignant', 'recteur']],
            ])
            ->assertSessionHasErrors("roles.{$employe->id}.1");

        $this->assertFalse($app->fresh()->users->contains($employe));
    }

    public function test_la_fiche_employe_attribue_plusieurs_roles_par_application(): void
    {
        $admin = User::factory()->admin()->create();
        $app = $this->application();
        $employe = User::factory()->create(['name' => 'Awa']);

        $this->actingAs($admin)->put(route('admin.users.update', $employe), [
            'name' => 'Awa', 'role' => 'employee', 'status' => 'active', 'locale' => 'fr',
            'applications' => [$app->id],
            'roles' => [$app->id => ['enseignant', 'admin']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['admin', 'enseignant'], Application::pivotRoles($employe->applications()->first()->pivot));

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $employe))
            ->put(route('admin.users.update', $employe), [
                'name' => 'Awa Renommée', 'role' => 'employee', 'status' => 'active', 'locale' => 'fr',
                'applications' => [$app->id],
                'roles' => [$app->id => ['directeur']],
            ])
            ->assertSessionHasErrors("roles.{$app->id}");

        // Rien n'est enregistre quand un role est refuse.
        $this->assertSame('Awa', $employe->fresh()->name);
    }

    public function test_le_portail_recupere_les_roles_et_le_personnel_de_lapplication(): void
    {
        $admin = User::factory()->admin()->create();
        $app = $this->application(['roles' => null]);
        $existant = User::factory()->create(['email' => 'awa@ium.cm']);

        Http::fake(['127.0.0.1:8002/portail/annuaire' => Http::response($this->annuaire())]);

        $this->actingAs($admin)
            ->post(route('admin.applications.sync', $app))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Http::assertSent(function ($request) use ($app) {
            $charge = JWT::decode((string) Str::after($request->header('Authorization')[0], 'Bearer '), new Key(self::SECRET, 'HS256'));

            return $charge->aud === $app->client_id && $charge->usage === 'annuaire';
        });

        $app->refresh();
        $this->assertSame(['admin', 'finance', 'enseignant'], $app->roleCodes());
        $this->assertSame('Finance & RH', $app->roleCatalogue()[1]['libelle']);
        $this->assertNotNull($app->roles_synchronises_le);

        // Rattache au compte existant par l'adresse, roles connus seulement, ordre du catalogue.
        $lien = $app->users->find($existant->id)->pivot;
        $this->assertSame('ENS-001', $lien->reference_locale);
        $this->assertSame(['finance', 'enseignant'], Application::pivotRoles($lien));

        // Cree le compte manquant, avec le matricule local comme identifiant.
        $paul = User::where('matricule', 'ADM-002')->firstOrFail();
        $this->assertSame('Paul', $paul->name);
        $this->assertSame($app->name, $paul->entite);
        $this->assertSame(['admin'], Application::pivotRoles($app->users->find($paul->id)->pivot));
        $this->assertSame(3, User::count());
    }

    public function test_une_nouvelle_synchronisation_ne_remplace_pas_les_roles_attribues_au_portail(): void
    {
        $admin = User::factory()->admin()->create();
        $app = $this->application();
        $employe = User::factory()->create();
        $employe->applications()->attach($app, Application::rolesAttributes(['coordonnateur']) + ['reference_locale' => 'ENS-001']);

        Http::fake(['*' => Http::response($this->annuaire())]);

        $this->actingAs($admin)->post(route('admin.applications.sync', $app))->assertSessionHasNoErrors();

        $this->assertSame(['coordonnateur'], Application::pivotRoles($app->fresh()->users->find($employe->id)->pivot));
        $this->assertSame(1, $app->fresh()->users()->where('users.id', $employe->id)->count());
    }

    public function test_un_employe_present_dans_deux_instituts_garde_un_seul_compte(): void
    {
        $ifpm = $this->application();
        $ium = $this->application(['roles' => null]);
        $employe = User::factory()->create(['name' => 'Awa', 'lastname' => 'Ngono', 'email' => 'awa@ifpm.cm']);
        $employe->applications()->attach($ifpm, ['reference_locale' => 'IFPM-01']);
        User::factory()->create(['name' => 'Jean', 'lastname' => 'Mballa']);
        User::factory()->create(['name' => 'Jean', 'lastname' => 'Mballa']);

        $resultat = app(\App\Services\ApplicationDirectorySync::class)->apply($ium, [
            'roles' => [['code' => 'enseignant', 'libelle' => 'Enseignant']],
            'personnel' => [
                // Autre adresse, meme nom (accents et ordre des mots ignores) : meme personne.
                ['reference' => 'ENS-001', 'prenom' => 'NGONO', 'nom' => 'Awa', 'email' => 'awa@ium.cm', 'roles' => ['enseignant']],
                ['reference' => 'ENS-001-BIS', 'prenom' => 'Awa', 'nom' => 'NGONO', 'email' => 'awa@ium.cm'],
                // Homonymes au portail : on ne devine pas, on cree un compte.
                ['reference' => 'ENS-002', 'prenom' => 'Jean', 'nom' => 'MBALLA'],
            ],
        ]);

        $this->assertSame(['roles' => 1, 'crees' => 1, 'rattaches' => 1, 'deja_lies' => 0, 'ignores' => 1], $resultat);
        $this->assertSame(['enseignant'], Application::pivotRoles($ium->users()->find($employe->id)->pivot));
        $this->assertCount(2, $employe->fresh()->applications);
        $this->assertSame(4, User::count());
    }

    public function test_une_application_injoignable_affiche_une_erreur(): void
    {
        $admin = User::factory()->admin()->create();
        $app = $this->application();

        Http::fake(['*' => Http::response(['message' => 'Accès refusé.'], 401)]);

        $this->actingAs($admin)
            ->from(route('admin.applications.access', $app))
            ->post(route('admin.applications.sync', $app))
            ->assertSessionHasErrors('synchronisation');
    }

    public function test_lapplication_pousse_ses_roles_et_son_personnel_avec_un_jeton_signe(): void
    {
        $app = $this->application(['roles' => null]);

        $jeton = fn (array $surcharge = [], string $secret = self::SECRET) => JWT::encode($surcharge + [
            'iss' => $app->client_id, 'aud' => 'portail', 'usage' => 'synchronisation',
            'jti' => (string) Str::uuid(), 'iat' => time(), 'exp' => time() + 60,
        ], $secret, 'HS256');

        $valide = $jeton();

        $this->withToken($valide)->postJson(route('api.applications.sync'), $this->annuaire())
            ->assertOk()
            ->assertJsonPath('resultat.roles', 3)
            ->assertJsonPath('resultat.crees', 2)
            ->assertJsonPath('resultat.ignores', 1);

        $this->assertSame(['admin', 'finance', 'enseignant'], $app->fresh()->roleCodes());

        // Rejeu, mauvaise signature, mauvais usage : refuses.
        $this->withToken($valide)->postJson(route('api.applications.sync'), $this->annuaire())->assertUnauthorized();
        $this->withToken($jeton([], str_repeat('x', 64)))->postJson(route('api.applications.sync'), [])->assertUnauthorized();
        $this->withToken($jeton(['usage' => 'annuaire']))->postJson(route('api.applications.sync'), [])->assertUnauthorized();
        $this->postJson(route('api.applications.sync'), [])->assertUnauthorized();
    }

    public function test_la_liste_du_personnel_se_filtre_par_institut_et_montre_les_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $app = $this->application();
        $autre = $this->application();
        $employe = User::factory()->create();
        $employe->applications()->attach($app, Application::rolesAttributes(['coordonnateur', 'enseignant']));
        User::factory()->create()->applications()->attach($autre);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['application' => $app->slug]))
            ->assertInertia(fn ($page) => $page
                ->component('admin/users/index')
                ->has('users.data', 1)
                ->where('users.data.0.id', $employe->id)
                ->where('users.data.0.acces.0.roles', ['Coordonnateur', 'Enseignant']));
    }

    public function test_un_module_reconnait_un_gestionnaire_parmi_plusieurs_roles(): void
    {
        $module = Application::factory()->module()->create();
        $employe = User::factory()->create();
        $role = config("modules.{$module->module_key}.manage_roles")[0] ?? null;

        if ($role === null) {
            $this->markTestSkipped('Ce module ne declare aucun role de gestion.');
        }

        $employe->applications()->attach($module, Application::rolesAttributes(['lecteur', $role]));

        $this->assertTrue($module->allowsManagementBy($employe));
    }
}
