<?php

namespace Tests\Feature\Modules;

use App\Models\Application;
use App\Models\Employeur;
use App\Models\User;
use App\Services\AttributionMatricules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Attribution des matricules du personnel et fichier du personnel.
 *
 * La regle qui compte : la sequence ne repart jamais en arriere, et un
 * matricule deja porte n'est jamais remplace.
 */
class MatriculesTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    private Application $institut;

    private Employeur $employeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        $this->institut = Application::factory()->create(['name' => 'IUM']);
        $this->employeur = Employeur::create([
            'nom' => 'Institut Universitaire', 'sigle' => 'IUM', 'application_id' => $this->institut->id,
        ]);
    }

    private function gestionnaire(): User
    {
        $user = User::factory()->create(['matricule' => null]);
        $user->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $user->employeursRh()->attach($this->employeur);

        return $user;
    }

    /** Un enseignant qui vient de créer son compte : pas encore de matricule. */
    private function enseignant(string $nom, ?string $matricule = null): User
    {
        $user = User::factory()->create([
            'lastname' => $nom,
            'name' => 'Claire',
            'matricule' => $matricule,
            'poste' => 'Enseignant',
        ]);
        $user->applications()->attach($this->institut);

        return $user;
    }

    // ------------------------------------------------------- la séquence

    public function test_la_sequence_part_du_premier_numero(): void
    {
        $this->assertSame('LM-260001', app(AttributionMatricules::class)->prochain());
    }

    public function test_la_sequence_reprend_apres_le_plus_haut_attribue(): void
    {
        $this->enseignant('NKOA', 'LM-260007');
        $this->enseignant('ATANGANA', 'LM-260003');

        $this->assertSame('LM-260008', app(AttributionMatricules::class)->prochain());
    }

    /** Un matricule d'un autre format ne perturbe pas la numérotation. */
    public function test_un_ancien_matricule_non_conforme_est_ignore(): void
    {
        $this->enseignant('NKOA', 'IUM/2019/44');
        $this->enseignant('ATANGANA', 'LM-260002');

        $this->assertSame('LM-260003', app(AttributionMatricules::class)->prochain());
    }

    public function test_un_numero_libere_par_un_depart_ne_revient_pas(): void
    {
        $parti = $this->enseignant('PARTI', 'LM-260005');
        $parti->update(['status' => 'suspended']);

        // Le compte est suspendu, son numéro reste pris.
        $this->assertSame('LM-260006', app(AttributionMatricules::class)->prochain());
    }

    /** L'année vient du premier contrat quand il est connu. */
    public function test_l_annee_est_celle_du_premier_contrat(): void
    {
        $ancien = $this->enseignant('ANCIEN');
        $agent = \App\Models\Agent::create(['user_id' => $ancien->id]);
        $agent->contrats()->create([
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi',
            'poste' => 'Enseignant',
            'date_debut' => '2019-10-01',
            'quotite' => 100,
            'statut' => 'actif',
        ]);

        $this->actingAs($this->gestionnaire())->post(route('personnel.matricules.attribuer'), [
            'personnes' => [$ancien->id],
        ])->assertRedirect();

        // Recruté en 2019, quelle que soit la date de création du compte.
        $this->assertSame('LM-190001', $ancien->refresh()->matricule);
    }

    public function test_sans_contrat_l_annee_est_celle_du_compte(): void
    {
        $recent = $this->enseignant('RECENT');
        // created_at n'est pas assignable en masse : on passe par la requete.
        User::where('id', $recent->id)->update(['created_at' => '2024-03-12 08:00:00']);

        $this->actingAs($this->gestionnaire())->post(route('personnel.matricules.attribuer'), [
            'personnes' => [$recent->id],
        ])->assertRedirect();

        $this->assertSame('LM-240001', $recent->refresh()->matricule);
    }

    /** Chaque année a sa propre séquence : elles ne se marchent pas dessus. */
    public function test_les_sequences_sont_independantes_d_une_annee_a_l_autre(): void
    {
        $this->enseignant('DEJA2019', 'LM-190007');

        $ancien = $this->enseignant('ANCIEN');
        $agent = \App\Models\Agent::create(['user_id' => $ancien->id]);
        $agent->contrats()->create([
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi',
            'poste' => 'Enseignant',
            'date_debut' => '2019-10-01',
            'quotite' => 100,
            'statut' => 'actif',
        ]);

        $nouveau = $this->enseignant('NOUVEAU');

        $this->actingAs($this->gestionnaire())->post(route('personnel.matricules.attribuer'), [
            'personnes' => [$ancien->id, $nouveau->id],
        ])->assertRedirect();

        $this->assertSame('LM-190008', $ancien->refresh()->matricule);
        $this->assertSame('LM-260001', $nouveau->refresh()->matricule);
    }

    // ----------------------------------------------------- l'attribution

    public function test_l_ecran_annonce_qui_recevrait_quel_numero(): void
    {
        $this->enseignant('ATANGANA');
        $this->enseignant('NKOA');
        $this->enseignant('DEJA', 'LM-260010');

        $this->actingAs($this->gestionnaire())->getJson(route('personnel.matricules.apourvoir'))
            ->assertOk()
            ->assertJsonPath('dernier', 'LM-260010')
            // Les deux enseignants sans matricule. Le gestionnaire, rattaché
            // au seul module et non à l'institut, ne relève pas de son propre
            // périmètre : il n'y figure pas.
            ->assertJsonPath('personnes.0.matricule', 'LM-260011')
            ->assertJsonPath('personnes.1.matricule', 'LM-260012')
            ->assertJsonCount(2, 'personnes');

        // Rien n'a été enregistré : c'est un aperçu.
        $this->assertSame(1, User::whereNotNull('matricule')->count());
    }

    public function test_l_attribution_suit_l_ordre_alphabetique(): void
    {
        $nkoa = $this->enseignant('NKOA');
        $atangana = $this->enseignant('ATANGANA');

        $this->actingAs($this->gestionnaire())->post(route('personnel.matricules.attribuer'), [
            'personnes' => [$nkoa->id, $atangana->id],
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame('LM-260001', $atangana->refresh()->matricule);
        $this->assertSame('LM-260002', $nkoa->refresh()->matricule);
    }

    public function test_on_attribue_seulement_aux_personnes_designees(): void
    {
        $retenu = $this->enseignant('NKOA');
        $ecarte = $this->enseignant('ATANGANA');

        $this->actingAs($this->gestionnaire())->post(route('personnel.matricules.attribuer'), [
            'personnes' => [$retenu->id],
        ])->assertRedirect();

        $this->assertNotNull($retenu->refresh()->matricule);
        $this->assertNull($ecarte->refresh()->matricule);
    }

    /** La règle 2 du document : un matricule ne change jamais. */
    public function test_un_matricule_existant_n_est_jamais_remplace(): void
    {
        $deja = $this->enseignant('NKOA', 'LM-260042');

        $this->actingAs($this->gestionnaire())->post(route('personnel.matricules.attribuer'), [
            'personnes' => [$deja->id],
        ])->assertSessionHasErrors('matricules');

        $this->assertSame('LM-260042', $deja->refresh()->matricule);
    }

    public function test_deux_attributions_de_suite_ne_se_chevauchent_pas(): void
    {
        $premier = $this->enseignant('AAA');
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->post(route('personnel.matricules.attribuer'), ['personnes' => [$premier->id]]);

        $second = $this->enseignant('BBB');
        $this->actingAs($gestionnaire)->post(route('personnel.matricules.attribuer'), ['personnes' => [$second->id]]);

        $this->assertSame('LM-260001', $premier->refresh()->matricule);
        $this->assertSame('LM-260002', $second->refresh()->matricule);
    }

    public function test_un_gestionnaire_ne_matricule_que_son_perimetre(): void
    {
        $autreInstitut = Application::factory()->create(['name' => 'IFPM']);
        $autreEmployeur = Employeur::create([
            'nom' => 'Formation', 'sigle' => 'IFPM', 'application_id' => $autreInstitut->id,
        ]);

        $etranger = User::factory()->create(['lastname' => 'IFPM', 'matricule' => null]);
        $etranger->applications()->attach($autreInstitut);

        $this->actingAs($this->gestionnaire())->post(route('personnel.matricules.attribuer'), [
            'personnes' => [$etranger->id],
        ])->assertSessionHasErrors('matricules');

        $this->assertNull($etranger->refresh()->matricule);
        $this->assertNotNull($autreEmployeur->id);
    }

    public function test_un_simple_lecteur_n_attribue_rien(): void
    {
        $enseignant = $this->enseignant('NKOA');
        $lecteur = User::factory()->create();
        $lecteur->applications()->attach($this->module, ['role_in_app' => 'lecteur', 'roles' => json_encode(['lecteur'])]);

        $this->actingAs($lecteur)->post(route('personnel.matricules.attribuer'), ['personnes' => [$enseignant->id]])
            ->assertForbidden();
        $this->actingAs($lecteur)->getJson(route('personnel.matricules.apourvoir'))->assertForbidden();

        $this->assertNull($enseignant->refresh()->matricule);
    }

    // ------------------------------------------- matricule d'une personne

    public function test_la_fiche_propose_le_prochain_numero_libre(): void
    {
        $this->enseignant('NKOA', 'LM-260012');

        $this->actingAs($this->gestionnaire())->getJson(route('personnel.matricules.prochain'))
            ->assertOk()
            ->assertJson(['matricule' => 'LM-260013']);

        // Rien n'est reserve : le numero reste libre tant qu'il n'est pas saisi.
        $this->assertSame(1, User::whereNotNull('matricule')->count());
    }

    public function test_un_lecteur_ne_demande_pas_de_numero(): void
    {
        $lecteur = User::factory()->create();
        $lecteur->applications()->attach($this->module, ['role_in_app' => 'lecteur', 'roles' => json_encode(['lecteur'])]);

        $this->actingAs($lecteur)->getJson(route('personnel.matricules.prochain'))->assertForbidden();
    }

    public function test_le_matricule_se_saisit_depuis_la_fiche(): void
    {
        $sans = $this->enseignant('NKOA');

        $this->actingAs($this->gestionnaire())->put(route('personnel.agents.update', $sans), [
            'name' => 'Claire',
            'lastname' => 'NKOA',
            'matricule' => 'LM-260013',
        ])->assertRedirect();

        $this->assertSame('LM-260013', $sans->refresh()->matricule);
    }

    public function test_un_matricule_se_remplace_mais_jamais_par_celui_d_un_autre(): void
    {
        $premier = $this->enseignant('NKOA', 'LM-260001');
        $second = $this->enseignant('ATANGANA', 'LM-260002');

        // Reprendre le numero du voisin est refuse.
        $this->actingAs($this->gestionnaire())->put(route('personnel.agents.update', $second), [
            'name' => 'Claire',
            'matricule' => 'LM-260001',
        ])->assertSessionHasErrors('matricule');

        $this->assertSame('LM-260002', $second->refresh()->matricule);

        // Le corriger vers un numero libre reste possible.
        $this->actingAs($this->gestionnaire())->put(route('personnel.agents.update', $second), [
            'name' => 'Claire',
            'matricule' => 'LM-260009',
        ])->assertRedirect();

        $this->assertSame('LM-260009', $second->refresh()->matricule);
        $this->assertSame('LM-260001', $premier->refresh()->matricule);
    }

    // --------------------------------------------------------- le fichier

    public function test_le_fichier_du_personnel_se_telecharge(): void
    {
        $this->enseignant('NKOA', 'LM-260001');
        $this->enseignant('ATANGANA');

        $reponse = $this->actingAs($this->gestionnaire())->get(route('personnel.export'));

        $reponse->assertOk();
        $reponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'personnel-la-majestueuse-'.now()->format('Y-m-d').'.csv',
            $reponse->headers->get('content-disposition'),
        );

        $csv = $reponse->streamedContent();

        // Excel a besoin du marqueur UTF-8 pour les accents.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Matricule;Nom;Prénom', $csv);
        $this->assertStringContainsString('LM-260001;NKOA;Claire', $csv);
        // Celui qui n'en a pas encore figure aussi, en tête de liste.
        $this->assertStringContainsString(';ATANGANA;Claire', $csv);
    }

    public function test_le_fichier_reprend_les_instituts_et_contrats(): void
    {
        $enseignant = $this->enseignant('NKOA', 'LM-260001');
        $agent = \App\Models\Agent::create(['user_id' => $enseignant->id]);
        $agent->contrats()->create([
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi',
            'poste' => 'Maître de conférences',
            'date_debut' => '2026-01-01',
            'quotite' => 100,
            'statut' => 'actif',
        ]);

        $csv = $this->actingAs($this->gestionnaire())->get(route('personnel.export'))->streamedContent();

        $this->assertStringContainsString('IUM', $csv);
        $this->assertStringContainsString('Maître de conférences', $csv);
    }

    public function test_un_lecteur_ne_telecharge_pas_le_fichier(): void
    {
        $lecteur = User::factory()->create();
        $lecteur->applications()->attach($this->module, ['role_in_app' => 'lecteur', 'roles' => json_encode(['lecteur'])]);

        $this->actingAs($lecteur)->get(route('personnel.export'))->assertForbidden();
    }
}
