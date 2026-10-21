<?php

namespace Tests\Feature\Modules;

use App\Models\Application;
use App\Models\Post;
use App\Models\Rubrique;
use App\Models\User;
use Database\Seeders\ModulesPortailSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InformationModuleTest extends TestCase
{
    use RefreshDatabase;

    private function module(): Application
    {
        return Application::factory()->module()->create(['name' => "Centre d'information"]);
    }

    public function test_un_module_apparait_parmi_les_applications_du_tableau_de_bord(): void
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module());

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('apps', 1)
                ->where('apps.0.type', 'module')
                ->where('apps.0.name', "Centre d'information"));
    }

    public function test_ouvrir_un_module_reste_dans_le_portail(): void
    {
        $user = User::factory()->create();
        $module = $this->module();
        $user->applications()->attach($module);

        // Une application metier sort vers un site exterieur ; un module non.
        $this->actingAs($user)->get(route('applications.open', $module))
            ->assertRedirect(route('informations.index'));
    }

    public function test_le_module_est_inaccessible_sans_la_tuile(): void
    {
        $this->module();

        $this->actingAs(User::factory()->create())->get(route('informations.index'))->assertForbidden();
    }

    public function test_un_lecteur_consulte_mais_ne_publie_pas(): void
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module(), ['role_in_app' => null]);

        $this->actingAs($user)->get(route('informations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/informations/index')
                ->where('canManage', false));

        $this->actingAs($user)->get(route('informations.create'))->assertForbidden();
        $this->actingAs($user)->post(route('informations.store'), ['title' => 'Tentative', 'type' => 'news'])
            ->assertForbidden();
    }

    /**
     * Le deploiement installe les modules du portail. Sans la tuile, la
     * salle de presse n'existe pour personne : le module repond 404 et son
     * site public n'a plus de redaction.
     */
    public function test_le_deploiement_installe_la_tuile_de_la_salle_de_presse(): void
    {
        $patron = User::factory()->superadmin()->create();

        $this->seed(ModulesPortailSeeder::class);

        $tuile = Application::where('module_key', 'informations')->first();

        $this->assertNotNull($tuile, "La tuile LA MAJESTUEUSE Info n'a pas ete posee.");
        $this->assertSame('module', $tuile->type);
        $this->assertTrue($tuile->is_active);

        // Elle est confiee aux administrateurs : ils la voient le jour meme.
        $this->assertTrue($patron->applications()->where('applications.id', $tuile->id)->exists());

        $this->actingAs($patron)->get(route('informations.index'))->assertOk();

        // Et le deploiement suivant ne la remet pas deux fois.
        $this->seed(ModulesPortailSeeder::class);
        $this->assertSame(1, Application::where('module_key', 'informations')->count());
    }

    public function test_un_redacteur_publie_dans_le_module(): void
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module(), ['role_in_app' => 'redacteur']);

        $this->actingAs($user)->get(route('informations.create'))->assertOk();

        $this->actingAs($user)->post(route('informations.store'), [
            'title' => 'Réunion de rentrée',
            'excerpt' => 'Le lundi à 8h.',
            'rubrique_id' => Rubrique::where('slug', 'annonces')->value('id'),
            'published_at' => now()->toDateTimeString(),
        ])->assertRedirect(route('informations.edit', Post::firstOrFail()));

        $post = Post::firstOrFail();
        $this->assertSame('Réunion de rentrée', $post->title);
        $this->assertSame($user->id, $post->author_id);
        // La famille de la rubrique garnit l'ancienne colonne « type ».
        $this->assertSame('announcement', $post->type);
    }

    public function test_un_administrateur_du_portail_publie_toujours(): void
    {
        $admin = User::factory()->superadmin()->create();
        $admin->applications()->attach($this->module());

        $this->actingAs($admin)->get(route('informations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', true));
    }

    public function test_les_brouillons_ne_sont_visibles_que_des_redacteurs(): void
    {
        $module = $this->module();
        Post::create(['title' => 'Publiée', 'slug' => 'publiee', 'type' => 'news', 'published_at' => now()->subDay()]);
        Post::create(['title' => 'Brouillon', 'slug' => 'brouillon', 'type' => 'news', 'published_at' => null]);

        $lecteur = User::factory()->create();
        $lecteur->applications()->attach($module);
        $this->actingAs($lecteur)->get(route('informations.index'))
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 1));

        $redacteur = User::factory()->create();
        $redacteur->applications()->attach($module, ['role_in_app' => 'editeur']);
        $this->actingAs($redacteur)->get(route('informations.index'))
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 2));
    }

    public function test_la_recherche_et_le_filtre_par_rubrique(): void
    {
        $module = $this->module();
        $actualites = Rubrique::where('slug', 'actualites')->value('id');
        $annonces = Rubrique::where('slug', 'annonces')->value('id');

        Post::create(['title' => 'Rentrée académique', 'slug' => 'a', 'type' => 'news',
            'rubrique_id' => $actualites, 'published_at' => now()]);
        Post::create(['title' => 'Maintenance ERP', 'slug' => 'b', 'type' => 'announcement',
            'rubrique_id' => $annonces, 'published_at' => now()]);

        $user = User::factory()->create();
        $user->applications()->attach($module);

        $this->actingAs($user)->get(route('informations.index', ['rubrique' => 'annonces']))
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 1)->where('posts.data.0.title', 'Maintenance ERP'));

        $this->actingAs($user)->get(route('informations.index', ['q' => 'Rentrée']))
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 1)->where('posts.data.0.title', 'Rentrée académique'));
    }

    public function test_ladministrateur_declare_un_module_sans_url(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->post(route('admin.applications.store'), [
            'name' => "Centre d'information",
            'type' => 'module',
            'module_key' => 'informations',
            'is_active' => '1',
        ])->assertRedirect(route('admin.applications.index'));

        $module = Application::firstOrFail();
        $this->assertSame('module', $module->type);
        $this->assertNull($module->url);
        $this->assertSame(route('informations.index'), $module->destination());
    }

    public function test_un_module_exige_une_cle_connue(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->from(route('admin.applications.create'))
            ->post(route('admin.applications.store'), ['name' => 'Sans clé', 'type' => 'module', 'is_active' => '1'])
            ->assertSessionHasErrors('module_key');

        $this->actingAs($admin)->from(route('admin.applications.create'))
            ->post(route('admin.applications.store'), [
                'name' => 'Clé inconnue', 'type' => 'module', 'module_key' => 'inexistant', 'is_active' => '1',
            ])
            ->assertSessionHasErrors('module_key');
    }

    public function test_une_application_metier_sans_lien_reste_sans_destination(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->from(route('admin.applications.create'))
            ->post(route('admin.applications.store'), ['name' => 'Sans lien', 'type' => 'application', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        // Pas de lien : la tuile annoncera « bientôt disponible » au lieu d'ouvrir.
        $this->assertNull(Application::where('slug', 'sans-lien')->sole()->destination());
    }
}
