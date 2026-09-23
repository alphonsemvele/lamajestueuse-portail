<?php

namespace Tests\Feature\Modules;

use App\Models\Agent;
use App\Models\Ajustement;
use App\Models\Application;
use App\Models\Bulletin;
use App\Models\CategorieRh;
use App\Models\Contrat;
use App\Models\Echelon;
use App\Models\Employeur;
use App\Models\User;
use App\Services\PaieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Perimetre RH : l'administrateur du portail donne l'acces au module puis
 * designe les entites suivies. Un gestionnaire ne voit alors que le personnel,
 * les contrats et les bulletins de ces entites — ni plus, ni moins.
 */
class PerimetreRhTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    private Application $appIum;

    private Application $appIfpm;

    private Employeur $ium;

    private Employeur $ifpm;

    private Echelon $echelon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);

        // Chaque employeur correspond a un institut du portail : c'est par lui
        // que le personnel est rattache avant meme d'avoir un contrat.
        $this->appIum = Application::factory()->create(['name' => 'IUM']);
        $this->appIfpm = Application::factory()->create(['name' => 'IFPM']);

        $this->ium = Employeur::create([
            'nom' => 'Institut Universitaire', 'sigle' => 'IUM', 'application_id' => $this->appIum->id,
        ]);
        $this->ifpm = Employeur::create([
            'nom' => 'Institut de Formation', 'sigle' => 'IFPM', 'application_id' => $this->appIfpm->id,
        ]);

        $categorie = CategorieRh::create(['libelle' => 'Enseignants']);
        $this->echelon = Echelon::create(['categorie_rh_id' => $categorie->id, 'numero' => 1, 'salaire' => 200000]);
    }

    /** Gestionnaire RH limite aux entites indiquees. */
    private function gestionnaire(Employeur ...$entites): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $user->employeursRh()->attach(collect($entites)->pluck('id')->all());

        return $user;
    }

    /** Un membre du personnel rattache a un institut, avec son dossier. */
    private function agent(string $nom, ?Application $institut = null): Agent
    {
        $membre = User::factory()->create(['lastname' => $nom]);
        $membre->applications()->attach($institut ?? $this->appIum);

        return Agent::create(['user_id' => $membre->id]);
    }

    private function contrat(Employeur $employeur, ?Agent $agent = null, array $attributs = []): Contrat
    {
        // Sans agent designe, on en cree un rattache a l'institut concerne.
        $defaut = fn () => $this->agent('MBALLA', $employeur->id === $this->ifpm->id ? $this->appIfpm : $this->appIum);

        return Contrat::create(array_merge([
            'agent_id' => ($agent ?? $defaut())->id,
            'employeur_id' => $employeur->id,
            'type' => 'cdi',
            'poste' => 'Enseignant',
            'date_debut' => '2026-01-01',
            'quotite' => 100,
            'echelon_id' => $this->echelon->id,
            'statut' => 'actif',
        ], $attributs));
    }

    // ------------------------------------------------- configuration par l'admin

    public function test_l_administrateur_attribue_les_entites_depuis_l_ecran_d_acces(): void
    {
        $admin = User::factory()->admin()->create();
        $gestionnaire = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.applications.access', $this->module))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('employeurs', 2));

        $this->actingAs($admin)->put(route('admin.applications.access.update', $this->module), [
            'users' => [$gestionnaire->id],
            'roles' => [$gestionnaire->id => ['drh']],
            'perimetres' => [$gestionnaire->id => [$this->ium->id]],
        ])->assertRedirect();

        $this->assertSame([$this->ium->id], $gestionnaire->refresh()->perimetreRh());
    }

    public function test_retirer_l_acces_retire_aussi_les_entites(): void
    {
        $admin = User::factory()->admin()->create();
        $gestionnaire = $this->gestionnaire($this->ium, $this->ifpm);

        $this->actingAs($admin)->put(route('admin.applications.access.update', $this->module), [
            'users' => [],
            'roles' => [],
        ])->assertRedirect();

        $this->assertSame([], $gestionnaire->refresh()->perimetreRh());
    }

    public function test_l_ecran_d_acces_d_une_application_ordinaire_ne_parle_pas_d_entites(): void
    {
        $application = Application::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.applications.access', $application))
            ->assertInertia(fn (Assert $page) => $page->has('employeurs', 0));
    }

    public function test_l_administrateur_du_portail_n_a_aucune_limite(): void
    {
        $this->assertNull(User::factory()->admin()->create()->perimetreRh());
    }

    // ---------------------------------------------------------- ce qui est vu

    public function test_le_gestionnaire_ne_voit_que_le_personnel_de_ses_entites(): void
    {
        $this->contrat($this->ium, $this->agent('NKOA'));
        $this->contrat($this->ifpm, $this->agent('ATANGANA', $this->appIfpm));

        $this->actingAs($this->gestionnaire($this->ium))->get(route('personnel.agents'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('agents.data', 1)
                ->where('agents.data.0.nom', fn ($nom) => str_contains((string) $nom, 'NKOA'))
                // Le filtre employeur ne propose que ses entites.
                ->has('employeurs', 1));
    }

    /**
     * Sans contrat encore saisi, c'est le rattachement du portail qui dit de
     * qui releve la personne : autrement, un nouvel arrivant serait invisible
     * de celui-la meme qui doit lui faire son contrat.
     */
    public function test_le_rattachement_a_l_institut_suffit_a_faire_apparaitre_une_personne(): void
    {
        $this->agent('NOUVEAU', $this->appIum);

        $this->actingAs($this->gestionnaire($this->ium))->get(route('personnel.agents'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('agents.data', 1)
                ->where('agents.data.0.dossierOuvert', true));

        $this->actingAs($this->gestionnaire($this->ifpm))->get(route('personnel.agents'))
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 0));
    }

    public function test_une_personne_rattachee_a_aucun_institut_ne_remonte_qu_a_l_administrateur(): void
    {
        $isole = User::factory()->create(['lastname' => 'ISOLÉ']);

        $this->actingAs($this->gestionnaire($this->ium))->get(route('personnel.agents'))
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 0));

        $reponse = $this->actingAs(User::factory()->admin()->create())->get(route('personnel.agents'));
        $noms = collect($reponse->viewData('page')['props']['agents']['data'])->pluck('nom');

        $this->assertTrue($noms->contains(fn ($nom) => str_contains((string) $nom, 'ISOLÉ')));
        $this->assertSame('ISOLÉ', $isole->lastname);
    }

    public function test_le_gestionnaire_de_deux_entites_voit_les_deux(): void
    {
        $this->contrat($this->ium, $this->agent('NKOA'));
        $this->contrat($this->ifpm, $this->agent('ATANGANA', $this->appIfpm));

        $this->actingAs($this->gestionnaire($this->ium, $this->ifpm))->get(route('personnel.agents'))
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 2)->has('employeurs', 2));
    }

    public function test_sans_entite_un_gestionnaire_ne_voit_aucun_dossier(): void
    {
        $this->contrat($this->ium, $this->agent('NKOA'));

        $this->actingAs($this->gestionnaire())->get(route('personnel.agents'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('agents.data', 0)->has('employeurs', 0));
    }

    public function test_la_fiche_d_un_agent_hors_perimetre_est_refusee(): void
    {
        $agent = $this->agent('ATANGANA', $this->appIfpm);
        $this->contrat($this->ifpm, $agent);

        $this->actingAs($this->gestionnaire($this->ium))->get(route('personnel.agents.show', $agent->user))
            ->assertForbidden();
    }

    /** Le cas qui justifie tout : un agent partagé entre deux instituts. */
    public function test_un_agent_partage_ne_montre_a_chacun_que_son_contrat(): void
    {
        $agent = $this->agent('NKOA');
        $this->contrat($this->ium, $agent, ['poste' => 'Enseignant IUM']);
        $this->contrat($this->ifpm, $agent, ['poste' => 'Formateur IFPM']);

        $this->actingAs($this->gestionnaire($this->ium))->get(route('personnel.agents.show', $agent->user))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('contrats', 1)
                ->where('contrats.0.poste', 'Enseignant IUM')
                ->has('referentiels.employeurs', 1));

        $this->actingAs(User::factory()->admin()->create())->get(route('personnel.agents.show', $agent->user))
            ->assertInertia(fn (Assert $page) => $page->has('contrats', 2));
    }

    public function test_les_bulletins_d_un_agent_partage_suivent_le_meme_partage(): void
    {
        $agent = $this->agent('NKOA');
        $paie = app(PaieService::class);

        foreach ([$this->ium, $this->ifpm] as $employeur) {
            $this->contrat($employeur, $agent);
            $paie->genererMois($employeur, 9, 2026);
        }

        $this->actingAs($this->gestionnaire($this->ium))->get(route('personnel.agents.show', $agent->user))
            ->assertInertia(fn (Assert $page) => $page->has('bulletins', 1)->where('bulletins.0.employeur', 'IUM'));
    }

    public function test_la_carriere_liee_a_un_autre_institut_reste_cachee(): void
    {
        $agent = $this->agent('NKOA');
        $contratIfpm = $this->contrat($this->ifpm, $agent);
        $this->contrat($this->ium, $agent);

        $agent->evenements()->create([
            'contrat_id' => $contratIfpm->id,
            'date_evenement' => '2026-03-01', 'type' => 'avancement', 'libelle' => 'Avancement IFPM',
        ]);
        $agent->evenements()->create([
            'date_evenement' => '2026-04-01', 'type' => 'formation', 'libelle' => 'Formation du groupe',
        ]);

        $this->actingAs($this->gestionnaire($this->ium))->get(route('personnel.agents.show', $agent->user))
            ->assertInertia(fn (Assert $page) => $page
                // L'evenement sans contrat concerne la personne, pas l'institut.
                ->has('evenements', 1)
                ->where('evenements.0.libelle', 'Formation du groupe'));
    }

    public function test_le_tableau_de_bord_ne_compte_que_les_entites_suivies(): void
    {
        $this->contrat($this->ium, $this->agent('NKOA'));
        $this->contrat($this->ifpm, $this->agent('ATANGANA', $this->appIfpm));
        app(PaieService::class)->genererMois($this->ifpm, 9, 2026);

        $this->actingAs($this->gestionnaire($this->ium))->get(route('personnel.index', ['mois' => 9, 'annee' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('employeurs', 1)
                ->where('chiffres.agents', 1)
                ->where('chiffres.contratsActifs', 1)
                // La paie de l'IFPM ne pese pas dans sa masse salariale.
                ->where('chiffres.masse.net', fn ($net) => (float) $net === 0.0)
                ->where('perimetreLimite', true));
    }

    public function test_la_paie_du_mois_est_limitee_aux_entites_suivies(): void
    {
        $paie = app(PaieService::class);
        $this->contrat($this->ium, $this->agent('NKOA'));
        $this->contrat($this->ifpm, $this->agent('ATANGANA', $this->appIfpm));
        $paie->genererMois($this->ium, 9, 2026);
        $paie->genererMois($this->ifpm, 9, 2026);

        $this->actingAs($this->gestionnaire($this->ium))->get(route('personnel.paie.index', ['mois' => 9, 'annee' => 2026]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('bulletins', 1)
                ->where('repartition.brouillon', 1)
                ->where('masse.net', fn ($net) => (float) $net === 200000.0));

        $this->actingAs($this->gestionnaire($this->ium))->get(route('personnel.bulletins'))
            ->assertInertia(fn (Assert $page) => $page->has('bulletins.data', 1));
    }

    public function test_un_bulletin_d_une_autre_entite_est_inaccessible(): void
    {
        $this->contrat($this->ifpm);
        app(PaieService::class)->genererMois($this->ifpm, 9, 2026);
        $bulletin = Bulletin::firstOrFail();

        $gestionnaire = $this->gestionnaire($this->ium);

        $this->actingAs($gestionnaire)->get(route('personnel.paie.bulletin', $bulletin))->assertForbidden();
        $this->actingAs($gestionnaire)->post(route('personnel.paie.valider', $bulletin))->assertForbidden();
        $this->actingAs($gestionnaire)->put(route('personnel.paie.note', $bulletin), ['note' => 'x'])->assertForbidden();
    }

    public function test_la_preparation_sans_entite_choisie_ne_touche_que_le_perimetre(): void
    {
        $this->contrat($this->ium);
        $this->contrat($this->ifpm);

        $this->actingAs($this->gestionnaire($this->ium))
            ->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026])
            ->assertRedirect();

        $this->assertSame(1, Bulletin::count());
        $this->assertSame($this->ium->id, Bulletin::first()->employeur_id);
    }

    public function test_preparer_la_paie_d_une_entite_non_suivie_est_refuse(): void
    {
        $this->contrat($this->ifpm);

        $this->actingAs($this->gestionnaire($this->ium))->post(route('personnel.paie.generer'), [
            'mois' => 9, 'annee' => 2026, 'employeur_id' => $this->ifpm->id,
        ])->assertForbidden();

        $this->assertSame(0, Bulletin::count());
    }

    public function test_le_traitement_en_lot_ignore_les_bulletins_hors_perimetre(): void
    {
        $paie = app(PaieService::class);
        $this->contrat($this->ium);
        $this->contrat($this->ifpm);
        $paie->genererMois($this->ium, 9, 2026);
        $paie->genererMois($this->ifpm, 9, 2026);

        $this->actingAs($this->gestionnaire($this->ium))->post(route('personnel.paie.lot'), [
            'action' => 'valider',
            'bulletins' => Bulletin::pluck('id')->all(),
        ])->assertRedirect();

        $this->assertSame('valide', Bulletin::where('employeur_id', $this->ium->id)->first()->statut);
        $this->assertSame('brouillon', Bulletin::where('employeur_id', $this->ifpm->id)->first()->statut);
    }

    // --------------------------------------------------------- ce qui est écrit

    public function test_un_contrat_ne_se_cree_pas_pour_une_entite_non_suivie(): void
    {
        $agent = $this->agent('NKOA');

        $this->actingAs($this->gestionnaire($this->ium))->post(route('personnel.contrats.store', $agent->user), [
            'employeur_id' => $this->ifpm->id,
            'type' => 'cdi',
            'poste' => 'Formateur',
            'date_debut' => '2026-09-01',
            'quotite' => 100,
            'statut' => 'actif',
        ])->assertForbidden();

        $this->assertSame(0, Contrat::count());
    }

    public function test_un_contrat_d_une_autre_entite_ne_se_modifie_pas(): void
    {
        $contrat = $this->contrat($this->ifpm);

        $this->actingAs($this->gestionnaire($this->ium))->delete(route('personnel.contrats.destroy', $contrat))
            ->assertForbidden();

        $this->assertDatabaseHas('contrats', ['id' => $contrat->id]);
    }

    public function test_un_ajustement_sur_une_autre_entite_est_refuse(): void
    {
        $contrat = $this->contrat($this->ifpm);

        $this->actingAs($this->gestionnaire($this->ium))->post(route('personnel.ajustements.store', $contrat), [
            'mois' => 9, 'annee' => 2026, 'type' => 'bonus', 'mode' => 'fixe',
            'libelle' => 'Prime', 'montant' => 10000,
        ])->assertForbidden();

        $this->assertSame(0, Ajustement::count());
    }

    public function test_le_dossier_d_un_agent_hors_perimetre_ne_se_modifie_pas(): void
    {
        $agent = $this->agent('ATANGANA', $this->appIfpm);
        $this->contrat($this->ifpm, $agent);

        $gestionnaire = $this->gestionnaire($this->ium);

        $this->actingAs($gestionnaire)->put(route('personnel.agents.update', $agent->user), ['enfants' => 4])
            ->assertForbidden();

        $this->actingAs($gestionnaire)->post(route('personnel.diplomes.store', $agent->user), ['intitule' => 'Master'])
            ->assertForbidden();

        $this->actingAs($gestionnaire)->post(route('personnel.evenements.store', $agent->user), [
            'date_evenement' => '2026-09-01', 'type' => 'sanction', 'libelle' => 'Avertissement',
        ])->assertForbidden();
    }

    public function test_les_referentiels_communs_restent_partages(): void
    {
        $gestionnaire = $this->gestionnaire($this->ium);

        // La grille, les indemnites, les retenues et les profils valent pour
        // tout le groupe : ils ne se decoupent pas par entite.
        foreach (['personnel.categories', 'personnel.profils', 'personnel.indemnites', 'personnel.retenues'] as $route) {
            $this->actingAs($gestionnaire)->get(route($route))->assertOk();
        }

        // Seule la liste des entites suit le perimetre.
        $this->actingAs($gestionnaire)->get(route('personnel.employeurs'))
            ->assertInertia(fn (Assert $page) => $page->has('employeurs', 1)->where('estAdministrateur', false));
    }
}
