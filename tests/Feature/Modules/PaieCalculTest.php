<?php

namespace Tests\Feature\Modules;

use App\Models\Agent;
use App\Models\Ajustement;
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
use RuntimeException;
use Tests\TestCase;

/**
 * Le calcul de la paie, verrouille ligne a ligne : c'est la partie du module
 * ou une erreur se voit sur un virement.
 */
class PaieCalculTest extends TestCase
{
    use RefreshDatabase;

    private PaieService $paie;

    private Employeur $employeur;

    private Echelon $echelon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paie = app(PaieService::class);
        $this->employeur = Employeur::create(['nom' => 'Institut Universitaire Majestueuse', 'sigle' => 'IUM']);

        $categorie = CategorieRh::create(['libelle' => 'Enseignants']);
        $this->echelon = Echelon::create([
            'categorie_rh_id' => $categorie->id,
            'numero' => 1,
            'salaire' => 200000,
        ]);
    }

    private function agent(string $nom = 'Nkoa'): Agent
    {
        return Agent::create(['user_id' => User::factory()->create(['lastname' => $nom])->id]);
    }

    private function contrat(array $attributs = []): Contrat
    {
        return Contrat::create(array_merge([
            'agent_id' => $this->agent()->id,
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi',
            'poste' => 'Enseignant',
            'date_debut' => '2026-01-01',
            'quotite' => 100,
            'echelon_id' => $this->echelon->id,
            'statut' => 'actif',
        ], $attributs));
    }

    public function test_le_salaire_de_base_vient_de_l_echelon(): void
    {
        $calcul = $this->paie->calculer($this->contrat(), 9, 2026);

        $this->assertSame(200000.0, $calcul['salaire_base']);
        $this->assertSame(200000.0, $calcul['salaire_net']);
    }

    public function test_la_quotite_proratise_le_salaire_de_base(): void
    {
        $calcul = $this->paie->calculer($this->contrat(['quotite' => 50]), 9, 2026);

        $this->assertSame(100000.0, $calcul['salaire_base']);
    }

    public function test_l_echelon_du_contrat_prime_sur_celui_du_profil(): void
    {
        $autre = Echelon::create([
            'categorie_rh_id' => $this->echelon->categorie_rh_id,
            'numero' => 2,
            'salaire' => 350000,
        ]);
        $profil = ProfilSalaire::create(['nom' => 'Enseignant 1', 'echelon_id' => $this->echelon->id]);

        $contrat = $this->contrat(['profil_salaire_id' => $profil->id, 'echelon_id' => $autre->id]);

        $this->assertSame(350000.0, $this->paie->calculer($contrat, 9, 2026)['salaire_base']);
    }

    public function test_le_profil_fournit_l_echelon_quand_le_contrat_n_en_a_pas(): void
    {
        $profil = ProfilSalaire::create(['nom' => 'Enseignant 1', 'echelon_id' => $this->echelon->id]);
        $contrat = $this->contrat(['profil_salaire_id' => $profil->id, 'echelon_id' => null]);

        $this->assertSame(200000.0, $this->paie->calculer($contrat, 9, 2026)['salaire_base']);
    }

    public function test_indemnites_et_retenues_fixes_et_en_pourcentage(): void
    {
        $profil = ProfilSalaire::create(['nom' => 'Enseignant 1', 'echelon_id' => $this->echelon->id]);

        $logement = Indemnite::create(['libelle' => 'Logement']);
        $transport = Indemnite::create(['libelle' => 'Transport']);
        $cnps = Retenue::create(['libelle' => 'CNPS']);

        $profil->indemnites()->attach($logement->id, ['type_calcul' => 'pourcentage', 'valeur' => 10]);
        $profil->indemnites()->attach($transport->id, ['type_calcul' => 'fixe', 'valeur' => 25000]);
        $profil->retenues()->attach($cnps->id, ['type_calcul' => 'pourcentage', 'valeur' => 4.2]);

        $calcul = $this->paie->calculer($this->contrat(['profil_salaire_id' => $profil->id]), 9, 2026);

        // 10 % de 200 000 = 20 000, plus 25 000 de transport.
        $this->assertSame(45000.0, $calcul['total_indemnites']);
        // 4,2 % de 200 000 = 8 400.
        $this->assertSame(8400.0, $calcul['total_retenues']);
        $this->assertSame(236600.0, $calcul['salaire_net']);

        $this->assertCount(2, $calcul['detail']['indemnites']);
        $this->assertSame('profil', $calcul['detail']['indemnites'][0]['source']);
        $this->assertSame('pourcentage', $calcul['detail']['indemnites'][0]['type']);
    }

    public function test_le_pourcentage_porte_sur_le_base_deja_proratise(): void
    {
        $profil = ProfilSalaire::create(['nom' => 'Mi-temps', 'echelon_id' => $this->echelon->id]);
        $profil->indemnites()->attach(
            Indemnite::create(['libelle' => 'Logement'])->id,
            ['type_calcul' => 'pourcentage', 'valeur' => 10]
        );

        $calcul = $this->paie->calculer($this->contrat(['profil_salaire_id' => $profil->id, 'quotite' => 50]), 9, 2026);

        $this->assertSame(100000.0, $calcul['salaire_base']);
        $this->assertSame(10000.0, $calcul['total_indemnites']);
    }

    public function test_les_ajustements_ne_valent_que_pour_leur_mois(): void
    {
        $contrat = $this->contrat();

        Ajustement::create([
            'contrat_id' => $contrat->id, 'mois' => 9, 'annee' => 2026,
            'type' => 'bonus', 'mode' => 'fixe', 'libelle' => 'Prime de rentrée', 'montant' => 50000,
        ]);
        Ajustement::create([
            'contrat_id' => $contrat->id, 'mois' => 9, 'annee' => 2026,
            'type' => 'retenue', 'mode' => 'pourcentage', 'libelle' => 'Absences', 'montant' => 5,
        ]);

        $septembre = $this->paie->calculer($contrat, 9, 2026);
        $this->assertSame(50000.0, $septembre['total_indemnites']);
        $this->assertSame(10000.0, $septembre['total_retenues']);
        $this->assertSame(240000.0, $septembre['salaire_net']);
        $this->assertSame('ajustement', $septembre['detail']['retenues'][0]['source']);

        $octobre = $this->paie->calculer($contrat, 10, 2026);
        $this->assertSame(200000.0, $octobre['salaire_net']);
        $this->assertSame([], $octobre['detail']['indemnites']);
    }

    public function test_un_contrat_sans_echelon_ne_produit_pas_de_salaire(): void
    {
        $calcul = $this->paie->calculer($this->contrat(['echelon_id' => null]), 9, 2026);

        $this->assertSame(0.0, $calcul['salaire_base']);
        $this->assertSame(0.0, $calcul['salaire_net']);
    }

    public function test_la_generation_ne_retient_que_les_contrats_actifs_du_mois(): void
    {
        $this->contrat();                                            // retenu
        $this->contrat(['statut' => 'termine']);                     // exclu : plus actif
        $this->contrat(['date_debut' => '2026-11-01']);              // exclu : pas encore commence
        $this->contrat(['date_debut' => '2025-01-01', 'date_fin' => '2026-06-30']); // exclu : deja parti
        $this->contrat(['date_fin' => '2026-09-15']);                // retenu : part en cours de mois

        $resultat = $this->paie->genererMois($this->employeur, 9, 2026);

        $this->assertSame(2, $resultat['crees']);
        $this->assertSame(2, Bulletin::where('annee', 2026)->where('mois', 9)->count());
    }

    public function test_la_generation_ignore_un_autre_employeur(): void
    {
        $autre = Employeur::create(['nom' => 'Institut de Formation', 'sigle' => 'IFPM']);
        $this->contrat();
        $this->contrat(['employeur_id' => $autre->id]);

        $this->assertSame(1, $this->paie->genererMois($this->employeur, 9, 2026)['crees']);
        $this->assertSame(1, $this->paie->genererMois($autre, 9, 2026)['crees']);
    }

    public function test_relancer_la_generation_recalcule_les_brouillons_sans_les_dupliquer(): void
    {
        $contrat = $this->contrat();
        $this->paie->genererMois($this->employeur, 9, 2026);

        Ajustement::create([
            'contrat_id' => $contrat->id, 'mois' => 9, 'annee' => 2026,
            'type' => 'bonus', 'mode' => 'fixe', 'libelle' => 'Prime', 'montant' => 30000,
        ]);

        $resultat = $this->paie->genererMois($this->employeur, 9, 2026);

        $this->assertSame(0, $resultat['crees']);
        $this->assertSame(1, $resultat['recalcules']);
        $this->assertSame(1, Bulletin::count());
        $this->assertSame(230000.0, (float) Bulletin::first()->salaire_net);
    }

    public function test_un_bulletin_valide_n_est_plus_touche_par_une_relance(): void
    {
        $contrat = $this->contrat();
        $this->paie->genererMois($this->employeur, 9, 2026);
        $this->paie->valider(Bulletin::first());

        Ajustement::create([
            'contrat_id' => $contrat->id, 'mois' => 9, 'annee' => 2026,
            'type' => 'bonus', 'mode' => 'fixe', 'libelle' => 'Prime', 'montant' => 30000,
        ]);

        $resultat = $this->paie->genererMois($this->employeur, 9, 2026);

        $this->assertSame(1, $resultat['ignores']);
        $this->assertSame(200000.0, (float) Bulletin::first()->salaire_net);
    }

    public function test_le_detail_reste_fige_quand_le_referentiel_change(): void
    {
        $profil = ProfilSalaire::create(['nom' => 'Enseignant 1', 'echelon_id' => $this->echelon->id]);
        $this->contrat(['profil_salaire_id' => $profil->id]);

        $this->paie->genererMois($this->employeur, 9, 2026);
        $this->paie->valider(Bulletin::first());

        $this->echelon->update(['salaire' => 500000]);

        $bulletin = Bulletin::first()->refresh();
        $this->assertSame(200000.0, (float) $bulletin->salaire_base);
    }

    public function test_le_circuit_brouillon_valide_paye(): void
    {
        $this->contrat();
        $this->paie->genererMois($this->employeur, 9, 2026);
        $bulletin = Bulletin::first();

        $this->assertSame('brouillon', $bulletin->statut);

        $bulletin = $this->paie->valider($bulletin, 1);
        $this->assertSame('valide', $bulletin->statut);
        $this->assertNotNull($bulletin->valide_le);

        $bulletin = $this->paie->payer($bulletin, 1);
        $this->assertSame('paye', $bulletin->statut);
        $this->assertNotNull($bulletin->paye_le);
        $this->assertTrue($bulletin->estFige());
    }

    public function test_on_ne_paie_pas_un_bulletin_non_valide(): void
    {
        $this->contrat();
        $this->paie->genererMois($this->employeur, 9, 2026);

        $this->expectException(RuntimeException::class);
        $this->paie->payer(Bulletin::first());
    }

    public function test_on_ne_recalcule_pas_un_bulletin_paye(): void
    {
        $this->contrat();
        $this->paie->genererMois($this->employeur, 9, 2026);
        $bulletin = $this->paie->payer($this->paie->valider(Bulletin::first()));

        $this->expectException(RuntimeException::class);
        $this->paie->recalculer($bulletin);
    }

    public function test_on_ne_valide_pas_un_net_negatif(): void
    {
        $contrat = $this->contrat();
        Ajustement::create([
            'contrat_id' => $contrat->id, 'mois' => 9, 'annee' => 2026,
            'type' => 'retenue', 'mode' => 'fixe', 'libelle' => 'Trop-perçu', 'montant' => 300000,
        ]);
        $this->paie->genererMois($this->employeur, 9, 2026);

        $this->expectException(RuntimeException::class);
        $this->paie->valider(Bulletin::first());
    }

    public function test_la_masse_salariale_du_mois(): void
    {
        $profil = ProfilSalaire::create(['nom' => 'Enseignant 1', 'echelon_id' => $this->echelon->id]);
        $profil->retenues()->attach(Retenue::create(['libelle' => 'CNPS'])->id, ['type_calcul' => 'pourcentage', 'valeur' => 5]);

        $this->contrat(['profil_salaire_id' => $profil->id]);
        $this->contrat(['profil_salaire_id' => $profil->id]);
        $this->paie->genererMois($this->employeur, 9, 2026);
        $this->paie->payer($this->paie->valider(Bulletin::first()));

        $masse = $this->paie->masseSalariale(9, 2026, $this->employeur->id);

        $this->assertSame(2, $masse['bulletins']);
        $this->assertSame(400000.0, $masse['brut']);
        $this->assertSame(20000.0, $masse['retenues']);
        $this->assertSame(380000.0, $masse['net']);
        // Un des deux est paye : il ne reste qu'un net a verser.
        $this->assertSame(190000.0, $masse['a_payer']);
    }

    public function test_un_mois_invalide_est_refuse(): void
    {
        $this->expectException(RuntimeException::class);
        $this->paie->genererMois($this->employeur, 13, 2026);
    }
}
