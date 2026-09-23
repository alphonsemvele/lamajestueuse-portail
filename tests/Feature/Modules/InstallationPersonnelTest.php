<?php

namespace Tests\Feature\Modules;

use App\Models\Application;
use App\Models\CategorieRh;
use App\Models\Echelon;
use App\Models\Employeur;
use App\Models\ProfilSalaire;
use App\Models\User;
use Database\Seeders\PersonnelModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le semeur d'installation tourne a chaque deploiement : il doit pouvoir etre
 * rejoue sans rien casser ni rien remettre en place de son propre chef.
 */
class InstallationPersonnelTest extends TestCase
{
    use RefreshDatabase;

    private function installer(): void
    {
        $this->seed(PersonnelModuleSeeder::class);
    }

    public function test_l_installation_cree_la_tuile_et_les_employeurs(): void
    {
        $admin = User::factory()->admin()->create();

        $this->installer();

        $module = Application::where('module_key', 'personnel')->first();
        $this->assertNotNull($module);
        $this->assertSame('module', $module->type);
        $this->assertTrue($module->is_active);

        $this->assertEqualsCanonicalizing(['GSBM', 'IFPM', 'IUM'], Employeur::pluck('sigle')->all());

        // L'administrateur voit la tuile des la premiere installation.
        $this->assertTrue($admin->applications()->where('applications.id', $module->id)->exists());
    }

    public function test_rejouer_l_installation_ne_duplique_rien(): void
    {
        $this->installer();
        $this->installer();
        $this->installer();

        $this->assertSame(1, Application::where('module_key', 'personnel')->count());
        $this->assertSame(3, Employeur::count());
    }

    public function test_une_entite_renseignee_a_la_main_n_est_pas_ecrasee(): void
    {
        $this->installer();

        Employeur::where('sigle', 'IUM')->update([
            'niu' => 'M021700000001',
            'numero_cnps' => 'CNPS-4471',
            'signataire' => 'Le Directeur Général',
        ]);

        $this->installer();

        $ium = Employeur::where('sigle', 'IUM')->firstOrFail();
        $this->assertSame('M021700000001', $ium->niu);
        $this->assertSame('Le Directeur Général', $ium->signataire);
    }

    /** Un administrateur qui s'est retiré la tuile ne se la voit pas remettre. */
    public function test_un_acces_retire_ne_revient_pas_au_deploiement_suivant(): void
    {
        $admin = User::factory()->admin()->create();
        $this->installer();

        $module = Application::where('module_key', 'personnel')->firstOrFail();
        $admin->applications()->detach($module->id);

        $this->installer();

        $this->assertFalse($admin->applications()->where('applications.id', $module->id)->exists());
    }

    public function test_l_installation_n_invente_aucune_grille_salariale(): void
    {
        $this->installer();

        // Les montants réels se saisissent dans l'interface : rien n'est
        // supposé à la place du service RH.
        $this->assertSame(0, CategorieRh::count());
        $this->assertSame(0, Echelon::count());
        $this->assertSame(0, ProfilSalaire::count());
    }
}
