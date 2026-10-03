<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Super administrateur et administrateur.
 *
 * Une seule difference les separe : le tableau de bord de l'administration.
 * Dans le portail et dans les modules, ils peuvent exactement la meme chose.
 */
class RoleSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_super_administrateur_ouvre_l_administration(): void
    {
        $this->actingAs(User::factory()->superadmin()->create())
            ->get(route('admin.dashboard'))->assertOk();
    }

    public function test_l_administrateur_n_ouvre_pas_l_administration(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertForbidden();
    }

    /** Tout le reste lui reste ouvert : c'est le meme pouvoir qu'avant. */
    public function test_l_administrateur_garde_les_modules_et_le_perimetre(): void
    {
        Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        $admin = User::factory()->admin()->create();

        // Aucune attribution, et pourtant le module s'ouvre : il administre.
        $this->actingAs($admin)->get(route('personnel.index'))->assertOk();

        // Perimetre RH sans limite, comme un super administrateur.
        $this->assertNull($admin->perimetreRh());
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isSuperAdmin());
    }

    /** Le lien vers l'administration ne se propose qu'a qui peut l'ouvrir. */
    public function test_le_portail_ne_propose_l_administration_qu_au_super_administrateur(): void
    {
        $this->actingAs(User::factory()->superadmin()->create())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.isSuperAdmin', true));

        $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.isSuperAdmin', false)
                ->where('auth.user.isAdmin', true));
    }

    public function test_le_role_de_super_administrateur_s_attribue_depuis_la_fiche(): void
    {
        $patron = User::factory()->superadmin()->create();
        $employe = User::factory()->create(['status' => 'active']);

        $this->actingAs($patron)->put(route('admin.users.update', $employe), [
            'name' => $employe->name,
            'email' => $employe->email,
            'role' => 'superadmin',
            'status' => 'active',
            'locale' => 'fr',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($employe->refresh()->isSuperAdmin());
    }

    /**
     * Sans cette garde, le dernier super administrateur pourrait se fermer
     * la porte : plus personne pour la rouvrir.
     */
    public function test_on_ne_se_retire_pas_son_propre_role(): void
    {
        $patron = User::factory()->superadmin()->create();

        $this->actingAs($patron)->put(route('admin.users.update', $patron), [
            'name' => $patron->name,
            'email' => $patron->email,
            'role' => 'admin',
            'status' => 'active',
            'locale' => 'fr',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($patron->refresh()->isSuperAdmin());
    }
}
