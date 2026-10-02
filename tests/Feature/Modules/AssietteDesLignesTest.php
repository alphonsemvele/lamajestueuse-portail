<?php

namespace Tests\Feature\Modules;

use App\Models\Agent;
use App\Models\Application;
use App\Models\Bulletin;
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
 * Une ligne de profil peut se calculer sur une autre ligne plutot que sur le
 * salaire de base : une retenue assise sur une indemnite, par exemple.
 */
class AssietteDesLignesTest extends TestCase
{
    use RefreshDatabase;

    private Employeur $employeur;

    private Echelon $echelon;

    private PaieService $paie;

    protected function setUp(): void
    {
        parent::setUp();

        Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        $this->employeur = Employeur::create(['nom' => 'Institut', 'sigle' => 'INS', 'actif' => true]);

        $categorie = CategorieRh::create(['libelle' => 'Catégorie 1', 'actif' => true]);
        $this->echelon = Echelon::create([
            'categorie_rh_id' => $categorie->id,
            'numero' => 1, 'libelle' => 'A', 'salaire' => 200000, 'actif' => true,
        ]);

        $this->paie = app(PaieService::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function profil(): ProfilSalaire
    {
        return ProfilSalaire::create([
            'nom' => 'Profil',
            'categorie_rh_id' => $this->echelon->categorie_rh_id,
            'echelon_id' => $this->echelon->id,
            'actif' => true,
        ]);
    }

    // ------------------------------------------------------------ le calcul

    public function test_une_retenue_se_calcule_sur_une_indemnite(): void
    {
        $profil = $this->profil();
        $transport = Indemnite::create(['libelle' => 'Transport', 'imposable' => true, 'actif' => true]);
        $part = Retenue::create(['libelle' => 'Part salariale', 'actif' => true]);

        // Transport : 50 000 fixe. Part salariale : 10 % du transport.
        $profil->indemnites()->attach($transport->id, ['type_calcul' => 'fixe', 'valeur' => 50000]);
        $profil->retenues()->attach($part->id, [
            'type_calcul' => 'pourcentage', 'valeur' => 10,
            'base_calcul' => 'indemnite:'.$transport->id,
        ]);

        $apercu = $this->paie->apercuProfil($profil->fresh());

        $this->assertSame(50000.0, $apercu['total_indemnites']);
        $this->assertSame(5000.0, $apercu['total_retenues']);   // 10 % de 50 000, pas de 200 000
        $this->assertSame(245000.0, $apercu['salaire_net']);
    }

    public function test_une_indemnite_se_calcule_sur_une_autre_indemnite(): void
    {
        $profil = $this->profil();
        $logement = Indemnite::create(['libelle' => 'Logement', 'imposable' => true, 'actif' => true]);
        $charges = Indemnite::create(['libelle' => 'Charges locatives', 'imposable' => true, 'actif' => true]);

        $profil->indemnites()->attach($logement->id, ['type_calcul' => 'pourcentage', 'valeur' => 20]);
        $profil->indemnites()->attach($charges->id, [
            'type_calcul' => 'pourcentage', 'valeur' => 25,
            'base_calcul' => 'indemnite:'.$logement->id,
        ]);

        // Logement : 20 % de 200 000 = 40 000. Charges : 25 % de 40 000 = 10 000.
        $this->assertSame(50000.0, $this->paie->apercuProfil($profil->fresh())['total_indemnites']);
    }

    /** Une chaine de trois maillons se resout aussi, quel que soit l'ordre. */
    public function test_une_chaine_de_trois_lignes_se_resout(): void
    {
        $profil = $this->profil();
        $a = Indemnite::create(['libelle' => 'A', 'imposable' => true, 'actif' => true]);
        $b = Indemnite::create(['libelle' => 'B', 'imposable' => true, 'actif' => true]);
        $c = Indemnite::create(['libelle' => 'C', 'imposable' => true, 'actif' => true]);

        // Attachees a l'envers de l'ordre de calcul, pour eprouver les passes.
        $profil->indemnites()->attach($c->id, [
            'type_calcul' => 'pourcentage', 'valeur' => 50, 'base_calcul' => 'indemnite:'.$b->id,
        ]);
        $profil->indemnites()->attach($b->id, [
            'type_calcul' => 'pourcentage', 'valeur' => 50, 'base_calcul' => 'indemnite:'.$a->id,
        ]);
        $profil->indemnites()->attach($a->id, ['type_calcul' => 'fixe', 'valeur' => 80000]);

        // 80 000 + 40 000 + 20 000
        $this->assertSame(140000.0, $this->paie->apercuProfil($profil->fresh())['total_indemnites']);
    }

    public function test_un_pourcentage_decimal_est_respecte(): void
    {
        $profil = $this->profil();
        $cnps = Retenue::create(['libelle' => 'CNPS', 'actif' => true]);

        $profil->retenues()->attach($cnps->id, ['type_calcul' => 'pourcentage', 'valeur' => 4.2]);

        // 4,2 % de 200 000
        $this->assertSame(8400.0, $this->paie->apercuProfil($profil->fresh())['total_retenues']);
    }

    public function test_sans_assiette_le_calcul_ne_change_pas(): void
    {
        $profil = $this->profil();
        $prime = Indemnite::create(['libelle' => 'Prime', 'imposable' => true, 'actif' => true]);
        $profil->indemnites()->attach($prime->id, ['type_calcul' => 'pourcentage', 'valeur' => 10]);

        $this->assertSame(20000.0, $this->paie->apercuProfil($profil->fresh())['total_indemnites']);
    }

    /** Le filet : une assiette disparue ne bloque pas la paie du mois. */
    public function test_une_assiette_disparue_retombe_sur_le_salaire_de_base(): void
    {
        $profil = $this->profil();
        $prime = Indemnite::create(['libelle' => 'Prime', 'imposable' => true, 'actif' => true]);
        $profil->indemnites()->attach($prime->id, [
            'type_calcul' => 'pourcentage', 'valeur' => 10, 'base_calcul' => 'indemnite:9999',
        ]);

        $this->assertSame(20000.0, $this->paie->apercuProfil($profil->fresh())['total_indemnites']);
    }

    public function test_le_bulletin_fige_l_assiette_qui_a_servi(): void
    {
        $profil = $this->profil();
        $transport = Indemnite::create(['libelle' => 'Transport', 'imposable' => true, 'actif' => true]);
        $part = Retenue::create(['libelle' => 'Part salariale', 'actif' => true]);

        $profil->indemnites()->attach($transport->id, ['type_calcul' => 'fixe', 'valeur' => 50000]);
        $profil->retenues()->attach($part->id, [
            'type_calcul' => 'pourcentage', 'valeur' => 10, 'base_calcul' => 'indemnite:'.$transport->id,
        ]);

        Contrat::create([
            'agent_id' => Agent::create(['user_id' => User::factory()->create()->id])->id,
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi', 'poste' => 'Enseignant', 'date_debut' => '2026-01-01',
            'quotite' => 100, 'profil_salaire_id' => $profil->id,
            'echelon_id' => $this->echelon->id, 'statut' => 'actif',
        ]);

        $this->paie->genererMois($this->employeur, 9, 2026);

        $ligne = Bulletin::firstOrFail()->detail['retenues'][0];

        // Le detail revient du JSON : un montant rond y perd sa decimale.
        $this->assertSame(5000.0, (float) $ligne['montant']);
        $this->assertSame(50000.0, (float) $ligne['assiette']);
        $this->assertSame('Transport', $ligne['assietteLibelle']);
    }

    // -------------------------------------------------------- la saisie

    public function test_la_rh_enregistre_une_ligne_assise_sur_une_autre(): void
    {
        $transport = Indemnite::create(['libelle' => 'Transport', 'imposable' => true, 'actif' => true]);
        $part = Retenue::create(['libelle' => 'Part salariale', 'actif' => true]);

        $this->actingAs($this->admin())->post(route('personnel.profils.store'), [
            'nom' => 'Cadre', 'actif' => true,
            'echelon_id' => $this->echelon->id,
            'indemnites' => [['id' => $transport->id, 'type_calcul' => 'fixe', 'valeur' => 50000]],
            'retenues' => [[
                'id' => $part->id, 'type_calcul' => 'pourcentage', 'valeur' => 10,
                'base_calcul' => 'indemnite:'.$transport->id,
            ]],
        ])->assertSessionHasNoErrors();

        $profil = ProfilSalaire::where('nom', 'Cadre')->firstOrFail();

        $this->assertSame(
            'indemnite:'.$transport->id,
            $profil->retenues()->first()->pivot->base_calcul,
        );
    }

    public function test_une_valeur_decimale_s_enregistre(): void
    {
        $cnps = Retenue::create(['libelle' => 'CNPS', 'actif' => true]);

        $this->actingAs($this->admin())->post(route('personnel.profils.store'), [
            'nom' => 'Cadre', 'actif' => true, 'echelon_id' => $this->echelon->id,
            'retenues' => [['id' => $cnps->id, 'type_calcul' => 'pourcentage', 'valeur' => 4.2]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            4.2,
            (float) ProfilSalaire::firstOrFail()->retenues()->first()->pivot->valeur,
        );
    }

    public function test_une_ligne_ne_se_calcule_pas_sur_elle_meme(): void
    {
        $prime = Indemnite::create(['libelle' => 'Prime', 'imposable' => true, 'actif' => true]);

        $this->actingAs($this->admin())->post(route('personnel.profils.store'), [
            'nom' => 'Cadre', 'actif' => true, 'echelon_id' => $this->echelon->id,
            'indemnites' => [[
                'id' => $prime->id, 'type_calcul' => 'pourcentage', 'valeur' => 10,
                'base_calcul' => 'indemnite:'.$prime->id,
            ]],
        ])->assertSessionHasErrors('profil');

        $this->assertSame(0, ProfilSalaire::count());
    }

    public function test_deux_lignes_qui_se_renvoient_l_une_a_l_autre_sont_refusees(): void
    {
        $a = Indemnite::create(['libelle' => 'A', 'imposable' => true, 'actif' => true]);
        $b = Indemnite::create(['libelle' => 'B', 'imposable' => true, 'actif' => true]);

        $this->actingAs($this->admin())->post(route('personnel.profils.store'), [
            'nom' => 'Cadre', 'actif' => true, 'echelon_id' => $this->echelon->id,
            'indemnites' => [
                ['id' => $a->id, 'type_calcul' => 'pourcentage', 'valeur' => 10, 'base_calcul' => 'indemnite:'.$b->id],
                ['id' => $b->id, 'type_calcul' => 'pourcentage', 'valeur' => 10, 'base_calcul' => 'indemnite:'.$a->id],
            ],
        ])->assertSessionHasErrors('profil');

        $this->assertSame(0, ProfilSalaire::count());
    }

    public function test_une_assiette_absente_du_profil_est_refusee(): void
    {
        $prime = Indemnite::create(['libelle' => 'Prime', 'imposable' => true, 'actif' => true]);
        $ailleurs = Indemnite::create(['libelle' => 'Ailleurs', 'imposable' => true, 'actif' => true]);

        $this->actingAs($this->admin())->post(route('personnel.profils.store'), [
            'nom' => 'Cadre', 'actif' => true, 'echelon_id' => $this->echelon->id,
            'indemnites' => [[
                'id' => $prime->id, 'type_calcul' => 'pourcentage', 'valeur' => 10,
                'base_calcul' => 'indemnite:'.$ailleurs->id,
            ]],
        ])->assertSessionHasErrors('profil');
    }

    public function test_une_assiette_mal_formee_est_refusee(): void
    {
        $prime = Indemnite::create(['libelle' => 'Prime', 'imposable' => true, 'actif' => true]);

        $this->actingAs($this->admin())->post(route('personnel.profils.store'), [
            'nom' => 'Cadre', 'actif' => true, 'echelon_id' => $this->echelon->id,
            'indemnites' => [[
                'id' => $prime->id, 'type_calcul' => 'pourcentage', 'valeur' => 10,
                'base_calcul' => 'salaire; DROP TABLE',
            ]],
        ])->assertSessionHasErrors('indemnites.0.base_calcul');
    }

    public function test_l_ecran_des_profils_renvoie_l_assiette(): void
    {
        $profil = $this->profil();
        $transport = Indemnite::create(['libelle' => 'Transport', 'imposable' => true, 'actif' => true]);
        $part = Retenue::create(['libelle' => 'Part salariale', 'actif' => true]);

        $profil->indemnites()->attach($transport->id, ['type_calcul' => 'fixe', 'valeur' => 50000]);
        $profil->retenues()->attach($part->id, [
            'type_calcul' => 'pourcentage', 'valeur' => 10, 'base_calcul' => 'indemnite:'.$transport->id,
        ]);

        $this->actingAs($this->admin())->get(route('personnel.profils'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('profils.0.retenues.0.baseCalcul', 'indemnite:'.$transport->id)
                ->where('profils.0.indemnites.0.baseCalcul', null)
                ->where('profils.0.totalRetenues', 5000)
                ->where('profils.0.salaireNet', 245000));
    }
}
