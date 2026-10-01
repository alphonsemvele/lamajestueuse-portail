<?php

namespace Tests\Feature\Modules;

use App\Models\Application;
use App\Models\CategorieRh;
use App\Models\Echelon;
use App\Models\Employeur;
use App\Models\ProfilSalaire;
use App\Models\User;
use Database\Seeders\ModulesPortailSeeder;
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
        $this->seed(ModulesPortailSeeder::class);
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

    public function test_l_installation_pose_aussi_le_module_badges(): void
    {
        $this->installer();

        $badges = Application::where('module_key', 'badges')->first();
        $this->assertNotNull($badges);
        $this->assertTrue($badges->estOuvertATous());
    }

    public function test_l_installation_pose_l_adresse_d_envoi_du_groupe(): void
    {
        $this->installer();

        $reglages = \App\Models\ReglageEmail::firstOrFail();
        $this->assertSame('info@lamajestueuse.com', $reglages->expediteur);
        // Inactifs : le relais se renseigne dans /admin/email.
        $this->assertFalse($reglages->actif);
    }

    public function test_l_installation_n_ecrase_pas_des_reglages_saisis(): void
    {
        $this->installer();

        \App\Models\ReglageEmail::firstOrFail()->update([
            'actif' => true, 'hote' => 'smtp-relay.brevo.com', 'expediteur' => 'contact@lamajestueuse.com',
        ]);

        $this->installer();

        $reglages = \App\Models\ReglageEmail::firstOrFail();
        $this->assertSame('contact@lamajestueuse.com', $reglages->expediteur);
        $this->assertTrue($reglages->actif);
        $this->assertSame(1, \App\Models\ReglageEmail::count());
    }

    public function test_l_installation_pose_le_module_des_bulletins(): void
    {
        $this->installer();

        $bulletins = Application::where('module_key', 'bulletins')->first();
        $this->assertNotNull($bulletins);
        $this->assertTrue($bulletins->estOuvertATous());
    }

    public function test_rejouer_l_installation_ne_duplique_rien(): void
    {
        $this->installer();
        $this->installer();
        $this->installer();

        $this->assertSame(1, Application::where('module_key', 'personnel')->count());
        $this->assertSame(1, Application::where('module_key', 'badges')->count());
        $this->assertSame(1, Application::where('module_key', 'bulletins')->count());
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

    /** La grille reprise d'IUM, telle qu'elle y était configurée. */
    public function test_l_installation_reprend_la_grille_d_ium(): void
    {
        $this->installer();

        $this->assertSame(8, CategorieRh::count());
        $this->assertSame(11, Echelon::count());
        $this->assertSame(11, ProfilSalaire::count());
        $this->assertSame(5, \App\Models\Indemnite::count());

        // IUM n'avait aucune retenue : le net s'y calcule base + indemnités.
        $this->assertSame(0, \App\Models\Retenue::count());

        $coordonnateur = ProfilSalaire::where('nom', 'Coordonnateur de filière')->firstOrFail();
        $this->assertSame(20000.0, (float) $coordonnateur->echelon->salaire);
        $this->assertCount(4, $coordonnateur->indemnites);

        $transport = $coordonnateur->indemnites->firstWhere('libelle', 'Indemnité de transport');
        $this->assertSame('fixe', $transport->pivot->type_calcul);
        $this->assertSame(183198.0, (float) $transport->pivot->valeur);
    }

    /** Une grille déjà saisie n'est jamais remplacée. */
    public function test_l_installation_ne_touche_pas_a_une_grille_existante(): void
    {
        $categorie = CategorieRh::create(['libelle' => 'Grille maison']);
        Echelon::create(['categorie_rh_id' => $categorie->id, 'numero' => 1, 'salaire' => 123456]);

        $this->installer();

        $this->assertSame(1, CategorieRh::count());
        $this->assertSame(123456.0, (float) Echelon::firstOrFail()->salaire);
        $this->assertSame(0, ProfilSalaire::count());
    }
}
