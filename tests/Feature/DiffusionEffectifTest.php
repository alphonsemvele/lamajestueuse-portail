<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Services\DiffusionEffectif;
use App\Services\PortalIdentityToken;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Le portail annonce aux applications qui travaille chez elles, a chaque
 * changement d'acces — pour qu'elles connaissent un agent avant sa premiere
 * connexion. Et elles peuvent venir rechercher la liste elles-memes.
 */
class DiffusionEffectifTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-partage-de-soixante-quatre-caracteres-pour-le-test-12345';

    private function application(array $overrides = []): Application
    {
        return Application::factory()->create(array_merge([
            'url' => 'http://127.0.0.1:8002',
            'client_id' => 'ium-'.Str::random(6),
            'client_secret' => self::SECRET,
        ], $overrides));
    }

    private function administrateur(): User
    {
        return User::factory()->superadmin()->create();
    }

    private function jetonApplication(Application $app, string $usage = 'effectif'): string
    {
        return JWT::encode([
            'iss' => $app->client_id,
            'aud' => 'portail',
            'usage' => $usage,
            'jti' => (string) Str::uuid(),
            'iat' => time(),
            'exp' => time() + 60,
        ], self::SECRET, PortalIdentityToken::ALGORITHME);
    }

    public function test_la_charge_porte_lidentite_les_roles_et_le_droit_dentrer(): void
    {
        $app = $this->application();
        $actif = User::factory()->create([
            'name' => 'Alphonse', 'lastname' => 'MVELE', 'matricule' => 'LM-0001', 'status' => 'active',
        ]);
        $attente = User::factory()->create(['status' => 'pending']);

        $app->users()->attach($actif, Application::rolesAttributes(['enseignant'])
            + ['reference_locale' => 'ENS-01', 'poste' => 'Enseignant']);
        $app->users()->attach($attente, Application::rolesAttributes(['personnel']));

        $charge = app(DiffusionEffectif::class)->charge($app->fresh());

        $this->assertTrue($charge['complet']);
        $this->assertCount(2, $charge['effectif']);

        $ligne = collect($charge['effectif'])->firstWhere('portail_id', $actif->id);
        $this->assertSame('Alphonse', $ligne['prenom']);
        $this->assertSame('MVELE', $ligne['nom']);
        $this->assertSame('ENS-01', $ligne['reference']);
        $this->assertSame('Enseignant', $ligne['poste']);
        $this->assertSame(['enseignant'], $ligne['roles']);
        $this->assertTrue($ligne['peut_se_connecter']);

        // Un compte en attente de validation est annonce, mais n'entre pas.
        $enAttente = collect($charge['effectif'])->firstWhere('portail_id', $attente->id);
        $this->assertFalse($enAttente['peut_se_connecter']);
    }

    public function test_un_compte_hors_effectifs_est_annonce_mais_signale_comme_tel(): void
    {
        $app = $this->application();
        $employe = User::factory()->create(['status' => 'active', 'dans_le_personnel' => true]);
        $technique = User::factory()->create(['status' => 'active', 'dans_le_personnel' => false]);

        $app->users()->attach($employe);
        $app->users()->attach($technique);

        $charge = app(DiffusionEffectif::class)->charge($app->fresh());

        // Les deux entrent dans l'application ; seul le premier est un employe.
        $this->assertTrue(collect($charge['effectif'])->firstWhere('portail_id', $employe->id)['dans_le_personnel']);
        $this->assertFalse(collect($charge['effectif'])->firstWhere('portail_id', $technique->id)['dans_le_personnel']);
    }

    public function test_modifier_les_acces_previent_lapplication(): void
    {
        Http::fake(['*' => Http::response(['resultat' => []], 200)]);

        $app = $this->application();
        $employe = User::factory()->create(['status' => 'active']);

        $this->actingAs($this->administrateur())
            ->put(route('admin.applications.access.update', $app), [
                'users' => [$employe->id],
                'roles' => [$employe->id => ['enseignant']],
                'references' => [],
            ])
            ->assertRedirect();

        Http::assertSent(function (Request $requete) use ($app, $employe) {
            $charge = $requete->data();

            return str_ends_with($requete->url(), '/portail/effectif')
                && $charge['application'] === $app->client_id
                && collect($charge['effectif'])->contains('portail_id', $employe->id);
        });

        $this->assertNotNull($app->fresh()->effectif_diffuse_le);
    }

    public function test_retirer_un_acces_previent_aussi_lapplication_quittee(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $app = $this->application();
        $employe = User::factory()->create(['status' => 'active']);
        $app->users()->attach($employe);

        $this->actingAs($this->administrateur())
            ->put(route('admin.users.update', $employe), [
                'name' => $employe->name,
                'role' => 'employee',
                'status' => 'active',
                'locale' => 'fr',
                'applications' => [],
            ]);

        // L'application apprend le depart : son effectif ne porte plus l'agent.
        Http::assertSent(fn (Request $requete) => str_ends_with($requete->url(), '/portail/effectif')
            && collect($requete->data()['effectif'])->doesntContain('portail_id', $employe->id));
    }

    public function test_une_diffusion_qui_echoue_ne_fait_pas_echouer_lenregistrement(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $app = $this->application();
        $employe = User::factory()->create(['status' => 'active']);

        $reponse = $this->actingAs($this->administrateur())
            ->put(route('admin.applications.access.update', $app), [
                'users' => [$employe->id],
                'roles' => [],
                'references' => [],
            ]);

        $reponse->assertRedirect();
        // L'acces est bien enregistre, et l'administrateur est averti.
        $this->assertDatabaseHas('application_user', [
            'application_id' => $app->id, 'user_id' => $employe->id,
        ]);
        $this->assertStringContainsString("n'a pas reçu", session('status'));
        $this->assertNull($app->fresh()->effectif_diffuse_le);
    }

    public function test_une_application_vient_chercher_son_effectif(): void
    {
        $app = $this->application();
        $employe = User::factory()->create(['status' => 'active']);
        $app->users()->attach($employe, Application::rolesAttributes(['enseignant']));

        $this->withToken($this->jetonApplication($app))
            ->getJson('/api/applications/effectif')
            ->assertOk()
            ->assertJsonPath('application', $app->client_id)
            ->assertJsonPath('effectif.0.portail_id', $employe->id);
    }

    public function test_un_jeton_destine_a_un_autre_usage_est_refuse(): void
    {
        $app = $this->application();

        $this->withToken($this->jetonApplication($app, 'synchronisation'))
            ->getJson('/api/applications/effectif')
            ->assertUnauthorized();
    }

    public function test_un_jeton_signe_avec_un_autre_secret_est_refuse(): void
    {
        $app = $this->application();

        $jeton = JWT::encode([
            'iss' => $app->client_id, 'aud' => 'portail', 'usage' => 'effectif',
            'jti' => (string) Str::uuid(), 'iat' => time(), 'exp' => time() + 60,
        ], 'un-autre-secret-tout-aussi-long-mais-qui-nest-pas-le-bon-00000', PortalIdentityToken::ALGORITHME);

        $this->withToken($jeton)->getJson('/api/applications/effectif')->assertUnauthorized();
    }

    public function test_un_module_du_portail_ne_recoit_aucune_diffusion(): void
    {
        Http::fake();

        $module = Application::factory()->module()->create();

        $this->assertSame([], app(DiffusionEffectif::class)->pousserVers([$module->id]));
        Http::assertNothingSent();
    }
}
