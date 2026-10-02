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

    /**
     * L'installation finit sur la grille officielle du groupe, et garde tout
     * ce qu'IUM apportait d'autre : indemnités, retenues et profils salaire
     * ne sont jamais supprimés — seuls catégories et échelons sont adaptés.
     */
    /**
     * Le deploiement rejoue ce seeder : il ne doit pas defaire les reglages
     * de l'administration.
     */
    public function test_une_tuile_desactivee_le_reste_apres_une_mise_a_jour(): void
    {
        $this->installer();

        $tuile = Application::where('module_key', 'tutoriels')->firstOrFail();
        $tuile->update(['is_active' => false]);

        $this->installer();

        $this->assertFalse((bool) $tuile->fresh()->is_active);
    }

    public function test_un_nom_une_couleur_et_un_ordre_choisis_sont_conserves(): void
    {
        $this->installer();

        $tuile = Application::where('module_key', 'badges')->firstOrFail();
        $tuile->update(['name' => 'Cartes professionnelles', 'color' => '#123456', 'sort_order' => 42]);

        $this->installer();

        $tuile->refresh();

        $this->assertSame('Cartes professionnelles', $tuile->name);
        $this->assertSame('#123456', $tuile->color);
        $this->assertSame(42, (int) $tuile->sort_order);
    }

    /** Ce qui decrit le module, lui, reste maintenu. */
    public function test_les_traits_structurels_sont_maintenus(): void
    {
        $this->installer();

        $tuile = Application::where('module_key', 'badges')->firstOrFail();
        $tuile->update(['type' => 'application', 'url' => 'https://ailleurs.example']);

        $this->installer();

        $tuile->refresh();

        $this->assertSame('module', $tuile->type);
        $this->assertNull($tuile->url);
    }

    public function test_l_installation_pose_la_grille_officielle(): void
    {
        $this->installer();

        $this->assertSame(12, CategorieRh::count());

        // Les 72 cases du tableau, plus l'échelon « temps partiel » d'IUM,
        // gardé désactivé parce qu'un profil le vise encore.
        $this->assertSame(73, Echelon::count());
        $this->assertSame(72, Echelon::where('actif', true)->count());

        foreach (range(1, 12) as $numero) {
            $this->assertSame(
                ['A', 'B', 'C', 'D', 'E', 'F'],
                CategorieRh::where('libelle', "Catégorie {$numero}")->firstOrFail()
                    ->echelons()->where('actif', true)->orderBy('numero')->pluck('libelle')->all(),
                "Catégorie {$numero}"
            );
        }
    }

    /** Les profils, indemnités et retenues d'IUM traversent l'installation. */
    public function test_l_installation_ne_supprime_aucun_profil_ni_indemnite(): void
    {
        $this->installer();

        $this->assertSame(11, ProfilSalaire::count());
        $this->assertSame(5, \App\Models\Indemnite::count());

        // IUM n'avait aucune retenue : le net s'y calcule base + indemnités.
        $this->assertSame(0, \App\Models\Retenue::count());

        // Chaque profil garde l'échelon qu'il visait.
        $this->assertSame(0, ProfilSalaire::whereNull('echelon_id')->count());

        $coordonnateur = ProfilSalaire::where('nom', 'Coordonnateur de filière')->firstOrFail();
        $this->assertCount(4, $coordonnateur->indemnites);

        $transport = $coordonnateur->indemnites->firstWhere('libelle', 'Indemnité de transport');
        $this->assertSame('fixe', $transport->pivot->type_calcul);
        $this->assertSame(183198.0, (float) $transport->pivot->valeur);

        // Son échelon passe des 20 000 erronés d'IUM au salaire du tableau.
        $this->assertSame(183198.0, (float) $coordonnateur->echelon->salaire);
    }

    /**
     * Une catégorie saisie à la main et devenue inutile est retirée, mais
     * celle qui porte encore un profil est seulement désactivée : le profil
     * survit intact.
     */
    public function test_une_grille_maison_cede_la_place_sans_emporter_ses_profils(): void
    {
        $inutilisee = CategorieRh::create(['libelle' => 'Grille maison']);
        Echelon::create(['categorie_rh_id' => $inutilisee->id, 'numero' => 1, 'salaire' => 123456]);

        $portante = CategorieRh::create(['libelle' => 'Grille maison (en service)']);
        $echelon = Echelon::create(['categorie_rh_id' => $portante->id, 'numero' => 1, 'salaire' => 654321]);
        $profil = ProfilSalaire::create([
            'nom' => 'Profil maison',
            'categorie_rh_id' => $portante->id,
            'echelon_id' => $echelon->id,
            'actif' => true,
        ]);

        $this->installer();

        $this->assertDatabaseMissing('categories_rh', ['id' => $inutilisee->id]);

        $this->assertDatabaseHas('categories_rh', ['id' => $portante->id, 'actif' => false]);
        $this->assertDatabaseHas('echelons', ['id' => $echelon->id, 'actif' => false]);
        $this->assertSame(654321.0, (float) $echelon->fresh()->salaire);

        $this->assertDatabaseHas('profils_salaire', ['id' => $profil->id, 'echelon_id' => $echelon->id]);
    }
}
