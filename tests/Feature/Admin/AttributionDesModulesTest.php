<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une tuile ne parait que si elle est attribuee : il faut donc pouvoir la
 * donner a tout le monde d'un geste, pour les modules qui concernent chacun.
 */
class AttributionDesModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_module_se_donne_a_tout_le_personnel_en_service(): void
    {
        $module = Application::factory()->module('badges')->create(['name' => 'Badges']);
        $patron = User::factory()->superadmin()->create();
        $employe = User::factory()->create(['status' => 'active']);
        $suspendu = User::factory()->create(['status' => 'suspended']);

        $this->actingAs($patron)->post(route('admin.modules.attribuer', $module))
            ->assertRedirect()->assertSessionHas('status');

        $this->assertTrue($employe->applications()->where('applications.id', $module->id)->exists());
        $this->assertTrue($patron->applications()->where('applications.id', $module->id)->exists());
        // Un compte suspendu n'a pas de tableau de bord a garnir.
        $this->assertFalse($suspendu->applications()->where('applications.id', $module->id)->exists());
    }

    /** Deux fois de suite ne cree pas de doublon. */
    public function test_une_seconde_attribution_ne_double_rien(): void
    {
        $module = Application::factory()->module('profil')->create(['name' => 'Mon profil']);
        $patron = User::factory()->superadmin()->create();
        $employe = User::factory()->create(['status' => 'active']);

        $this->actingAs($patron)->post(route('admin.modules.attribuer', $module));
        $this->actingAs($patron)->post(route('admin.modules.attribuer', $module));

        $this->assertSame(1, $employe->applications()->where('applications.id', $module->id)->count());
    }

    public function test_seul_le_super_administrateur_attribue(): void
    {
        $module = Application::factory()->module('badges')->create(['name' => 'Badges']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.modules.attribuer', $module))->assertForbidden();
    }

    /** Une application ordinaire n'est pas un module : rien a attribuer ici. */
    public function test_une_application_ordinaire_n_est_pas_concernee(): void
    {
        $institut = Application::factory()->create(['name' => 'IUM']);

        $this->actingAs(User::factory()->superadmin()->create())
            ->post(route('admin.modules.attribuer', $institut))->assertNotFound();
    }
}
