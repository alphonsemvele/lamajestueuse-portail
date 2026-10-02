<?php

namespace Tests\Feature\Modules;

use App\Models\Agent;
use App\Models\CategorieRh;
use App\Models\Contrat;
use App\Models\Echelon;
use App\Models\Employeur;
use App\Models\ProfilSalaire;
use App\Models\User;
use Database\Seeders\GrilleSalarialeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrilleSalarialeTest extends TestCase
{
    use RefreshDatabase;

    private function poser(): void
    {
        $this->seed(GrilleSalarialeSeeder::class);
    }

    private function salaire(int $categorie, string $lettre): float
    {
        return (float) Echelon::whereRelation('categorie', 'libelle', "Catégorie {$categorie}")
            ->where('libelle', $lettre)
            ->value('salaire');
    }

    public function test_la_grille_compte_douze_categories_de_six_echelons(): void
    {
        $this->poser();

        $this->assertSame(12, CategorieRh::count());
        $this->assertSame(72, Echelon::count());

        foreach (range(1, 12) as $numero) {
            $categorie = CategorieRh::where('libelle', "Catégorie {$numero}")->firstOrFail();

            $this->assertSame(
                ['A', 'B', 'C', 'D', 'E', 'F'],
                $categorie->echelons()->orderBy('numero')->pluck('libelle')->all(),
                "Catégorie {$numero}"
            );
        }
    }

    public function test_les_quatre_coins_du_tableau_sont_les_bons(): void
    {
        $this->poser();

        $this->assertSame(60000.0, $this->salaire(1, 'A'));
        $this->assertSame(67500.0, $this->salaire(1, 'F'));
        $this->assertSame(399068.0, $this->salaire(12, 'A'));
        $this->assertSame(457668.0, $this->salaire(12, 'F'));
    }

    /**
     * Les deux cellules qui s'ecartent du pas de leur ligne sont saisies
     * telles que le document les porte : c'est lui qui fait foi.
     */
    public function test_les_deux_cellules_hors_pas_suivent_le_document(): void
    {
        $this->poser();

        $this->assertSame(118070.0, $this->salaire(4, 'F'));
        $this->assertSame(351658.0, $this->salaire(11, 'A'));
    }

    public function test_chaque_ligne_monte_de_a_vers_f(): void
    {
        $this->poser();

        foreach (CategorieRh::with('echelons')->get() as $categorie) {
            $salaires = $categorie->echelons->sortBy('numero')->pluck('salaire')->map(fn ($s) => (float) $s)->all();

            $this->assertSame($salaires, collect($salaires)->sort()->values()->all(), $categorie->libelle);
        }
    }

    public function test_un_echelon_deja_en_place_est_mis_a_jour_sans_perdre_ses_contrats(): void
    {
        // L'extrait d'IUM portait la catégorie 7 échelon F à 20 000.
        $categorie = CategorieRh::create(['libelle' => 'Catégorie 7', 'actif' => true]);
        $echelon = Echelon::create([
            'categorie_rh_id' => $categorie->id,
            'numero' => 1, 'libelle' => 'F', 'salaire' => 20000, 'actif' => true,
        ]);

        $employeur = Employeur::create(['nom' => 'Institut', 'sigle' => 'INS', 'actif' => true]);
        $agent = Agent::create(['user_id' => User::factory()->create()->id]);
        $contrat = Contrat::create([
            'agent_id' => $agent->id, 'employeur_id' => $employeur->id,
            'type' => 'cdi', 'poste' => 'Coordonnateur', 'date_debut' => '2026-01-01',
            'quotite' => 100, 'echelon_id' => $echelon->id, 'statut' => 'actif',
        ]);

        $this->poser();

        // Meme ligne, meme contrat, salaire officiel.
        $this->assertSame($echelon->id, $contrat->fresh()->echelon_id);
        $this->assertSame(183198.0, (float) $echelon->fresh()->salaire);
        $this->assertSame(6, (int) $echelon->fresh()->numero);
    }

    public function test_un_echelon_hors_grille_et_inutilise_est_retire(): void
    {
        $categorie = CategorieRh::create(['libelle' => 'Catégorie 1', 'actif' => true]);
        $echelon = Echelon::create([
            'categorie_rh_id' => $categorie->id,
            'numero' => 2, 'libelle' => 'A (temps partiel)', 'salaire' => 40000, 'actif' => true,
        ]);

        $this->poser();

        $this->assertDatabaseMissing('echelons', ['id' => $echelon->id]);
    }

    public function test_un_echelon_hors_grille_encore_utilise_est_desactive_et_non_supprime(): void
    {
        $categorie = CategorieRh::create(['libelle' => 'Catégorie 1', 'actif' => true]);
        $echelon = Echelon::create([
            'categorie_rh_id' => $categorie->id,
            'numero' => 2, 'libelle' => 'A (temps partiel)', 'salaire' => 40000, 'actif' => true,
        ]);
        ProfilSalaire::create([
            'nom' => 'Chauffeur (temps partiel)',
            'categorie_rh_id' => $categorie->id,
            'echelon_id' => $echelon->id,
            'actif' => true,
        ]);

        $this->poser();

        // Il ne se propose plus a la saisie, mais le profil garde sa reference.
        $this->assertFalse((bool) $echelon->fresh()->actif);
        $this->assertSame($echelon->id, ProfilSalaire::firstOrFail()->echelon_id);
    }

    public function test_une_categorie_hors_grille_et_vide_est_retiree(): void
    {
        $demo = CategorieRh::create(['libelle' => 'Enseignants', 'actif' => true]);

        $this->poser();

        $this->assertDatabaseMissing('categories_rh', ['id' => $demo->id]);
    }

    public function test_une_categorie_hors_grille_qui_porte_encore_un_echelon_est_desactivee(): void
    {
        $demo = CategorieRh::create(['libelle' => 'Enseignants', 'actif' => true]);
        $echelon = Echelon::create([
            'categorie_rh_id' => $demo->id,
            'numero' => 1, 'libelle' => 'Vacataire', 'salaire' => 180000, 'actif' => true,
        ]);
        ProfilSalaire::create([
            'nom' => 'Vacataire', 'categorie_rh_id' => $demo->id,
            'echelon_id' => $echelon->id, 'actif' => true,
        ]);

        $this->poser();

        $this->assertDatabaseHas('categories_rh', ['id' => $demo->id, 'actif' => false]);
    }

    public function test_rejouer_le_seeder_ne_change_rien(): void
    {
        $this->poser();
        $empreinte = Echelon::orderBy('id')->get(['id', 'numero', 'libelle', 'salaire'])->toJson();

        $this->poser();

        $this->assertSame(72, Echelon::count());
        $this->assertSame($empreinte, Echelon::orderBy('id')->get(['id', 'numero', 'libelle', 'salaire'])->toJson());
    }

    public function test_une_grille_posee_puis_corrigee_par_la_rh_nest_pas_ecrasee(): void
    {
        $this->poser();

        $echelon = Echelon::whereRelation('categorie', 'libelle', 'Catégorie 1')
            ->where('libelle', 'A')->firstOrFail();
        $echelon->update(['salaire' => 65000]);

        $this->poser();

        $this->assertSame(65000.0, (float) $echelon->fresh()->salaire);
    }
}
