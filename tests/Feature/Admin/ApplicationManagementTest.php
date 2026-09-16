<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_employe_ne_peut_pas_acceder_a_ladministration(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.applications.index'))->assertForbidden();
    }

    public function test_ladministrateur_cree_un_projet_avec_son_lien_de_redirection(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::create(['name' => 'Sante', 'slug' => 'sante', 'color' => '#0d9488']);

        $this->actingAs($admin)->post(route('admin.applications.store'), [
            'name' => 'Fondation Medicale',
            'description' => "Systeme d'information hospitalier.",
            'url' => 'https://fondation.lamajestueuse.cm',
            'category_id' => $category->id,
            'type' => 'application',
            'icon' => 'heart',
            'color' => '#0d9488',
            'client_id' => 'fondation-medicale',
            'is_active' => '1',
        ])->assertRedirect(route('admin.applications.index'));

        $this->assertDatabaseHas('applications', [
            'name' => 'Fondation Medicale',
            'slug' => 'fondation-medicale',
            'url' => 'https://fondation.lamajestueuse.cm',
            'category_id' => $category->id,
            'is_active' => true,
        ]);
    }

    public function test_le_lien_de_redirection_est_obligatoire_et_doit_etre_une_url(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.applications.create'))
            ->post(route('admin.applications.store'), ['name' => 'Sans lien', 'type' => 'application'])
            ->assertSessionHasErrors('url');

        $this->actingAs($admin)
            ->from(route('admin.applications.create'))
            ->post(route('admin.applications.store'), [
                'name' => 'Lien casse', 'type' => 'application', 'url' => 'pas-une-url',
            ])
            ->assertSessionHasErrors('url');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_le_slug_est_unique(): void
    {
        $admin = User::factory()->admin()->create();
        Application::factory()->create(['slug' => 'ifpm']);

        $this->actingAs($admin)
            ->from(route('admin.applications.create'))
            ->post(route('admin.applications.store'), [
                'name' => 'Autre', 'slug' => 'ifpm', 'type' => 'application',
                'url' => 'https://autre.lamajestueuse.cm',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_un_slug_vide_qui_entre_en_collision_est_rejete_proprement(): void
    {
        $admin = User::factory()->admin()->create();
        Application::factory()->create(['name' => 'IFPM', 'slug' => 'ifpm']);

        // Slug laisse vide : il est deduit du nom et doit produire une erreur
        // de validation, pas une violation de contrainte en base.
        $this->actingAs($admin)
            ->from(route('admin.applications.create'))
            ->post(route('admin.applications.store'), [
                'name' => 'IFPM', 'type' => 'application',
                'url' => 'https://ifpm-bis.lamajestueuse.cm',
            ])
            ->assertRedirect(route('admin.applications.create'))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseCount('applications', 1);
    }

    public function test_lidentifiant_client_sso_est_unique(): void
    {
        $admin = User::factory()->admin()->create();
        Application::factory()->create(['client_id' => 'ifpm']);

        $this->actingAs($admin)
            ->from(route('admin.applications.create'))
            ->post(route('admin.applications.store'), [
                'name' => 'Autre projet', 'type' => 'application',
                'url' => 'https://autre.lamajestueuse.cm', 'client_id' => 'ifpm',
            ])
            ->assertSessionHasErrors('client_id');
    }

    public function test_modification_du_lien_de_redirection(): void
    {
        $admin = User::factory()->admin()->create();
        $app = Application::factory()->create(['url' => 'https://ancien.lamajestueuse.cm']);

        $this->actingAs($admin)->put(route('admin.applications.update', $app), [
            'name' => $app->name,
            'url' => 'https://nouveau.lamajestueuse.cm',
            'type' => 'application',
            'is_active' => '1',
        ])->assertRedirect(route('admin.applications.index'));

        $this->assertSame('https://nouveau.lamajestueuse.cm', $app->fresh()->url);
    }

    public function test_suppression_dune_application(): void
    {
        $admin = User::factory()->admin()->create();
        $app = Application::factory()->create();

        $this->actingAs($admin)->delete(route('admin.applications.destroy', $app))
            ->assertRedirect(route('admin.applications.index'));

        $this->assertDatabaseMissing('applications', ['id' => $app->id]);
    }

    public function test_affectation_des_acces_a_une_application(): void
    {
        $admin = User::factory()->admin()->create();
        $app = Application::factory()->create();
        $employe = User::factory()->create();
        $autre = User::factory()->create();
        $app->users()->attach($autre);

        $this->actingAs($admin)->put(route('admin.applications.access.update', $app), [
            'users' => [$employe->id],
            'roles' => [$employe->id => 'coordonnateur'],
        ])->assertRedirect();

        $this->assertTrue($app->fresh()->users->contains($employe));
        $this->assertFalse($app->fresh()->users->contains($autre));
        $this->assertSame('coordonnateur', $app->fresh()->users->find($employe->id)->pivot->role_in_app);
    }

    public function test_les_routes_dadministration_sont_liees_par_slug(): void
    {
        // Le front construit ses URL a la main : si la cle de route changeait,
        // tous les liens d'edition tomberaient en 404 sans que rien n'echoue
        // cote serveur. Ce test fige la convention.
        $admin = User::factory()->admin()->create();
        $app = Application::factory()->create(['slug' => 'fondation-medicale']);

        $this->assertSame('slug', $app->getRouteKeyName());
        $this->assertStringEndsWith('/admin/applications/fondation-medicale/edit', route('admin.applications.edit', $app));

        $this->actingAs($admin)->get('/admin/applications/fondation-medicale/edit')->assertOk();
        $this->actingAs($admin)->get("/admin/applications/{$app->id}/edit")->assertNotFound();
    }

    public function test_lecran_de_creation_saffiche(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.applications.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/applications/form')
                ->where('application', null)
                ->has('categories'));
    }
}
