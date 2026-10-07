<?php

namespace Tests\Feature\Modules;

use App\Models\Agent;
use App\Models\Application;
use App\Models\CategorieRh;
use App\Models\Contrat;
use App\Models\Echelon;
use App\Models\Employeur;
use App\Models\Indemnite;
use App\Models\ProfilSalaire;
use App\Models\Retenue;
use App\Models\User;
use App\Services\PaieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le net affiche sur un profil doit etre celui que le bulletin produira :
 * c'est le meme moteur, et ces essais le verrouillent.
 */
class ApercuProfilTest extends TestCase
{
    use RefreshDatabase;

    private Echelon $echelon;

    private PaieService $paie;

    protected function setUp(): void
    {
        parent::setUp();

        $categorie = CategorieRh::create(['libelle' => 'Catégorie 7', 'actif' => true]);
        $this->echelon = Echelon::create([
            'categorie_rh_id' => $categorie->id,
            'numero' => 1, 'libelle' => 'A', 'salaire' => 200000, 'actif' => true,
        ]);
        $this->paie = app(PaieService::class);

        Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
    }

    private function profil(array $indemnites = [], array $retenues = []): ProfilSalaire
    {
        $profil = ProfilSalaire::create([
            'nom' => 'Profil',
            'categorie_rh_id' => $this->echelon->categorie_rh_id,
            'echelon_id' => $this->echelon->id,
            'actif' => true,
        ]);

        foreach ($indemnites as $libelle => [$type, $valeur]) {
            $profil->indemnites()->attach(
                Indemnite::create(['libelle' => $libelle, 'imposable' => true, 'actif' => true])->id,
                ['type_calcul' => $type, 'valeur' => $valeur]
            );
        }

        foreach ($retenues as $libelle => [$type, $valeur]) {
            $profil->retenues()->attach(
                Retenue::create(['libelle' => $libelle, 'actif' => true])->id,
                ['type_calcul' => $type, 'valeur' => $valeur]
            );
        }

        return $profil->fresh(['echelon', 'indemnites', 'retenues']);
    }

    public function test_un_profil_sans_ligne_verse_son_salaire_de_base(): void
    {
        $apercu = $this->paie->apercuProfil($this->profil());

        $this->assertSame(200000.0, $apercu['salaire_base']);
        $this->assertSame(0.0, $apercu['total_indemnites']);
        $this->assertSame(0.0, $apercu['total_retenues']);
        $this->assertSame(200000.0, $apercu['salaire_net']);
    }

    public function test_les_montants_fixes_s_ajoutent_et_se_retranchent(): void
    {
        $apercu = $this->paie->apercuProfil($this->profil(
            ['Transport' => ['fixe', 30000], 'Logement' => ['fixe', 25000]],
            ['Avance' => ['fixe', 10000]],
        ));

        $this->assertSame(55000.0, $apercu['total_indemnites']);
        $this->assertSame(10000.0, $apercu['total_retenues']);
        $this->assertSame(245000.0, $apercu['salaire_net']);
    }

    public function test_un_pourcentage_porte_sur_le_salaire_de_base(): void
    {
        $apercu = $this->paie->apercuProfil($this->profil(
            ['Technicité' => ['pourcentage', 12.5]],
            ['CNPS' => ['pourcentage', 4.2]],
        ));

        $this->assertSame(25000.0, $apercu['total_indemnites']); // 12,5 % de 200 000
        $this->assertSame(8400.0, $apercu['total_retenues']);    // 4,2 % de 200 000
        $this->assertSame(216600.0, $apercu['salaire_net']);
    }

    public function test_un_profil_sans_echelon_ne_fait_pas_tomber_l_ecran(): void
    {
        $profil = ProfilSalaire::create(['nom' => 'Sans échelon', 'actif' => true]);

        $apercu = $this->paie->apercuProfil($profil);

        $this->assertSame(0.0, $apercu['salaire_base']);
        $this->assertSame(0.0, $apercu['salaire_net']);
    }

    /** Le net lu sur le profil est celui que le bulletin porte. */
    public function test_l_apercu_tombe_sur_le_meme_net_que_le_bulletin(): void
    {
        $profil = $this->profil(
            ['Transport' => ['fixe', 30000], 'Technicité' => ['pourcentage', 10]],
            ['CNPS' => ['pourcentage', 4.2]],
        );

        $employeur = Employeur::create(['nom' => 'Institut', 'sigle' => 'INS', 'actif' => true]);
        $contrat = Contrat::create([
            'agent_id' => Agent::create(['user_id' => User::factory()->create()->id])->id,
            'employeur_id' => $employeur->id,
            'type' => 'cdi', 'poste' => 'Enseignant', 'date_debut' => '2026-01-01',
            'quotite' => 100, 'profil_salaire_id' => $profil->id,
            'echelon_id' => $this->echelon->id, 'statut' => 'actif',
        ]);

        $this->assertSame(
            $this->paie->calculer($contrat, 9, 2026)['salaire_net'],
            $this->paie->apercuProfil($profil)['salaire_net'],
        );
    }

    // ------------------------------------------------------------- export

    /**
     * Le fichier doit dire la meme chose que l'ecran : meme net, et le detail
     * des lignes en clair. Un export qui recalculerait de son cote finirait
     * par dire autre chose que les bulletins.
     */
    public function test_la_rh_exporte_les_profils_en_tableur(): void
    {
        $this->profil(
            ['Transport' => ['fixe', 30000], 'Technicité' => ['pourcentage', 12.5]],
            ['Avance' => ['fixe', 5000]],
        );

        $reponse = $this->actingAs($this->gestionnaire())->get(route('personnel.profils.export'));

        $reponse->assertOk();
        $reponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $reponse->streamedContent();

        // L'en-tete, le net, et le detail lisible de chaque ligne.
        $this->assertStringContainsString('Salaire net', $csv);
        $this->assertStringContainsString('Transport : 30 000', $csv);
        $this->assertStringContainsString('Technicité : 12,5 % sur le salaire de base = 25 000', $csv);
        $this->assertStringContainsString('Avance : 5 000', $csv);

        // 200 000 + 30 000 + 25 000 - 5 000
        $this->assertStringContainsString('250000', $csv);
    }

    /** Le fichier s'ouvre dans Excel sans charabia : il porte le marqueur UTF-8. */
    public function test_l_export_s_ouvre_dans_excel(): void
    {
        $this->profil();

        $csv = $this->actingAs($this->gestionnaire())
            ->get(route('personnel.profils.export'))->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(';', $csv);
    }

    public function test_un_employe_n_exporte_pas_les_profils(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'active']))
            ->get(route('personnel.profils.export'))->assertForbidden();
    }

    private function gestionnaire(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
    }

    public function test_l_ecran_des_profils_porte_le_net(): void
    {
        $this->profil(['Transport' => ['fixe', 30000]], ['Avance' => ['fixe', 5000]]);

        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);

        $this->actingAs($admin)->get(route('personnel.profils'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('profils.0.salaireBase', 200000)
                ->where('profils.0.totalIndemnites', 30000)
                ->where('profils.0.totalRetenues', 5000)
                ->where('profils.0.salaireNet', 225000));
    }
}
