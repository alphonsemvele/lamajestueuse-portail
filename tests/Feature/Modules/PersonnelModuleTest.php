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

    /** L'institut, cote portail : c'est lui qui rattache le personnel. */
    private Application $institut;

    private Employeur $employeur;

    private Echelon $echelon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        $this->institut = Application::factory()->create(['name' => 'IUM']);
        $this->employeur = Employeur::create([
            'nom' => 'Institut Universitaire',
            'sigle' => 'IUM',
            'application_id' => $this->institut->id,
        ]);

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

    /** Un membre du personnel, rattache a l'institut cote portail. */
    private function membre(string $nom = 'MBALLA'): User
    {
        $membre = User::factory()->create(['lastname' => $nom]);
        $membre->applications()->attach($this->institut);

        return $membre;
    }

    private function agent(string $nom = 'MBALLA'): Agent
    {
        return Agent::create(['user_id' => $this->membre($nom)->id]);
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

        $this->actingAs($lecteur)->put(route('personnel.agents.update', $this->agent()->user), ['enfants' => 2])
            ->assertForbidden();

        $this->actingAs($lecteur)->get(route('personnel.employeurs'))->assertForbidden();
        $this->actingAs($lecteur)->get(route('personnel.profils'))->assertForbidden();
    }

    public function test_l_administrateur_du_portail_gere_sans_role_particulier(): void
    {
        $this->actingAs(User::factory()->superadmin()->create())->get(route('personnel.employeurs'))->assertOk();
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

    /** Le dossier n'est plus un objet a creer d'abord : il nait a la saisie. */
    public function test_le_dossier_nait_a_la_premiere_saisie(): void
    {
        $compte = $this->membre('NKOA');

        $this->assertSame(0, Agent::count());

        // La fiche s'ouvre sans rien creer.
        $this->actingAs($this->gestionnaire())->get(route('personnel.agents.show', $compte))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('agent.dossierOuvert', false)
                ->where('agent.userId', $compte->id)
                ->has('contrats', 0));

        $this->assertSame(0, Agent::count());

        // La premiere saisie, elle, l'ouvre.
        $this->actingAs($this->gestionnaire())
            ->put(route('personnel.agents.update', $compte), ['name' => $compte->name, 'enfants' => 2])
            ->assertRedirect();

        $this->assertDatabaseHas('agents', ['user_id' => $compte->id, 'enfants' => 2]);
    }

    public function test_deux_saisies_de_suite_ne_creent_qu_un_dossier(): void
    {
        $compte = $this->membre('NKOA');
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->put(route('personnel.agents.update', $compte), ['name' => $compte->name, 'enfants' => 1]);
        $this->actingAs($gestionnaire)->post(route('personnel.diplomes.store', $compte), ['intitule' => 'Licence']);

        $this->assertSame(1, Agent::where('user_id', $compte->id)->count());
    }

    /** Un compte en attente de validation figure aussi dans la liste. */
    public function test_la_liste_montre_les_comptes_non_encore_valides(): void
    {
        $enAttente = $this->membre('NOUVEAU');
        $enAttente->update(['status' => 'pending']);

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('agents.data', fn ($liste) => collect($liste)
                    ->contains(fn ($m) => $m['statutCompte'] === 'pending'))
                ->where('enAttente', 1));
    }

    public function test_le_filtre_par_etat_de_compte(): void
    {
        $this->membre('ACTIF');
        $this->membre('ATTENTE')->update(['status' => 'pending']);

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents', ['compte' => 'pending']))
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 1));
    }

    /** Un matricule ne se donne pas avant la validation du compte. */
    public function test_un_compte_en_attente_ne_recoit_pas_de_matricule(): void
    {
        $enAttente = $this->membre('NOUVEAU');
        // La fabrique donne un matricule : on le retire pour poser le cas.
        $enAttente->update(['status' => 'pending', 'matricule' => null]);

        $this->actingAs($this->gestionnaire())->getJson(route('personnel.matricules.apourvoir'))
            ->assertOk()
            ->assertJsonMissing(['id' => $enAttente->id]);

        $this->actingAs($this->gestionnaire())
            ->post(route('personnel.matricules.attribuer'), ['personnes' => [$enAttente->id]])
            ->assertSessionHasErrors('matricules');

        $this->assertNull($enAttente->refresh()->matricule);
    }

    public function test_la_liste_montre_le_personnel_du_portail_dossier_ou_non(): void
    {
        $this->agent();                  // dossier deja ouvert
        User::factory()->create();       // compte sans dossier

        $this->actingAs(User::factory()->superadmin()->create())->get(route('personnel.agents'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/personnel/agents/index')
                // Les deux comptes, plus l'administrateur lui-meme.
                ->has('agents.data', 3)
                ->where('sansDossier', 2));
    }

    public function test_le_filtre_dossier_non_renseigne(): void
    {
        $this->agent();
        User::factory()->create();

        $this->actingAs(User::factory()->superadmin()->create())
            ->get(route('personnel.agents', ['statut' => 'sans_dossier']))
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 2));
    }

    public function test_la_recherche_filtre_par_nom(): void
    {
        $this->agent();
        $this->agent('ATANGANA');

        $this->actingAs(User::factory()->superadmin()->create())->get(route('personnel.agents', ['q' => 'atangana']))
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 1));
    }

    public function test_le_filtre_sans_contrat_isole_les_dossiers_a_completer(): void
    {
        $this->contrat();
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->get(route('personnel.agents', ['statut' => 'sans_contrat']))
            // Tout le monde sauf la personne sous contrat.
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 1));
    }

    public function test_la_fiche_reunit_dossier_diplomes_contrats_et_carriere(): void
    {
        $agent = $this->agent();
        $this->contrat($agent);
        $agent->diplomes()->create(['intitule' => 'Master en gestion', 'niveau' => 'Master']);

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents.show', $agent->user))
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

        $this->actingAs($this->gestionnaire())->put(route('personnel.agents.update', $agent->user), [
            'name' => $agent->user->name,
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
            ->put(route('personnel.agents.update', $agent->user), [
                'name' => $agent->user->name,
                'situation_familiale' => 'concubinage',
            ])
            ->assertSessionHasErrors('situation_familiale');
    }

    public function test_le_cycle_de_vie_d_un_diplome(): void
    {
        $agent = $this->agent();
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->post(route('personnel.diplomes.store', $agent->user), [
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
            ->post(route('personnel.diplomes.store', $this->agent()->user), ['intitule' => 'Brevet', 'niveau' => 'Certificat maison'])
            ->assertSessionHasErrors('niveau');
    }

    // ---------------------------------------------------------- identité

    public function test_la_rh_saisit_le_matricule_et_l_identite(): void
    {
        $membre = $this->membre('NKOA');

        $this->actingAs($this->gestionnaire())->put(route('personnel.agents.update', $membre), [
            'name' => 'Claire',
            'lastname' => 'NKOA',
            'matricule' => 'LM-2026-118',
            'email' => 'claire.nkoa@lamajestueuse.cm',
            'phone' => '+237 699 00 11 22',
            'poste' => 'Chargée de scolarité',
            'enfants' => 2,
        ])->assertRedirect();

        $membre->refresh();
        $this->assertSame('LM-2026-118', $membre->matricule);
        $this->assertSame('Claire', $membre->name);
        $this->assertSame('claire.nkoa@lamajestueuse.cm', $membre->email);
        $this->assertSame('Chargée de scolarité', $membre->poste);

        // Le dossier RH suit dans la meme saisie.
        $this->assertDatabaseHas('agents', ['user_id' => $membre->id, 'enfants' => 2]);
    }

    public function test_un_matricule_deja_pris_est_refuse(): void
    {
        $this->membre('NKOA')->update(['matricule' => 'LM-0001']);
        $autre = $this->membre('ATANGANA');

        $this->actingAs($this->gestionnaire())->put(route('personnel.agents.update', $autre), [
            'name' => 'Paul',
            'matricule' => 'LM-0001',
        ])->assertSessionHasErrors('matricule');
    }

    public function test_le_lecteur_ne_touche_pas_a_l_identite(): void
    {
        $membre = $this->membre('NKOA');
        $lecteur = User::factory()->create();
        $lecteur->applications()->attach($this->module, ['role_in_app' => 'lecteur', 'roles' => json_encode(['lecteur'])]);

        $this->actingAs($lecteur)->put(route('personnel.agents.update', $membre), [
            'name' => 'Pirate', 'matricule' => 'LM-9999',
        ])->assertForbidden();

        $this->assertNotSame('LM-9999', $membre->refresh()->matricule);
    }

    // --------------------------------------------------- nouvel arrivant

    public function test_la_rh_cree_la_fiche_d_un_arrivant(): void
    {
        $this->actingAs($this->gestionnaire())->post(route('personnel.agents.store'), [
            'name' => 'Paul',
            'lastname' => 'ATANGANA',
            'matricule' => 'LM-2026-200',
            'email' => 'paul.atangana@lamajestueuse.cm',
            'poste' => 'Enseignant',
            'employeur_id' => $this->employeur->id,
            'password' => 'motdepasse2026',
            'password_confirmation' => 'motdepasse2026',
        ])->assertRedirect();

        $cree = User::where('matricule', 'LM-2026-200')->firstOrFail();
        $this->assertSame('active', $cree->status);
        $this->assertSame('employee', $cree->role);
        $this->assertSame('IUM', $cree->entite);

        // Rattache a son institut, donc visible de la RH ; mais sans acces
        // au module lui-meme : cela reste la main de l'administrateur.
        $this->assertTrue($cree->applications()->where('applications.id', $this->institut->id)->exists());
        $this->assertFalse($cree->applications()->where('applications.id', $this->module->id)->exists());
    }

    public function test_l_arrivant_apparait_aussitot_dans_la_liste(): void
    {
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->post(route('personnel.agents.store'), [
            'name' => 'Paul',
            'matricule' => 'LM-2026-200',
            'employeur_id' => $this->employeur->id,
            'password' => 'motdepasse2026',
            'password_confirmation' => 'motdepasse2026',
        ]);

        $this->actingAs($gestionnaire)->get(route('personnel.agents', ['q' => 'LM-2026-200']))
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 1));
    }

    public function test_un_mot_de_passe_trop_court_est_refuse(): void
    {
        $this->actingAs($this->gestionnaire())->post(route('personnel.agents.store'), [
            'name' => 'Paul',
            'employeur_id' => $this->employeur->id,
            'password' => 'court',
            'password_confirmation' => 'court',
        ])->assertSessionHasErrors('password');

        $this->assertSame(0, User::where('name', 'Paul')->count());
    }

    public function test_on_ne_cree_pas_dans_une_entite_non_suivie(): void
    {
        $autre = Employeur::create(['nom' => 'Institut de Formation', 'sigle' => 'IFPM']);

        $this->actingAs($this->gestionnaire())->post(route('personnel.agents.store'), [
            'name' => 'Paul',
            'employeur_id' => $autre->id,
            'password' => 'motdepasse2026',
            'password_confirmation' => 'motdepasse2026',
        ])->assertForbidden();
    }

    /** Sans institut rattache, la personne creee serait invisible. */
    public function test_une_entite_sans_institut_refuse_la_creation(): void
    {
        $orpheline = Employeur::create(['nom' => 'Entité isolée', 'sigle' => 'ISO']);
        $gestionnaire = $this->gestionnaire();
        $gestionnaire->employeursRh()->attach($orpheline);

        $this->actingAs($gestionnaire)->post(route('personnel.agents.store'), [
            'name' => 'Paul',
            'employeur_id' => $orpheline->id,
            'password' => 'motdepasse2026',
            'password_confirmation' => 'motdepasse2026',
        ])->assertSessionHasErrors('employeur_id');

        $this->assertSame(0, User::where('name', 'Paul')->count());
    }

    // ---------------------------------------------------------- contrats

    /** Le contrat part du poste déclaré à l'inscription, pour cet institut. */
    /**
     * Le filtre entite doit se lire comme la liste : rattache a l'institut
     * suffit, sans attendre qu'un contrat soit saisi.
     */
    public function test_le_filtre_entite_trouve_les_rattaches_sans_contrat(): void
    {
        // Rattache a l'institut a l'inscription, aucun contrat.
        $membre = User::factory()->create(['lastname' => 'NKOA']);
        $membre->applications()->attach($this->institut);

        $this->actingAs($this->gestionnaire())
            ->get(route('personnel.agents', ['employeur' => $this->employeur->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('agents.data', 1)
                ->where('agents.data.0.nom', fn ($nom) => str_contains((string) $nom, 'NKOA')));
    }

    public function test_le_filtre_entite_trouve_aussi_par_le_contrat(): void
    {
        $agent = $this->agent('MBALLA');
        $this->contrat($agent);

        $this->actingAs($this->gestionnaire())
            ->get(route('personnel.agents', ['employeur' => $this->employeur->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 1));
    }

    public function test_le_filtre_entite_ecarte_ceux_d_une_autre_entite(): void
    {
        $autreInstitut = Application::factory()->create(['name' => 'GSBM']);
        $autre = Employeur::create([
            'nom' => 'Groupe Scolaire', 'sigle' => 'GSBM', 'application_id' => $autreInstitut->id,
        ]);

        $ailleurs = User::factory()->create(['lastname' => 'ESSOMBA']);
        $ailleurs->applications()->attach($autreInstitut);

        $ici = User::factory()->create(['lastname' => 'NKOA']);
        $ici->applications()->attach($this->institut);

        // L'administrateur voit tout : son perimetre ne masque rien.
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);

        $this->actingAs($admin)
            ->get(route('personnel.agents', ['employeur' => $autre->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('agents.data', 1)
                ->where('agents.data.0.nom', fn ($nom) => str_contains((string) $nom, 'ESSOMBA')));
    }

    public function test_la_fiche_propose_le_poste_declare(): void
    {
        $membre = User::factory()->create(['lastname' => 'NKOA', 'poste' => 'Agent polyvalent']);
        $membre->applications()->attach($this->institut, ['poste' => 'Chargée de scolarité']);

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents.show', $membre))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('referentiels.employeurs.0.posteDeclare', 'Chargée de scolarité'));
    }

    public function test_a_defaut_c_est_le_poste_du_compte(): void
    {
        $membre = User::factory()->create(['lastname' => 'NKOA', 'poste' => 'Agent polyvalent']);
        // Rattaché sans poste précisé pour cet institut.
        $membre->applications()->attach($this->institut);

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents.show', $membre))
            ->assertInertia(fn (Assert $page) => $page
                ->where('referentiels.employeurs.0.posteDeclare', 'Agent polyvalent'));
    }

    /**
     * Un contrat sans echelon ni profil n'a pas de salaire de base : son
     * bulletin ne porterait que des zeros.
     */
    public function test_un_contrat_sans_remuneration_ne_produit_pas_de_bulletin(): void
    {
        $this->contrat($this->agent(), ['echelon_id' => null, 'profil_salaire_id' => null]);

        $compte = app(\App\Services\PaieService::class)->genererMois($this->employeur, 9, 2026);

        $this->assertSame(0, Bulletin::count());
        $this->assertSame(0, $compte['crees']);
        $this->assertSame(1, $compte['sans_remuneration']);
    }

    /**
     * Les brouillons a zero d'avant ce controle s'effacent a la preparation
     * suivante : ils ne valent rien et encombrent l'ecran.
     */
    public function test_un_brouillon_a_zero_disparait_a_la_preparation(): void
    {
        $contrat = $this->contrat($this->agent());
        app(\App\Services\PaieService::class)->genererMois($this->employeur, 9, 2026);
        $this->assertSame(1, Bulletin::count());

        // Le contrat perd sa remuneration : son brouillon n'a plus lieu d'etre.
        $contrat->update(['echelon_id' => null, 'profil_salaire_id' => null]);

        $compte = app(\App\Services\PaieService::class)->genererMois($this->employeur, 9, 2026);

        $this->assertSame(0, Bulletin::count());
        $this->assertSame(1, $compte['sans_remuneration']);
    }

    public function test_un_bulletin_paye_a_zero_nest_jamais_efface(): void
    {
        $contrat = $this->contrat($this->agent());
        app(\App\Services\PaieService::class)->genererMois($this->employeur, 9, 2026);
        Bulletin::query()->update(['statut' => 'paye']);

        $contrat->update(['echelon_id' => null, 'profil_salaire_id' => null]);

        app(\App\Services\PaieService::class)->genererMois($this->employeur, 9, 2026);

        $this->assertSame(1, Bulletin::count());
    }

    /**
     * Un contrat sans remuneration est simplement passe : il ne declenche
     * aucune alerte, la RH le voit a l'ecran du personnel.
     */
    public function test_la_preparation_passe_les_contrats_sans_remuneration(): void
    {
        $this->contrat($this->agent('MBALLA'), ['echelon_id' => null, 'profil_salaire_id' => null]);
        $this->contrat($this->agent('NKOA'));

        $this->actingAs($this->gestionnaire())->post(route('personnel.paie.generer'), [
            'mois' => 9, 'annee' => 2026,
        ])->assertSessionHasNoErrors();

        // Seul celui qui a un echelon est prepare.
        $this->assertSame(1, Bulletin::count());
    }

    public function test_un_echelon_porte_par_le_profil_suffit(): void
    {
        $profil = \App\Models\ProfilSalaire::create([
            'nom' => 'Enseignant', 'echelon_id' => $this->echelon->id, 'actif' => true,
        ]);
        $this->contrat($this->agent(), ['echelon_id' => null, 'profil_salaire_id' => $profil->id]);

        app(\App\Services\PaieService::class)->genererMois($this->employeur, 9, 2026);

        $this->assertSame(1, Bulletin::count());
        $this->assertSame(200000.0, (float) Bulletin::firstOrFail()->salaire_base);
    }

    public function test_la_date_de_debut_du_contrat_nest_pas_obligatoire(): void
    {
        $agent = $this->agent();

        $this->actingAs($this->gestionnaire())->post(route('personnel.contrats.store', $agent->user), [
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi',
            'poste' => 'Enseignant',
            'quotite' => 100,
            'statut' => 'actif',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNull(Contrat::firstOrFail()->date_debut);
    }

    /** Le recrutement s'inscrit quand meme dans la carriere, date du jour. */
    public function test_un_contrat_sans_date_inscrit_le_recrutement_au_jour_de_la_saisie(): void
    {
        $agent = $this->agent();

        $this->actingAs($this->gestionnaire())->post(route('personnel.contrats.store', $agent->user), [
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi', 'poste' => 'Enseignant', 'quotite' => 100, 'statut' => 'actif',
        ]);

        $this->assertDatabaseHas('evenements_carriere', [
            'contrat_id' => Contrat::firstOrFail()->id,
            'type' => 'recrutement',
            'date_evenement' => now()->startOfDay(),
        ]);
    }

    /** Sans date de debut, le contrat est repute avoir toujours couru. */
    public function test_un_contrat_sans_date_de_debut_produit_son_bulletin(): void
    {
        $this->contrat($this->agent(), ['date_debut' => null]);

        app(\App\Services\PaieService::class)->genererMois($this->employeur, 9, 2026);

        $this->assertSame(1, Bulletin::count());
        $this->assertSame(200000.0, (float) Bulletin::firstOrFail()->salaire_base);
    }

    public function test_une_date_de_fin_seule_est_acceptee(): void
    {
        $agent = $this->agent();

        $this->actingAs($this->gestionnaire())->post(route('personnel.contrats.store', $agent->user), [
            'employeur_id' => $this->employeur->id,
            'type' => 'cdd', 'poste' => 'Enseignant', 'quotite' => 100, 'statut' => 'actif',
            'date_fin' => '2027-08-31',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Contrat::firstOrFail()->date_debut);
    }

    public function test_le_poste_du_contrat_nest_pas_obligatoire(): void
    {
        $membre = User::factory()->create(['lastname' => 'ESSOMBA', 'poste' => null]);
        $membre->applications()->attach($this->institut);

        $this->actingAs($this->gestionnaire())->post(route('personnel.contrats.store', $membre), [
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi',
            'date_debut' => '2026-09-01',
            'quotite' => 100,
            'statut' => 'actif',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNull(Contrat::firstOrFail()->poste);
    }

    public function test_un_poste_laisse_vide_reprend_celui_declare_pour_cet_institut(): void
    {
        $membre = User::factory()->create(['lastname' => 'ESSOMBA', 'poste' => 'Agent polyvalent']);
        $membre->applications()->attach($this->institut, ['poste' => 'Chargée de scolarité']);

        $this->actingAs($this->gestionnaire())->post(route('personnel.contrats.store', $membre), [
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi',
            'poste' => '   ',
            'date_debut' => '2026-09-01',
            'quotite' => 100,
            'statut' => 'actif',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Chargée de scolarité', Contrat::firstOrFail()->poste);
    }

    public function test_un_contrat_sans_poste_inscrit_quand_meme_le_recrutement(): void
    {
        $membre = User::factory()->create(['lastname' => 'ESSOMBA', 'poste' => null]);
        $membre->applications()->attach($this->institut);

        $this->actingAs($this->gestionnaire())->post(route('personnel.contrats.store', $membre), [
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi',
            'date_debut' => '2026-09-01',
            'quotite' => 100,
            'statut' => 'actif',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('evenements_carriere', [
            'contrat_id' => Contrat::firstOrFail()->id,
            'type' => 'recrutement',
            'libelle' => 'Poste à préciser — '.$this->employeur->sigle,
        ]);
    }

    public function test_le_poste_se_vide_a_la_modification_du_contrat(): void
    {
        $contrat = $this->contrat();

        $this->actingAs($this->gestionnaire())->put(route('personnel.contrats.update', $contrat), [
            'employeur_id' => $contrat->employeur_id,
            'type' => 'cdi',
            'poste' => '',
            'date_debut' => '2026-01-01',
            'quotite' => 100,
            'statut' => 'actif',
        ])->assertSessionHasNoErrors();

        // Le compte n'a declare aucun poste : le contrat reste sans poste.
        $this->assertNull($contrat->fresh()->poste);
    }

    public function test_la_creation_d_un_contrat_inscrit_le_recrutement_dans_la_carriere(): void
    {
        $agent = $this->agent();

        $this->actingAs($this->gestionnaire())->post(route('personnel.contrats.store', $agent->user), [
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
        $this->actingAs($this->gestionnaire())->post(route('personnel.contrats.store', $this->agent()->user), [
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

        $this->actingAs($gestionnaire)->post(route('personnel.evenements.store', $agent->user), [
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

    /**
     * C'est la meme personne qui arrete le montant et qui paie : l'etape de
     * validation separee n'est plus imposee.
     */
    public function test_un_brouillon_se_paie_sans_passer_par_la_validation(): void
    {
        $contrat = $this->contrat();
        app(\App\Services\PaieService::class)->genererMois($this->employeur, 9, 2026);
        $bulletin = Bulletin::firstOrFail();
        $this->assertSame('brouillon', $bulletin->statut);

        $gestionnaire = $this->gestionnaire();
        $this->actingAs($gestionnaire)
            ->post(route('personnel.paie.payer', $bulletin))
            ->assertSessionHasNoErrors();

        $bulletin->refresh();
        $this->assertSame('paye', $bulletin->statut);

        // La trace reste complete : les deux horodatages sont remplis.
        $this->assertNotNull($bulletin->valide_le);
        $this->assertNotNull($bulletin->paye_le);
        $this->assertSame($gestionnaire->id, $bulletin->valide_par);
        $this->assertSame($gestionnaire->id, $bulletin->paye_par);

        unset($contrat);
    }

    public function test_un_net_negatif_bloque_toujours_le_paiement_direct(): void
    {
        $contrat = $this->contrat();
        app(\App\Services\PaieService::class)->genererMois($this->employeur, 9, 2026);
        $bulletin = Bulletin::firstOrFail();
        $bulletin->update(['salaire_net' => -1000]);

        $this->actingAs($this->gestionnaire())
            ->post(route('personnel.paie.payer', $bulletin))
            ->assertSessionHasErrors('paie');

        $this->assertSame('brouillon', $bulletin->fresh()->statut);

        unset($contrat);
    }

    public function test_un_bulletin_deja_paye_ne_se_repaie_pas(): void
    {
        $this->contrat();
        app(\App\Services\PaieService::class)->genererMois($this->employeur, 9, 2026);
        $bulletin = Bulletin::firstOrFail();
        $bulletin->update(['statut' => 'paye']);

        $this->actingAs($this->gestionnaire())
            ->post(route('personnel.paie.payer', $bulletin))
            ->assertSessionHasErrors('paie');
    }

    /**
     * Un arrivant enregistre apres coup : on complete le mois sans defaire le
     * travail deja fait sur les bulletins en place.
     */
    public function test_preparer_le_reste_n_ajoute_que_les_manquants(): void
    {
        $premier = $this->contrat();
        app(\App\Services\PaieService::class)->genererMois($this->employeur, 9, 2026);

        // Le brouillon en place porte une correction saisie a la main.
        $dejaLa = Bulletin::firstOrFail();
        $dejaLa->update(['salaire_net' => 123456, 'note' => 'Corrigé à la main']);

        // Un second contrat arrive ensuite.
        $arrivant = $this->contrat($this->agent('ESSOMBA'));

        $this->actingAs($this->gestionnaire())->post(route('personnel.paie.generer'), [
            'mois' => 9, 'annee' => 2026, 'mode' => 'manquants',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Bulletin::count());

        // Le bulletin de l'arrivant est la...
        $this->assertDatabaseHas('bulletins', ['contrat_id' => $arrivant->id, 'mois' => 9, 'annee' => 2026]);

        // ...et celui qui existait n'a pas bouge.
        $dejaLa->refresh();
        $this->assertSame(123456.0, (float) $dejaLa->salaire_net);
        $this->assertSame('Corrigé à la main', $dejaLa->note);

        unset($premier);
    }

    public function test_la_preparation_complete_recalcule_les_brouillons(): void
    {
        $this->contrat();
        app(\App\Services\PaieService::class)->genererMois($this->employeur, 9, 2026);
        Bulletin::query()->update(['salaire_net' => 1]);

        $this->actingAs($this->gestionnaire())->post(route('personnel.paie.generer'), [
            'mois' => 9, 'annee' => 2026, 'mode' => 'complet',
        ])->assertSessionHasNoErrors();

        // Le mode complet remet le brouillon d'aplomb.
        $this->assertSame(200000.0, (float) Bulletin::firstOrFail()->salaire_net);
    }

    public function test_preparer_le_reste_ne_touche_pas_un_bulletin_paye(): void
    {
        $this->contrat();
        app(\App\Services\PaieService::class)->genererMois($this->employeur, 9, 2026);
        Bulletin::query()->update(['statut' => 'paye', 'salaire_net' => 999]);

        $this->actingAs($this->gestionnaire())->post(route('personnel.paie.generer'), [
            'mois' => 9, 'annee' => 2026, 'mode' => 'manquants',
        ]);

        $this->assertSame(1, Bulletin::count());
        $this->assertSame(999.0, (float) Bulletin::firstOrFail()->salaire_net);
    }

    public function test_un_mode_inconnu_est_refuse(): void
    {
        $this->actingAs($this->gestionnaire())->post(route('personnel.paie.generer'), [
            'mois' => 9, 'annee' => 2026, 'mode' => 'tout-effacer',
        ])->assertSessionHasErrors('mode');
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
        $this->actingAs(User::factory()->superadmin()->create())->post(route('personnel.employeurs.store'), [
            'nom' => 'Autre institut', 'sigle' => 'IUM',
        ])->assertSessionHasErrors('sigle');
    }

    public function test_un_employeur_avec_des_contrats_ne_se_supprime_pas(): void
    {
        $this->contrat();

        $this->actingAs(User::factory()->superadmin()->create())->delete(route('personnel.employeurs.destroy', $this->employeur))
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
