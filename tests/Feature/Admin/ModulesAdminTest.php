<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * L'administration des modules : ce que le code declare, ce qui est en
 * service, et par ou chaque module s'administre.
 */
class ModulesAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_un_employe_n_entre_pas_dans_l_administration(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.modules.index'))->assertForbidden();
    }

    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        // actingAs vaut pour tout le test : le visiteur a le sien.
        $this->get(route('admin.modules.index'))->assertRedirect(route('login'));
    }

    public function test_tous_les_modules_declares_sont_listes(): void
    {
        $this->actingAs($this->admin())->get(route('admin.modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/modules/index')
                ->has('modules', count(config('modules')))
                ->where('modules.0.cle', array_key_first(config('modules'))));
    }

    public function test_un_module_declare_mais_sans_tuile_est_signale(): void
    {
        $this->actingAs($this->admin())->get(route('admin.modules.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('modules', fn ($modules) => collect($modules)->every(fn ($m) => $m['pose'] === false)));
    }

    public function test_un_module_pose_remonte_son_etat_et_ses_acces(): void
    {
        $module = Application::factory()->module('badges')->create(['name' => 'Badges']);
        $employe = User::factory()->create();
        $employe->applications()->attach($module);

        $this->actingAs($this->admin())->get(route('admin.modules.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('modules', fn ($modules) => collect($modules)
                    ->firstWhere('cle', 'badges')['pose'] === true))
            ->assertInertia(fn (Assert $page) => $page
                ->where('modules', fn ($modules) => collect($modules)
                    ->firstWhere('cle', 'badges')['acces'] === 1))
            ->assertInertia(fn (Assert $page) => $page
                ->where('modules', fn ($modules) => collect($modules)
                    ->firstWhere('cle', 'badges')['ouvertATous'] === true));
    }

    public function test_le_lien_d_administration_pointe_sur_l_ecran_du_module(): void
    {
        Application::factory()->module('badges')->create(['name' => 'Badges']);

        $this->actingAs($this->admin())->get(route('admin.modules.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('modules', fn ($modules) => str_ends_with(
                    (string) collect($modules)->firstWhere('cle', 'badges')['lienAdmin'],
                    '/badges/gestion',
                )));
    }

    /** Un module retiré ne s'ouvre plus : on ne propose pas le lien. */
    public function test_un_module_retire_ne_propose_plus_son_lien(): void
    {
        Application::factory()->module('badges')->inactive()->create(['name' => 'Badges']);

        $this->actingAs($this->admin())->get(route('admin.modules.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('modules', fn ($modules) => collect($modules)
                    ->firstWhere('cle', 'badges')['lienModule'] === null));
    }

    public function test_l_administrateur_retire_puis_remet_un_module(): void
    {
        $module = Application::factory()->module('badges')->create(['name' => 'Badges']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.modules.toggle', $module))->assertRedirect();
        $this->assertFalse($module->refresh()->is_active);

        $this->actingAs($admin)->post(route('admin.modules.toggle', $module))->assertRedirect();
        $this->assertTrue($module->refresh()->is_active);
    }

    public function test_on_ne_bascule_pas_une_application_ordinaire(): void
    {
        $application = Application::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.modules.toggle', $application))->assertNotFound();
        $this->assertTrue($application->refresh()->is_active);
    }

    /** Une tuile dont le code ne déclare plus la clé ne mène nulle part. */
    public function test_les_tuiles_orphelines_sont_signalees(): void
    {
        Application::factory()->module('supprime-du-code')->create(['name' => 'Ancien module']);

        $this->actingAs($this->admin())->get(route('admin.modules.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('orphelins', 1)
                ->where('orphelins.0.name', 'Ancien module'));
    }
}
