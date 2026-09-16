<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Services\PortalIdentityToken;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalSignOnTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-partage-de-soixante-quatre-caracteres-pour-le-test-12345';

    private function application(array $overrides = []): Application
    {
        // client_id est unique : chaque application de test a le sien.
        return Application::factory()->create(array_merge([
            'url' => 'http://127.0.0.1:8002',
            'client_id' => 'ium-'.Str::random(6),
            'client_secret' => self::SECRET,
        ], $overrides));
    }

    private function decoder(string $token, string $secret): object
    {
        return JWT::decode($token, new Key($secret, PortalIdentityToken::ALGORITHME));
    }

    public function test_une_application_est_raccordee_quand_elle_a_un_couple_didentifiants(): void
    {
        $this->assertTrue($this->application()->usesPortalSignOn());
        $this->assertFalse($this->application(['client_secret' => null])->usesPortalSignOn());
        $this->assertFalse($this->application(['client_id' => null])->usesPortalSignOn());
        $this->assertFalse(Application::factory()->module()->create()->usesPortalSignOn());
    }

    public function test_le_jeton_porte_lidentite_professionnelle_et_le_role(): void
    {
        $app = $this->application();
        $user = User::factory()->create([
            'name' => 'Alphonse', 'lastname' => 'MVELE', 'matricule' => 'LM-0001',
            'phone' => '+237 699000001', 'entite' => 'Direction',
        ]);
        $user->applications()->attach($app, [
            'role_in_app' => 'admin', 'poste' => 'Coordonnateur', 'reference_locale' => 'ENS-GIUTVL',
        ]);

        $charge = $this->decoder(app(PortalIdentityToken::class)->pour($user, $app), self::SECRET);

        $this->assertSame($app->client_id, $charge->aud);
        $this->assertSame((string) $user->id, $charge->sub);
        $this->assertSame('LM-0001', $charge->matricule);
        $this->assertSame('admin', $charge->role);
        $this->assertSame('ENS-GIUTVL', $charge->reference);
        // Le poste dans cette application prime sur le poste general.
        $this->assertSame('Coordonnateur', $charge->poste);
    }

    public function test_le_jeton_ne_transporte_jamais_de_mot_de_passe(): void
    {
        $app = $this->application();
        $user = User::factory()->create();
        $user->applications()->attach($app);

        $charge = (array) $this->decoder(app(PortalIdentityToken::class)->pour($user, $app), self::SECRET);

        foreach (['password', 'remember_token', 'mot_de_passe'] as $interdit) {
            $this->assertArrayNotHasKey($interdit, $charge);
        }
    }

    public function test_le_jeton_expire_vite_et_ne_sert_quune_fois(): void
    {
        $app = $this->application();
        $user = User::factory()->create();
        $user->applications()->attach($app);

        $a = $this->decoder(app(PortalIdentityToken::class)->pour($user, $app), self::SECRET);
        $b = $this->decoder(app(PortalIdentityToken::class)->pour($user, $app), self::SECRET);

        $this->assertSame(PortalIdentityToken::DUREE, $a->exp - $a->iat);
        // Chaque remise porte un identifiant distinct, pour bloquer le rejeu.
        $this->assertNotSame($a->jti, $b->jti);
    }

    public function test_louverture_dirige_vers_lentree_signee_de_lapplication(): void
    {
        $app = $this->application();
        $user = User::factory()->create();
        $user->applications()->attach($app);

        $reponse = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) (new \App\Http\Middleware\HandleInertiaRequests())->version(request()),
            ])
            ->get(route('applications.open', $app))
            ->assertStatus(409);

        $cible = $reponse->headers->get('X-Inertia-Location');

        $this->assertStringStartsWith('http://127.0.0.1:8002/sso/portail?token=', $cible);
        $this->assertDatabaseHas('access_logs', ['user_id' => $user->id, 'action' => 'sso']);
    }

    public function test_une_application_non_raccordee_garde_le_lien_simple(): void
    {
        $app = $this->application(['client_secret' => null, 'url' => 'https://ancien.lamajestueuse.cm']);
        $user = User::factory()->create();
        $user->applications()->attach($app);

        $reponse = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) (new \App\Http\Middleware\HandleInertiaRequests())->version(request()),
            ])
            ->get(route('applications.open', $app));

        $this->assertSame('https://ancien.lamajestueuse.cm', $reponse->headers->get('X-Inertia-Location'));
        $this->assertDatabaseHas('access_logs', ['user_id' => $user->id, 'action' => 'open']);
    }

    public function test_le_role_affecte_doit_figurer_parmi_ceux_declares(): void
    {
        $admin = User::factory()->admin()->create();
        $app = $this->application(['roles' => ['admin', 'enseignant', 'personnel']]);
        $employe = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.applications.access', $app))
            ->put(route('admin.applications.access.update', $app), [
                'users' => [$employe->id],
                'roles' => [$employe->id => 'directeur-general'],
            ])
            ->assertSessionHasErrors("roles.{$employe->id}.0");

        $this->actingAs($admin)
            ->put(route('admin.applications.access.update', $app), [
                'users' => [$employe->id],
                'roles' => [$employe->id => 'enseignant'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('enseignant', $app->fresh()->users->find($employe->id)->pivot->role_in_app);
    }

    public function test_la_reference_locale_est_enregistree_et_transmise(): void
    {
        $admin = User::factory()->admin()->create();
        $app = $this->application();
        $employe = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.applications.access.update', $app), [
            'users' => [$employe->id],
            'roles' => [$employe->id => 'admin'],
            'references' => [$employe->id => 'ENS-GIUTVL'],
        ])->assertSessionHasNoErrors();

        $charge = $this->decoder(app(PortalIdentityToken::class)->pour($employe->fresh(), $app), self::SECRET);

        $this->assertSame('ENS-GIUTVL', $charge->reference);
        $this->assertSame('admin', $charge->role);
    }

    public function test_le_secret_nest_jamais_transmis_a_linterface(): void
    {
        $app = $this->application();

        $this->assertArrayNotHasKey('client_secret', $app->toUiArray());
        $this->assertArrayNotHasKey('clientSecret', $app->toUiArray());
        $this->assertTrue($app->toUiArray()['hasClientSecret']);
    }

    public function test_le_secret_est_chiffre_en_base(): void
    {
        $app = $this->application();

        $brut = \Illuminate\Support\Facades\DB::table('applications')->where('id', $app->id)->value('client_secret');

        $this->assertNotSame(self::SECRET, $brut);
        $this->assertSame(self::SECRET, $app->fresh()->client_secret);
    }
}
