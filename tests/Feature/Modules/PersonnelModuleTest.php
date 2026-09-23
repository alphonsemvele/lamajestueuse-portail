<?php

namespace Tests\Feature\Modules;

use App\Models\Agent;
use App\Models\Ajustement;
use App\Models\Application;
use App\Models\Bulletin;
use App\Models\CategorieRh;
use App\Models\Contrat;
use App\Models\Diplome;
use App\Models\Echelon;
use App\Models\Employeur;
use App\Models\EvenementCarriere;
use App\Models\Indemnite;
use App\Models\ProfilSalaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Le module Personnel & paie de bout en bout : qui y entre, qui y ecrit, et
 * ce que produisent les ecrans.
 */
class PersonnelModuleTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    private Employeur $employeur;

    private Echelon $echelon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        $this->employeur = Employeur::create(['nom' => 'Institut Universitaire', 'sigle' => 'IUM']);

        $categorie = CategorieRh::create(['libelle' => 'Enseignants']);
        $this->echelon = Echelon::create(['categorie_rh_id' => $categorie->id, 'numero' => 1, 'salaire' => 200000]);
    }

    /** Un employe qui a la tuile : il consulte, il n'ecrit pas. */
    private function lecteur(): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module, ['role_in_app' => 'lecteur', 'roles' => json_encode(['lecteur'])]);

        return $user;
    }

    /**
     * Un gestionnaire RH : le role declare dans config/modules.php, plus les
     * entites que l'administrateur du portail lui a confiees.
     */
    private function gestionnaire(?Employeur $employeur = null): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $user->employeursRh()->attach($employeur ?? $this->employeur);

        return $user;
    }

    private function agent(): Agent
    {
        return Agent::create(['user_id' => User::factory()->create(['lastname' => 'MBALLA'])->id]);
    }

    private function contrat(?Agent $agent = null, array $attributs = []): Contrat
    {
        return Contrat::create(array_merge([
            'agent_id' => ($agent ?? $this->agent())->id,
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi',
            'poste' => 'Enseignant',
            'date_debut' => '2026-01-01',
            'quotite' => 100,
            'echelon_id' => $this->echelon->id,
            'statut' => 'actif',
        ], $attributs));
    }

    // ------------------------------------------------------------- acces

    public function test_le_module_est_inaccessible_sans_la_tuile(): void
    {
        $this->actingAs(User::factory()->create())->get(route('personnel.index'))->assertForbidden();
    }

    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        $this->get(route('personnel.index'))->assertRedirect(route('login'));
    }

    public function test_le_module_desactive_repond_404(): void
    {
        $lecteur = $this->lecteur();
        $this->module->update(['is_active' => false]);

        $this->actingAs($lecteur)->get(route('personnel.index'))->assertNotFound();
    }

    public function test_le_lecteur_consulte_mais_n_ecrit_pas(): void
    {
        $lecteur = $this->lecteur();

        $this->actingAs($lecteur)->get(route('personnel.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/personnel/index')
                ->where('peutGerer', false));

        $this->actingAs($lecteur)->post(route('personnel.agents.store'), ['user_id' => User::factory()->create()->id])
            ->assertForbidden();

        $this->actingAs($lecteur)->get(route('personnel.employeurs'))->assertForbidden();
        $this->actingAs($lecteur)->get(route('personnel.profils'))->assertForbidden();
    }

    public function test_l_administrateur_du_portail_gere_sans_role_particulier(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('personnel.employeurs'))->assertOk();
    }

    public function test_le_gestionnaire_rh_gere(): void
    {
        $this->actingAs($this->gestionnaire())->get(route('personnel.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('peutGerer', true));
    }

    // ---------------------------------------------------------- dossiers

    public function test_le_tableau_de_bord_resume_les_effectifs_et_la_masse(): void
    {
        $contrat = $this->contrat();
        Bulletin::create([
            'contrat_id' => $contrat->id, 'employeur_id' => $this->employeur->id, 'agent_id' => $contrat->agent_id,
            'mois' => 9, 'annee' => 2026, 'salaire_base' => 200000, 'salaire_net' => 200000, 'statut' => 'brouillon',
        ]);

        $this->actingAs($this->gestionnaire())->get(route('personnel.index', ['mois' => 9, 'annee' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('chiffres.agents', 1)
                ->where('chiffres.contratsActifs', 1)
                ->where('chiffres.masse.net', fn ($net) => (float) $net === 200000.0)
                ->has('employeurs', 1)
                ->where('employeurs.0.effectif', 1));
    }

    public function test_les_contrats_a_echeance_remontent(): void
    {
        $this->contrat(null, ['date_fin' => now()->addDays(20)->toDateString()]);
        $this->contrat(null, ['date_fin' => now()->addMonths(6)->toDateString()]);

        $this->actingAs($this->gestionnaire())->get(route('personnel.index'))
            ->assertInertia(fn (Assert $page) => $page->has('echeances', 1));
    }

    public function test_l_ouverture_d_un_dossier_rattache_un_compte_existant(): void
    {
        $compte = User::factory()->create();

        $this->actingAs($this->gestionnaire())
            ->post(route('personnel.agents.store'), ['user_id' => $compte->id])
            ->assertRedirect();

        $this->assertDatabaseHas('agents', ['user_id' => $compte->id]);
    }

    public function test_un_compte_n_a_qu_un_seul_dossier(): void
    {
        $agent = $this->agent();

        $this->actingAs($this->gestionnaire())
            ->post(route('personnel.agents.store'), ['user_id' => $agent->user_id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_la_liste_ne_propose_que_les_comptes_sans_dossier(): void
    {
        $this->agent();
        User::factory()->create();

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/personnel/agents/index')
                ->has('agents.data', 1)
                // Le gestionnaire lui-meme et le compte libre, pas l'agent deja suivi.
                ->has('comptesSansDossier', 2));
    }

    public function test_la_recherche_filtre_par_nom(): void
    {
        $this->agent();
        Agent::create(['user_id' => User::factory()->create(['lastname' => 'ATANGANA'])->id]);

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents', ['q' => 'atangana']))
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 1));
    }

    public function test_le_filtre_sans_contrat_isole_les_dossiers_a_completer(): void
    {
        $this->contrat();
        $this->agent();

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents', ['statut' => 'sans_contrat']))
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 1));
    }

    public function test_la_fiche_reunit_dossier_diplomes_contrats_et_carriere(): void
    {
        $agent = $this->agent();
        $this->contrat($agent);
        $agent->diplomes()->create(['intitule' => 'Master en gestion', 'niveau' => 'Master']);

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents.show', $agent))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/personnel/agents/fiche')
                ->has('diplomes', 1)
                ->has('contrats', 1)
                // Le contrat cree hors interface ne pose pas d'evenement.
                ->has('evenements', 0)
                ->has('referentiels.employeurs', 1));
    }

    public function test_la_mise_a_jour_du_dossier_administratif(): void
    {
        $agent = $this->agent();

        $this->actingAs($this->gestionnaire())->put(route('personnel.agents.update', $agent), [
            'date_naissance' => '1988-04-12',
            'lieu_naissance' => 'Douala',
            'situation_familiale' => 'marie',
            'enfants' => 3,
            'numero_cnps' => 'CN-99221',
        ])->assertRedirect();

        $this->assertDatabaseHas('agents', ['id' => $agent->id, 'enfants' => 3, 'situation_familiale' => 'marie']);
    }

    public function test_une_situation_familiale_inconnue_est_refusee(): void
    {
        $agent = $this->agent();

        $this->actingAs($this->gestionnaire())
            ->put(route('personnel.agents.update', $agent), ['situation_familiale' => 'concubinage'])
            ->assertSessionHasErrors('situation_familiale');
    }

    public function test_le_cycle_de_vie_d_un_diplome(): void
    {
        $agent = $this->agent();
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->post(route('personnel.diplomes.store', $agent), [
            'intitule' => 'Licence en droit',
            'niveau' => 'Licence',
            'annee_obtention' => 2012,
            'piece_fournie' => true,
        ])->assertRedirect();

        $diplome = Diplome::firstOrFail();
        $this->assertTrue($diplome->piece_fournie);

        $this->actingAs($gestionnaire)->put(route('personnel.diplomes.update', $diplome), [
            'intitule' => 'Licence en droit public',
            'piece_fournie' => false,
        ])->assertRedirect();

        $this->assertSame('Licence en droit public', $diplome->refresh()->intitule);

        $this->actingAs($gestionnaire)->delete(route('personnel.diplomes.destroy', $diplome))->assertRedirect();
        $this->assertSame(0, Diplome::count());
    }

    public function test_un_niveau_hors_liste_est_refuse(): void
    {
        $this->actingAs($this->gestionnaire())
            ->post(route('personnel.diplomes.store', $this->agent()), ['intitule' => 'Brevet', 'niveau' => 'Certificat maison'])
            ->assertSessionHasErrors('niveau');
    }

    // ---------------------------------------------------------- contrats

    public function test_la_creation_d_un_contrat_inscrit_le_recrutement_dans_la_carriere(): void
    {
        $agent = $this->agent();

        $this->actingAs($this->gestionnaire())->post(route('personnel.contrats.store', $agent), [
            'employeur_id' => $this->employeur->id,
            'type' => 'cdd',
            'poste' => 'Chargé de cours',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31',
            'quotite' => 50,
            'echelon_id' => $this->echelon->id,
            'statut' => 'actif',
        ])->assertRedirect();

        $contrat = Contrat::firstOrFail();
        $this->assertSame(100000.0, $contrat->salaireBase());

        $this->assertDatabaseHas('evenements_carriere', [
            'agent_id' => $agent->id,
            'contrat_id' => $contrat->id,
            'type' => 'recrutement',
        ]);
    }

    public function test_une_date_de_fin_anterieure_au_debut_est_refusee(): void
    {
        $this->actingAs($this->gestionnaire())->post(route('personnel.contrats.store', $this->agent()), [
            'employeur_id' => $this->employeur->id,
            'type' => 'cdd',
            'poste' => 'Chargé de cours',
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-08-01',
            'quotite' => 100,
            'statut' => 'actif',
        ])->assertSessionHasErrors('date_fin');
    }

    public function test_un_contrat_deja_paye_ne_se_supprime_pas(): void
    {
        $contrat = $this->contrat();
        Bulletin::create([
            'contrat_id' => $contrat->id, 'employeur_id' => $this->employeur->id, 'agent_id' => $contrat->agent_id,
            'mois' => 9, 'annee' => 2026, 'statut' => 'paye',
        ]);

        $this->actingAs($this->gestionnaire())->delete(route('personnel.contrats.destroy', $contrat))
            ->assertSessionHasErrors('contrat');

        $this->assertDatabaseHas('contrats', ['id' => $contrat->id]);
    }

    public function test_terminer_un_contrat_conserve_le_motif(): void
    {
        $contrat = $this->contrat();

        $this->actingAs($this->gestionnaire())->put(route('personnel.contrats.update', $contrat), [
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi',
            'poste' => 'Enseignant',
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-09-30',
            'quotite' => 100,
            'statut' => 'termine',
            'motif_fin' => 'Démission',
        ])->assertRedirect();

        $this->assertSame('Démission', $contrat->refresh()->motif_fin);
    }

    public function test_un_evenement_de_carriere_s_ajoute_et_se_retire(): void
    {
        $agent = $this->agent();
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->post(route('personnel.evenements.store', $agent), [
            'date_evenement' => '2026-09-01',
            'type' => 'avancement',
            'libelle' => 'Passage à l’échelon 2',
        ])->assertRedirect();

        $evenement = EvenementCarriere::firstOrFail();
        $this->assertSame($gestionnaire->id, $evenement->saisi_par);

        $this->actingAs($gestionnaire)->delete(route('personnel.evenements.destroy', $evenement))->assertRedirect();
        $this->assertSame(0, EvenementCarriere::count());
    }

    // -------------------------------------------------------------- paie

    public function test_la_preparation_cree_les_bulletins_du_mois(): void
    {
        $this->contrat();
        $this->contrat();

        $this->actingAs($this->gestionnaire())->post(route('personnel.paie.generer'), [
            'mois' => 9, 'annee' => 2026, 'employeur_id' => $this->employeur->id,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(2, Bulletin::count());
    }

    public function test_un_lecteur_ne_prepare_pas_la_paie(): void
    {
        $this->contrat();

        $this->actingAs($this->lecteur())->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026])
            ->assertForbidden();

        $this->assertSame(0, Bulletin::count());
    }

    public function test_l_ecran_de_paie_affiche_la_masse_et_la_repartition(): void
    {
        $this->contrat();
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026]);

        $this->actingAs($gestionnaire)->get(route('personnel.paie.index', ['mois' => 9, 'annee' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/personnel/paie/index')
                ->has('bulletins', 1)
                ->where('masse.net', fn ($net) => (float) $net === 200000.0)
                ->where('repartition.brouillon', 1));
    }

    public function test_le_traitement_en_lot_valide_puis_paie(): void
    {
        $this->contrat();
        $gestionnaire = $this->gestionnaire();
        $this->actingAs($gestionnaire)->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026]);

        $ids = Bulletin::pluck('id')->all();

        $this->actingAs($gestionnaire)->post(route('personnel.paie.lot'), ['action' => 'valider', 'bulletins' => $ids])
            ->assertRedirect();
        $this->assertSame('valide', Bulletin::first()->statut);

        $this->actingAs($gestionnaire)->post(route('personnel.paie.lot'), ['action' => 'payer', 'bulletins' => $ids])
            ->assertRedirect();
        $this->assertSame('paye', Bulletin::first()->statut);
    }

    public function test_un_lot_qui_echoue_remonte_l_erreur_sans_bloquer_les_autres(): void
    {
        $premier = $this->contrat();
        $this->contrat();
        $gestionnaire = $this->gestionnaire();
        $this->actingAs($gestionnaire)->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026]);

        // Le premier est deja paye : la mise en paiement du lot doit l'ignorer
        // en le signalant, et traiter le second malgre tout.
        $bulletin = Bulletin::where('contrat_id', $premier->id)->first();
        $bulletin->update(['statut' => 'paye']);

        $this->actingAs($gestionnaire)->post(route('personnel.paie.lot'), [
            'action' => 'valider',
            'bulletins' => Bulletin::pluck('id')->all(),
        ])->assertSessionHasErrors('paie');

        $this->assertSame(1, Bulletin::where('statut', 'valide')->count());
    }

    public function test_le_bulletin_detaille_ses_lignes(): void
    {
        $profil = ProfilSalaire::create(['nom' => 'Enseignant 1', 'echelon_id' => $this->echelon->id]);
        $profil->indemnites()->attach(
            Indemnite::create(['libelle' => 'Logement'])->id,
            ['type_calcul' => 'pourcentage', 'valeur' => 10]
        );
        $this->contrat(null, ['profil_salaire_id' => $profil->id]);

        $gestionnaire = $this->gestionnaire();
        $this->actingAs($gestionnaire)->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026]);

        $this->actingAs($gestionnaire)->get(route('personnel.paie.bulletin', Bulletin::first()))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/personnel/paie/bulletin')
                ->where('bulletin.totalIndemnites', fn ($v) => (float) $v === 20000.0)
                ->where('bulletin.detail.indemnites.0.libelle', 'Logement')
                ->where('bulletin.detail.indemnites.0.montant', fn ($v) => (float) $v === 20000.0));
    }

    public function test_un_ajustement_recalcule_aussitot_le_brouillon(): void
    {
        $contrat = $this->contrat();
        $gestionnaire = $this->gestionnaire();
        $this->actingAs($gestionnaire)->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026]);

        $this->actingAs($gestionnaire)->post(route('personnel.ajustements.store', $contrat), [
            'mois' => 9, 'annee' => 2026, 'type' => 'bonus', 'mode' => 'fixe',
            'libelle' => 'Prime de rentrée', 'montant' => 40000,
        ])->assertRedirect();

        $this->assertSame(240000.0, (float) Bulletin::first()->salaire_net);
    }

    public function test_un_ajustement_ne_s_ajoute_pas_a_un_bulletin_fige(): void
    {
        $contrat = $this->contrat();
        $gestionnaire = $this->gestionnaire();
        $this->actingAs($gestionnaire)->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026]);
        Bulletin::first()->update(['statut' => 'valide']);

        $this->actingAs($gestionnaire)->post(route('personnel.ajustements.store', $contrat), [
            'mois' => 9, 'annee' => 2026, 'type' => 'bonus', 'mode' => 'fixe',
            'libelle' => 'Prime tardive', 'montant' => 40000,
        ])->assertSessionHasErrors('ajustement');

        $this->assertSame(0, Ajustement::count());
    }

    public function test_un_pourcentage_superieur_a_cent_est_refuse(): void
    {
        $contrat = $this->contrat();

        $this->actingAs($this->gestionnaire())->post(route('personnel.ajustements.store', $contrat), [
            'mois' => 9, 'annee' => 2026, 'type' => 'retenue', 'mode' => 'pourcentage',
            'libelle' => 'Absences', 'montant' => 150,
        ])->assertSessionHasErrors('montant');
    }

    public function test_le_retrait_d_un_ajustement_recalcule_le_brouillon(): void
    {
        $contrat = $this->contrat();
        $gestionnaire = $this->gestionnaire();
        $this->actingAs($gestionnaire)->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026]);
        $this->actingAs($gestionnaire)->post(route('personnel.ajustements.store', $contrat), [
            'mois' => 9, 'annee' => 2026, 'type' => 'bonus', 'mode' => 'fixe',
            'libelle' => 'Prime', 'montant' => 40000,
        ]);

        $this->actingAs($gestionnaire)->delete(route('personnel.ajustements.destroy', Ajustement::first()))
            ->assertRedirect();

        $this->assertSame(200000.0, (float) Bulletin::first()->salaire_net);
    }

    public function test_la_note_interne_s_enregistre(): void
    {
        $this->contrat();
        $gestionnaire = $this->gestionnaire();
        $this->actingAs($gestionnaire)->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026]);

        $this->actingAs($gestionnaire)->put(route('personnel.paie.note', Bulletin::first()), [
            'note' => 'Virement groupé du 28.',
        ])->assertRedirect();

        $this->assertSame('Virement groupé du 28.', Bulletin::first()->note);
    }

    // ------------------------------------------------------ referentiels

    /** Chaque référentiel a son entrée de menu, comme dans IUM. */
    public function test_chaque_referentiel_a_son_propre_ecran(): void
    {
        $gestionnaire = $this->gestionnaire();

        $sections = [
            'personnel.employeurs' => 'employeurs',
            'personnel.categories' => 'categories',
            'personnel.profils' => 'profils',
            'personnel.indemnites' => 'indemnites',
            'personnel.retenues' => 'retenues',
        ];

        foreach ($sections as $route => $section) {
            $this->actingAs($gestionnaire)->get(route($route))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('modules/personnel/referentiels')
                    ->where('section', $section)
                    // Un profil se construit a partir de tout le reste :
                    // l'ecran recoit les cinq referentiels quoi qu'il arrive.
                    ->has('employeurs', 1)
                    ->has('categories', 1)
                    ->has('categories.0.echelons', 1));
        }
    }

    public function test_le_registre_des_bulletins_couvre_toutes_les_periodes(): void
    {
        $contrat = $this->contrat();
        $gestionnaire = $this->gestionnaire();

        foreach ([[8, 2026], [9, 2026]] as [$mois, $annee]) {
            Bulletin::create([
                'contrat_id' => $contrat->id, 'employeur_id' => $this->employeur->id, 'agent_id' => $contrat->agent_id,
                'mois' => $mois, 'annee' => $annee, 'salaire_net' => 200000, 'statut' => 'paye',
            ]);
        }

        $this->actingAs($gestionnaire)->get(route('personnel.bulletins'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/personnel/paie/bulletins')
                ->has('bulletins.data', 2)
                ->has('annees', 1));

        $this->actingAs($gestionnaire)->get(route('personnel.bulletins', ['statut' => 'brouillon']))
            ->assertInertia(fn (Assert $page) => $page->has('bulletins.data', 0));

        $this->actingAs($gestionnaire)->get(route('personnel.bulletins', ['q' => 'MBALLA']))
            ->assertInertia(fn (Assert $page) => $page->has('bulletins.data', 2));
    }

    public function test_le_sigle_d_un_employeur_est_unique(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('personnel.employeurs.store'), [
            'nom' => 'Autre institut', 'sigle' => 'IUM',
        ])->assertSessionHasErrors('sigle');
    }

    public function test_un_employeur_avec_des_contrats_ne_se_supprime_pas(): void
    {
        $this->contrat();

        $this->actingAs(User::factory()->admin()->create())->delete(route('personnel.employeurs.destroy', $this->employeur))
            ->assertSessionHasErrors('employeur');
    }

    /** Les entites du groupe relevent de l'administration du portail. */
    public function test_un_gestionnaire_ne_cree_pas_d_entite(): void
    {
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->post(route('personnel.employeurs.store'), [
            'nom' => 'Institut inventé', 'sigle' => 'XXX',
        ])->assertForbidden();

        $this->actingAs($gestionnaire)->delete(route('personnel.employeurs.destroy', $this->employeur))
            ->assertForbidden();

        $this->assertSame(1, Employeur::count());
    }

    public function test_deux_echelons_ne_partagent_pas_un_numero_dans_une_categorie(): void
    {
        $this->actingAs($this->gestionnaire())->post(
            route('personnel.echelons.store', $this->echelon->categorie_rh_id),
            ['numero' => 1, 'salaire' => 250000]
        )->assertSessionHasErrors('numero');
    }

    public function test_un_echelon_utilise_ne_se_supprime_pas(): void
    {
        $this->contrat();

        $this->actingAs($this->gestionnaire())->delete(route('personnel.echelons.destroy', $this->echelon))
            ->assertSessionHasErrors('echelon');
    }

    public function test_un_profil_enregistre_ses_indemnites_et_retenues(): void
    {
        $indemnite = Indemnite::create(['libelle' => 'Transport']);

        $this->actingAs($this->gestionnaire())->post(route('personnel.profils.store'), [
            'nom' => 'Enseignant 1',
            'echelon_id' => $this->echelon->id,
            'indemnites' => [['id' => $indemnite->id, 'type_calcul' => 'fixe', 'valeur' => 25000]],
        ])->assertRedirect();

        $profil = ProfilSalaire::firstOrFail();
        $this->assertSame(25000.0, (float) $profil->indemnites()->first()->pivot->valeur);
    }

    public function test_modifier_un_profil_remplace_ses_lignes(): void
    {
        $premiere = Indemnite::create(['libelle' => 'Transport']);
        $seconde = Indemnite::create(['libelle' => 'Logement']);
        $profil = ProfilSalaire::create(['nom' => 'Enseignant 1', 'echelon_id' => $this->echelon->id]);
        $profil->indemnites()->attach($premiere->id, ['type_calcul' => 'fixe', 'valeur' => 25000]);

        $this->actingAs($this->gestionnaire())->put(route('personnel.profils.update', $profil), [
            'nom' => 'Enseignant 1',
            'echelon_id' => $this->echelon->id,
            'indemnites' => [['id' => $seconde->id, 'type_calcul' => 'pourcentage', 'valeur' => 10]],
        ])->assertRedirect();

        $lignes = $profil->refresh()->indemnites;
        $this->assertCount(1, $lignes);
        $this->assertSame('Logement', $lignes->first()->libelle);
    }

    public function test_un_profil_utilise_ne_se_supprime_pas(): void
    {
        $profil = ProfilSalaire::create(['nom' => 'Enseignant 1', 'echelon_id' => $this->echelon->id]);
        $this->contrat(null, ['profil_salaire_id' => $profil->id]);

        $this->actingAs($this->gestionnaire())->delete(route('personnel.profils.destroy', $profil))
            ->assertSessionHasErrors('profil');
    }
}
