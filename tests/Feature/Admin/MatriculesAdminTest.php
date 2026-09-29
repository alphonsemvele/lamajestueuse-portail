<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Attribution des matricules depuis l'administration du portail : on coche
 * plusieurs comptes, on attribue.
 */
class MatriculesAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create(['matricule' => 'LM-00001']);
    }

    private function compte(string $nom, ?string $matricule = null): User
    {
        return User::factory()->create(['lastname' => $nom, 'name' => 'Claire', 'matricule' => $matricule]);
    }

    public function test_la_liste_annonce_ceux_qui_attendent_et_le_prochain_numero(): void
    {
        $this->compte('NKOA');
        $this->compte('ATANGANA');
        $this->compte('DEJA', 'LM-00004');

        $this->actingAs($this->admin())->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sansMatriculeCount', 2)
                ->where('prochainMatricule', 'LM-00005'));
    }

    public function test_le_filtre_isole_les_comptes_sans_matricule(): void
    {
        $this->compte('NKOA');
        $this->compte('DEJA', 'LM-00004');

        $this->actingAs($this->admin())->get(route('admin.users.index', ['sans_matricule' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('filters.sansMatricule', true));
    }

    public function test_plusieurs_comptes_recoivent_leur_matricule_d_un_coup(): void
    {
        $nkoa = $this->compte('NKOA');
        $atangana = $this->compte('ATANGANA');
        $mvele = $this->compte('MVELE');

        $this->actingAs($this->admin())->post(route('admin.users.matricules'), [
            'users' => [$nkoa->id, $atangana->id, $mvele->id],
        ])->assertRedirect()->assertSessionHas('status');

        // Ordre alphabetique, a la suite du plus haut numero existant.
        $this->assertSame('LM-00002', $atangana->refresh()->matricule);
        $this->assertSame('LM-00003', $mvele->refresh()->matricule);
        $this->assertSame('LM-00004', $nkoa->refresh()->matricule);
    }

    public function test_un_compte_deja_matricule_est_laisse_tel_quel(): void
    {
        $deja = $this->compte('DEJA', 'LM-00009');
        $sans = $this->compte('SANS');

        $this->actingAs($this->admin())->post(route('admin.users.matricules'), [
            'users' => [$deja->id, $sans->id],
        ])->assertRedirect();

        $this->assertSame('LM-00009', $deja->refresh()->matricule);
        $this->assertSame('LM-00010', $sans->refresh()->matricule);
    }

    public function test_une_selection_sans_rien_a_faire_le_dit(): void
    {
        $deja = $this->compte('DEJA', 'LM-00009');

        $this->actingAs($this->admin())->post(route('admin.users.matricules'), ['users' => [$deja->id]])
            ->assertSessionHasErrors('matricules');
    }

    public function test_une_selection_vide_est_refusee(): void
    {
        $this->actingAs($this->admin())->post(route('admin.users.matricules'), ['users' => []])
            ->assertSessionHasErrors('users');
    }

    public function test_seul_un_administrateur_attribue(): void
    {
        $compte = $this->compte('NKOA');

        $this->actingAs(User::factory()->create())->post(route('admin.users.matricules'), ['users' => [$compte->id]])
            ->assertForbidden();

        $this->assertNull($compte->refresh()->matricule);
    }
}
