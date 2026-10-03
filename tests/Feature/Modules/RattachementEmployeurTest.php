<?php

namespace Tests\Feature\Modules;

use App\Models\Agent;
use App\Models\Application;
use App\Models\Contrat;
use App\Models\Employeur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Le rattachement d'une personne a un employeur.
 *
 * Un seul institut : il se deduit tout seul. Deux instituts : la deduction
 * serait un coup de des, c'est la RH qui tranche. Et son choix l'emporte
 * toujours, meme quand la deduction etait possible.
 */
class RattachementEmployeurTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    private Application $ium;

    private Application $gsbm;

    private Employeur $employeurIum;

    private Employeur $employeurGsbm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);

        $this->ium = Application::factory()->create(['name' => 'IUM']);
        $this->gsbm = Application::factory()->create(['name' => 'GSBM']);

        $this->employeurIum = Employeur::create([
            'nom' => 'Institut Universitaire', 'sigle' => 'IUM',
            'application_id' => $this->ium->id, 'actif' => true,
        ]);
        $this->employeurGsbm = Employeur::create([
            'nom' => 'Groupe Scolaire Bilingue', 'sigle' => 'GSBM',
            'application_id' => $this->gsbm->id, 'actif' => true,
        ]);
    }

    private function gestionnaire(): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $user->employeursRh()->attach([$this->employeurIum->id, $this->employeurGsbm->id]);

        return $user;
    }

    private function gestionnairePour(Employeur $employeur): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $user->employeursRh()->attach($employeur);

        return $user;
    }

    private function contrat(User $membre, Employeur $employeur, string $statut = 'actif'): Contrat
    {
        return Contrat::create([
            'agent_id' => Agent::firstOrCreate(['user_id' => $membre->id])->id,
            'employeur_id' => $employeur->id,
            'type' => 'cdi', 'poste' => 'Enseignant', 'date_debut' => '2026-01-01',
            'quotite' => 100, 'statut' => $statut,
        ]);
    }

    private function membre(array $instituts): User
    {
        $membre = User::factory()->create(['lastname' => 'NKOA']);
        $membre->applications()->attach($instituts);

        return $membre->fresh();
    }

    // -------------------------------------------------------- la deduction

    public function test_un_seul_institut_deduit_l_employeur(): void
    {
        $membre = $this->membre([$this->ium->id]);

        $this->assertSame($this->employeurIum->id, $membre->employeurDeRattachement()?->id);
        $this->assertFalse($membre->rattachementATrancher());
    }

    public function test_deux_instituts_attendent_la_decision_de_la_rh(): void
    {
        $membre = $this->membre([$this->ium->id, $this->gsbm->id]);

        $this->assertNull($membre->employeurDeRattachement());
        $this->assertTrue($membre->rattachementATrancher());
    }

    public function test_aucun_institut_ne_donne_aucun_employeur(): void
    {
        $membre = User::factory()->create();

        $this->assertNull($membre->employeurDeRattachement());
        $this->assertFalse($membre->rattachementATrancher());
    }

    /** Signer avec une entite, c'est en relever : le contrat passe avant l'institut. */
    public function test_le_contrat_actif_determine_l_employeur(): void
    {
        $membre = $this->membre([$this->gsbm->id]);
        $this->contrat($membre, $this->employeurIum);

        $this->assertSame($this->employeurIum->id, $membre->fresh()->employeurDeRattachement()?->id);
    }

    public function test_deux_contrats_actifs_demandent_un_arbitrage(): void
    {
        $membre = $this->membre([$this->ium->id]);
        $this->contrat($membre, $this->employeurIum);
        $this->contrat($membre, $this->employeurGsbm);

        $membre->refresh();
        $this->assertNull($membre->employeurDeRattachement());
        $this->assertTrue($membre->rattachementATrancher());
    }

    public function test_un_contrat_termine_ne_determine_rien(): void
    {
        $membre = $this->membre([$this->gsbm->id]);
        $this->contrat($membre, $this->employeurIum, 'termine');

        // On retombe sur l'institut.
        $this->assertSame($this->employeurGsbm->id, $membre->fresh()->employeurDeRattachement()?->id);
    }

    public function test_le_choix_de_la_rh_prime_sur_le_contrat(): void
    {
        $membre = $this->membre([$this->gsbm->id]);
        $this->contrat($membre, $this->employeurIum);
        $membre->update(['employeur_id' => $this->employeurGsbm->id]);

        $this->assertSame($this->employeurGsbm->id, $membre->fresh()->employeurDeRattachement()?->id);
    }

    public function test_le_perimetre_suit_le_contrat(): void
    {
        $membre = $this->membre([$this->gsbm->id]);
        $this->contrat($membre, $this->employeurIum);

        $gestionnaireIum = $this->gestionnairePour($this->employeurIum);
        $gestionnaireGsbm = $this->gestionnairePour($this->employeurGsbm);

        $this->actingAs($gestionnaireIum)->get(route('personnel.agents'))
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 1));

        // Son institut d'origine ne le retient plus : c'est l'IUM qui l'emploie.
        $this->actingAs($gestionnaireGsbm)->get(route('personnel.agents'))
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 0));
    }

    /** Le selecteur offre les memes entites que le formulaire de contrat. */
    public function test_le_selecteur_offre_toutes_les_entites_du_perimetre(): void
    {
        $membre = $this->membre([$this->gsbm->id]);

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents.show', $membre))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // GSBM et IUM, pas seulement l'institut de la personne.
                ->has('agent.employeursPossibles', 2)
                ->where('agent.employeurDeduit', 'GSBM'));
    }

    public function test_un_gestionnaire_ne_se_voit_offrir_que_ses_entites(): void
    {
        $membre = $this->membre([$this->ium->id]);

        $this->actingAs($this->gestionnairePour($this->employeurIum))
            ->get(route('personnel.agents.show', $membre))
            ->assertInertia(fn (Assert $page) => $page->has('agent.employeursPossibles', 1));
    }

    // ----------------------------------------------------- le choix de la RH

    public function test_la_rh_tranche_entre_deux_instituts(): void
    {
        $membre = $this->membre([$this->ium->id, $this->gsbm->id]);

        $this->actingAs($this->gestionnaire())
            ->put(route('personnel.rattachement', $membre), ['employeur_id' => $this->employeurGsbm->id])
            ->assertSessionHasNoErrors();

        $membre->refresh();
        $this->assertSame($this->employeurGsbm->id, $membre->employeurDeRattachement()?->id);
        $this->assertFalse($membre->rattachementATrancher());
    }

    /** Le choix pose l'emporte sur la deduction, meme quand elle etait sure. */
    public function test_la_rh_change_un_rattachement_deduit(): void
    {
        $membre = $this->membre([$this->ium->id]);

        $this->actingAs($this->gestionnaire())
            ->put(route('personnel.rattachement', $membre), ['employeur_id' => $this->employeurGsbm->id]);

        $this->assertSame($this->employeurGsbm->id, $membre->fresh()->employeurDeRattachement()?->id);
    }

    public function test_la_rh_rend_la_main_a_la_deduction(): void
    {
        $membre = $this->membre([$this->ium->id]);
        $membre->update(['employeur_id' => $this->employeurGsbm->id]);

        $this->actingAs($this->gestionnaire())
            ->put(route('personnel.rattachement', $membre), ['employeur_id' => null])
            ->assertSessionHasNoErrors();

        $membre->refresh();
        $this->assertNull($membre->employeur_id);
        $this->assertSame($this->employeurIum->id, $membre->employeurDeRattachement()?->id);
    }

    public function test_un_gestionnaire_ne_rattache_pas_hors_de_son_perimetre(): void
    {
        $horsPerimetre = Employeur::create(['nom' => 'Ailleurs', 'sigle' => 'AIL', 'actif' => true]);
        $membre = $this->membre([$this->ium->id]);

        $limite = User::factory()->create();
        $limite->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $limite->employeursRh()->attach($this->employeurIum);

        $this->actingAs($limite)
            ->put(route('personnel.rattachement', $membre), ['employeur_id' => $horsPerimetre->id])
            ->assertForbidden();

        $this->assertNull($membre->fresh()->employeur_id);
    }

    /**
     * Aucune cle etrangere ne tient ce lien : c'est l'application qui libere
     * les rattaches quand l'employeur disparait.
     */
    public function test_supprimer_un_employeur_libere_ceux_qu_il_rattachait(): void
    {
        $membre = $this->membre([$this->ium->id]);
        $membre->update(['employeur_id' => $this->employeurGsbm->id]);

        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);

        $this->actingAs($admin)
            ->delete(route('personnel.employeurs.destroy', $this->employeurGsbm))
            ->assertSessionHasNoErrors();

        $membre->refresh();
        $this->assertNull($membre->employeur_id);

        // Il retombe sur la deduction, sans rester accroche a une entite morte.
        $this->assertSame($this->employeurIum->id, $membre->employeurDeRattachement()?->id);
    }

    /** Une reference devenue orpheline ne fait pas tomber la fiche. */
    public function test_un_rattachement_orphelin_rend_simplement_rien(): void
    {
        $membre = $this->membre([$this->ium->id, $this->gsbm->id]);
        $membre->update(['employeur_id' => 9999]);

        $this->assertNull($membre->fresh()->employeurDeRattachement());

        $this->actingAs($this->gestionnaire())
            ->get(route('personnel.agents.show', $membre))
            ->assertOk();
    }

    public function test_un_lecteur_ne_rattache_rien(): void
    {
        $membre = $this->membre([$this->ium->id]);

        $lecteur = User::factory()->create();
        $lecteur->applications()->attach($this->module, ['role_in_app' => 'lecteur', 'roles' => json_encode(['lecteur'])]);

        $this->actingAs($lecteur)
            ->put(route('personnel.rattachement', $membre), ['employeur_id' => $this->employeurIum->id])
            ->assertForbidden();
    }

    // ------------------------------------------------- ce que la RH voit

    public function test_la_fiche_annonce_qu_il_faut_trancher(): void
    {
        $membre = $this->membre([$this->ium->id, $this->gsbm->id]);

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents.show', $membre))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('agent.rattachementATrancher', true)
                ->where('agent.employeurRetenu', null)
                ->has('agent.employeursPossibles', 2));
    }

    public function test_la_fiche_montre_le_rattachement_deduit(): void
    {
        $membre = $this->membre([$this->ium->id]);

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents.show', $membre))
            ->assertInertia(fn (Assert $page) => $page
                ->where('agent.rattachementATrancher', false)
                ->where('agent.employeurRetenu.sigle', 'IUM')
                ->where('agent.employeurChoisi', null));
    }

    public function test_le_contrat_propose_l_employeur_de_rattachement(): void
    {
        $membre = $this->membre([$this->ium->id, $this->gsbm->id]);
        $membre->update(['employeur_id' => $this->employeurGsbm->id]);

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents.show', $membre))
            ->assertInertia(function (Assert $page) {
                $employeurs = collect($page->toArray()['props']['referentiels']['employeurs']);

                $this->assertTrue($employeurs->firstWhere('sigle', 'GSBM')['rattachement']);
                $this->assertFalse($employeurs->firstWhere('sigle', 'IUM')['rattachement']);
            });
    }

    /**
     * L'effectif d'une entite se lit comme sa liste : le personnel rattache,
     * contrat saisi ou non. Le compter sur les contrats affichait zero tant
     * que la RH n'avait rien saisi.
     */
    public function test_l_effectif_par_employeur_compte_les_rattaches(): void
    {
        $this->membre([$this->ium->id]);
        $this->membre([$this->ium->id]);
        $this->membre([$this->gsbm->id]);

        $this->actingAs($this->gestionnaire())->get(route('personnel.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $employeurs = collect($page->toArray()['props']['employeurs']);

                $this->assertSame(2, $employeurs->firstWhere('sigle', 'IUM')['effectif']);
                $this->assertSame(1, $employeurs->firstWhere('sigle', 'GSBM')['effectif']);

                // Les contrats restent comptes a part.
                $this->assertSame(0, $employeurs->firstWhere('sigle', 'IUM')['contratsActifs']);
            });
    }

    public function test_l_effectif_suit_le_rattachement_choisi(): void
    {
        $membre = $this->membre([$this->ium->id]);
        $membre->update(['employeur_id' => $this->employeurGsbm->id]);

        $this->actingAs($this->gestionnaire())->get(route('personnel.index'))
            ->assertInertia(function (Assert $page) {
                $employeurs = collect($page->toArray()['props']['employeurs']);

                $this->assertSame(0, $employeurs->firstWhere('sigle', 'IUM')['effectif']);
                $this->assertSame(1, $employeurs->firstWhere('sigle', 'GSBM')['effectif']);
            });
    }

    public function test_le_filtre_retrouve_ceux_qui_attendent_un_rattachement(): void
    {
        $this->membre([$this->ium->id, $this->gsbm->id]);      // à trancher
        $this->membre([$this->ium->id]);                        // déduit
        $tranche = $this->membre([$this->ium->id, $this->gsbm->id]);
        $tranche->update(['employeur_id' => $this->employeurIum->id]);

        $this->actingAs($this->gestionnaire())
            ->get(route('personnel.agents', ['statut' => 'a_rattacher']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 1));
    }

    /** Le choix de la RH prime aussi sur le perimetre : la personne suit. */
    public function test_le_perimetre_suit_le_rattachement_choisi(): void
    {
        $membre = $this->membre([$this->ium->id]);
        $membre->update(['employeur_id' => $this->employeurGsbm->id]);

        $gestionnaireGsbm = User::factory()->create();
        $gestionnaireGsbm->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $gestionnaireGsbm->employeursRh()->attach($this->employeurGsbm);

        $this->actingAs($gestionnaireGsbm)->get(route('personnel.agents'))
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 1));
    }

    public function test_rattachee_ailleurs_elle_sort_du_perimetre_de_son_institut(): void
    {
        $membre = $this->membre([$this->ium->id]);
        $membre->update(['employeur_id' => $this->employeurGsbm->id]);

        $gestionnaireIum = User::factory()->create();
        $gestionnaireIum->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $gestionnaireIum->employeursRh()->attach($this->employeurIum);

        $this->actingAs($gestionnaireIum)->get(route('personnel.agents'))
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 0));
    }
}
