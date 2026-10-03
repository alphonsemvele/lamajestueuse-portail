<?php

namespace Tests\Feature\Modules;

use App\Models\Application;
use App\Models\Employeur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Comptes hors personnel : un administrateur technique entre dans le portail
 * sans figurer dans les dossiers du service RH.
 */
class HorsPersonnelTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    private Application $institut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        $this->institut = Application::factory()->create(['name' => 'IUM']);
        Employeur::create(['nom' => 'Institut Universitaire', 'sigle' => 'IUM', 'application_id' => $this->institut->id]);
    }

    private function gestionnaire(): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $user->employeursRh()->attach(Employeur::firstOrFail());

        return $user;
    }

    private function membre(string $nom, bool $dansLePersonnel = true): User
    {
        $membre = User::factory()->create([
            'lastname' => $nom,
            'matricule' => null,
            'dans_le_personnel' => $dansLePersonnel,
        ]);
        $membre->applications()->attach($this->institut);

        return $membre;
    }

    /** La valeur vient de la base : on relit le compte pour la voir. */
    public function test_un_compte_est_du_personnel_par_defaut(): void
    {
        $this->assertTrue(User::factory()->create()->fresh()->dans_le_personnel);
    }

    public function test_un_compte_hors_personnel_ne_figure_pas_dans_la_liste(): void
    {
        $this->membre('AGENT');
        $this->membre('TECHNIQUE', false);

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('agents.data', 1)
                ->where('agents.data.0.nom', fn ($nom) => str_contains((string) $nom, 'AGENT')));
    }

    public function test_sa_fiche_n_est_pas_consultable(): void
    {
        $technique = $this->membre('TECHNIQUE', false);

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents.show', $technique))
            ->assertNotFound();
    }

    public function test_il_ne_compte_pas_dans_les_effectifs(): void
    {
        $this->membre('AGENT');
        $this->membre('TECHNIQUE', false);

        $this->actingAs($this->gestionnaire())->get(route('personnel.index'))
            ->assertInertia(fn (Assert $page) => $page->where('chiffres.agents', 1));
    }

    public function test_il_ne_recoit_pas_de_matricule(): void
    {
        $technique = $this->membre('TECHNIQUE', false);
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->getJson(route('personnel.matricules.apourvoir'))
            ->assertOk()
            ->assertJsonMissing(['id' => $technique->id]);

        $this->actingAs($gestionnaire)
            ->post(route('personnel.matricules.attribuer'), ['personnes' => [$technique->id]])
            ->assertSessionHasErrors('matricules');

        $this->assertNull($technique->refresh()->matricule);
    }

    public function test_il_ne_figure_pas_dans_le_fichier_exporte(): void
    {
        $this->membre('AGENT');
        $this->membre('TECHNIQUE', false);

        $csv = $this->actingAs($this->gestionnaire())->get(route('personnel.export'))->streamedContent();

        $this->assertStringContainsString('AGENT', $csv);
        $this->assertStringNotContainsString('TECHNIQUE', $csv);
    }

    // ------------------------------------------------- côté administration

    public function test_l_administrateur_marque_un_compte_hors_personnel(): void
    {
        $technique = User::factory()->create(['dans_le_personnel' => true]);

        $this->actingAs(User::factory()->superadmin()->create())->put(route('admin.users.update', $technique), [
            'name' => $technique->name,
            'role' => 'superadmin',
            'status' => 'active',
            'locale' => 'fr',
            'dans_le_personnel' => false,
        ])->assertRedirect();

        $this->assertFalse($technique->refresh()->dans_le_personnel);
    }

    public function test_le_marqueur_remonte_a_l_interface(): void
    {
        $technique = User::factory()->create(['dans_le_personnel' => false]);

        $this->actingAs(User::factory()->superadmin()->create())->get(route('admin.users.edit', $technique))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('user.dansLePersonnel', false));
    }

    /** Il garde son accès au portail : seule sa visibilité RH change. */
    public function test_il_garde_son_acces_au_portail(): void
    {
        $technique = User::factory()->create(['dans_le_personnel' => false, 'status' => 'active']);
        $technique->applications()->attach($this->institut);

        $this->actingAs($technique)->get(route('dashboard'))->assertOk();
    }
}
