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
        $this->assertSame('LM-00001', app(AttributionMatricules::class)->prochain());
    }

    public function test_la_sequence_reprend_apres_le_plus_haut_attribue(): void
    {
        $this->enseignant('NKOA', 'LM-00007');
        $this->enseignant('ATANGANA', 'LM-00003');

        $this->assertSame('LM-00008', app(AttributionMatricules::class)->prochain());
    }

    /** Un matricule d'un autre format ne perturbe pas la numérotation. */
    public function test_un_ancien_matricule_non_conforme_est_ignore(): void
    {
        $this->enseignant('NKOA', 'IUM/2019/44');
        $this->enseignant('ATANGANA', 'LM-00002');

        $this->assertSame('LM-00003', app(AttributionMatricules::class)->prochain());
    }

    public function test_un_numero_libere_par_un_depart_ne_revient_pas(): void
    {
        $parti = $this->enseignant('PARTI', 'LM-00005');
        $parti->update(['status' => 'suspended']);

        // Le compte est suspendu, son numéro reste pris.
        $this->assertSame('LM-00006', app(AttributionMatricules::class)->prochain());
    }

    // ----------------------------------------------------- l'attribution

    public function test_l_ecran_annonce_qui_recevrait_quel_numero(): void
    {
        $this->enseignant('ATANGANA');
        $this->enseignant('NKOA');
        $this->enseignant('DEJA', 'LM-00010');

        $this->actingAs($this->gestionnaire())->getJson(route('personnel.matricules.apourvoir'))
            ->assertOk()
            ->assertJsonPath('dernier', 'LM-00010')
            // Les deux enseignants sans matricule. Le gestionnaire, rattaché
            // au seul module et non à l'institut, ne relève pas de son propre
            // périmètre : il n'y figure pas.
            ->assertJsonPath('personnes.0.matricule', 'LM-00011')
            ->assertJsonPath('personnes.1.matricule', 'LM-00012')
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

        $this->assertSame('LM-00001', $atangana->refresh()->matricule);
        $this->assertSame('LM-00002', $nkoa->refresh()->matricule);
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
        $deja = $this->enseignant('NKOA', 'LM-00042');

        $this->actingAs($this->gestionnaire())->post(route('personnel.matricules.attribuer'), [
            'personnes' => [$deja->id],
        ])->assertSessionHasErrors('matricules');

        $this->assertSame('LM-00042', $deja->refresh()->matricule);
    }

    public function test_deux_attributions_de_suite_ne_se_chevauchent_pas(): void
    {
        $premier = $this->enseignant('AAA');
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->post(route('personnel.matricules.attribuer'), ['personnes' => [$premier->id]]);

        $second = $this->enseignant('BBB');
        $this->actingAs($gestionnaire)->post(route('personnel.matricules.attribuer'), ['personnes' => [$second->id]]);

        $this->assertSame('LM-00001', $premier->refresh()->matricule);
        $this->assertSame('LM-00002', $second->refresh()->matricule);
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

    // --------------------------------------------------------- le fichier

    public function test_le_fichier_du_personnel_se_telecharge(): void
    {
        $this->enseignant('NKOA', 'LM-00001');
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
        $this->assertStringContainsString('LM-00001;NKOA;Claire', $csv);
        // Celui qui n'en a pas encore figure aussi, en tête de liste.
        $this->assertStringContainsString(';ATANGANA;Claire', $csv);
    }

    public function test_le_fichier_reprend_les_instituts_et_contrats(): void
    {
        $enseignant = $this->enseignant('NKOA', 'LM-00001');
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
