<?php

namespace Tests\Feature\Modules;

use App\Models\Application;
use App\Models\DemandeBadge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Demandes de badge : chacun demande le sien, le service qui les fabrique
 * traite. Le point sensible est le choix du logo quand la personne sert
 * plusieurs instituts.
 */
class BadgeModuleTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    private Application $ium;

    private Application $ifpm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Application::factory()->module('badges')->create(['name' => 'Badges']);
        $this->ium = Application::factory()->create(['name' => 'IUM', 'color' => '#047857']);
        $this->ifpm = Application::factory()->create(['name' => 'IFPM', 'color' => '#1e3a8a']);
    }

    private int $suivant = 146;

    /** Un employe, rattache aux instituts donnes. Matricule distinct a chaque fois. */
    private function employe(Application ...$instituts): User
    {
        $user = User::factory()->create([
            'name' => 'Claire',
            'lastname' => 'NKOA',
            'matricule' => 'LM-'.str_pad((string) ++$this->suivant, 4, '0', STR_PAD_LEFT),
        ]);

        foreach ($instituts as $institut) {
            $user->applications()->attach($institut, ['poste' => 'Enseignante']);
        }

        return $user;
    }

    /** Le service qui fabrique les badges. */
    private function guichet(): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module, ['role_in_app' => 'accueil', 'roles' => json_encode(['accueil'])]);

        return $user;
    }

    private function demande(User $demandeur, array $attributs = []): DemandeBadge
    {
        return DemandeBadge::create(array_merge([
            'numero' => DemandeBadge::prochainNumero(),
            'user_id' => $demandeur->id,
            'application_id' => $this->ium->id,
            'nom_affiche' => 'Claire NKOA',
            'modele' => 'classique',
            'motif' => 'premiere',
            'statut' => 'en_attente',
        ], $attributs));
    }

    // ------------------------------------------------------------- acces

    public function test_tout_le_personnel_peut_demander_sans_tuile_prealable(): void
    {
        // Pas de tuile « Badges » attribuee : la demande reste ouverte.
        $this->actingAs($this->employe($this->ium))->get(route('badges.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/badges/index')
                ->where('peutGerer', false)
                ->has('instituts', 1));
    }

    /** La tuile se pose seule : sinon personne ne trouverait le module. */
    public function test_la_tuile_apparait_sur_le_tableau_de_bord_de_tous(): void
    {
        $employe = $this->employe($this->ium);

        $this->actingAs($employe)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('apps', fn ($apps) => collect($apps)->contains('moduleKey', 'badges')));
    }

    public function test_un_module_ferme_ne_se_pose_pas_de_lui_meme(): void
    {
        $ferme = Application::factory()->module('informations')->create(['name' => "Centre d'information"]);

        $this->actingAs($this->employe($this->ium))->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('apps', fn ($apps) => ! collect($apps)->contains('id', $ferme->id)));
    }

    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        $this->get(route('badges.index'))->assertRedirect(route('login'));
    }

    public function test_le_module_desactive_repond_404(): void
    {
        $employe = $this->employe($this->ium);
        $this->module->update(['is_active' => false]);

        $this->actingAs($employe)->get(route('badges.index'))->assertNotFound();
    }

    public function test_le_traitement_est_reserve(): void
    {
        $employe = $this->employe($this->ium);

        $this->actingAs($employe)->get(route('badges.gestion'))->assertForbidden();
        $this->actingAs($employe)->get(route('badges.impression'))->assertForbidden();
        $this->actingAs($this->guichet())->get(route('badges.gestion'))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('badges.gestion'))->assertOk();
    }

    // ---------------------------------------------------------- la demande

    public function test_le_formulaire_reprend_l_identite_du_compte(): void
    {
        $employe = $this->employe($this->ium);

        $this->actingAs($employe)->get(route('badges.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('identite.nom', 'Claire NKOA')
                ->where('identite.matricule', $employe->matricule)
                // Plus de choix de modele : le badge du groupe est unique.
                ->missing('modeles'));
    }

    public function test_le_modele_du_groupe_s_applique_sans_qu_on_le_demande(): void
    {
        $this->actingAs($this->employe($this->ium))->post(route('badges.store'), [
            'nom_affiche' => 'Claire NKOA',
            'motif' => 'premiere',
            'application_id' => $this->ium->id,
        ])->assertRedirect();

        $this->assertSame('classique', DemandeBadge::firstOrFail()->modele);
    }

    public function test_une_demande_simple_est_enregistree(): void
    {
        $employe = $this->employe($this->ium);

        $this->actingAs($employe)->post(route('badges.store'), [
            'nom_affiche' => 'Dr Claire NKOA',
            'poste_affiche' => 'Enseignante-chercheuse',
            'motif' => 'premiere',
            'application_id' => $this->ium->id,
        ])->assertRedirect()->assertSessionHas('status');

        $demande = DemandeBadge::firstOrFail();
        $this->assertSame('Dr Claire NKOA', $demande->nom_affiche);
        $this->assertSame('en_attente', $demande->statut);
        $this->assertSame('BDG-000001', $demande->numero);
        $this->assertSame($this->ium->id, $demande->application_id);
    }

    public function test_les_numeros_se_suivent(): void
    {
        $this->demande($this->employe($this->ium));
        $this->demande($this->employe($this->ifpm));

        $this->assertSame(['BDG-000001', 'BDG-000002'], DemandeBadge::orderBy('id')->pluck('numero')->all());
        $this->assertSame('BDG-000003', DemandeBadge::prochainNumero());
    }

    /** Le point de la demande : deux instituts, donc un choix de logo. */
    public function test_deux_instituts_imposent_de_choisir_le_logo(): void
    {
        $employe = $this->employe($this->ium, $this->ifpm);

        $this->actingAs($employe)->get(route('badges.index'))
            ->assertInertia(fn (Assert $page) => $page->has('instituts', 2));

        // Sans choix, la demande est refusee.
        $this->actingAs($employe)->post(route('badges.store'), [
            'nom_affiche' => 'Claire NKOA',
            'modele' => 'classique',
            'motif' => 'premiere',
        ])->assertSessionHasErrors('application_id');

        $this->assertSame(0, DemandeBadge::count());

        // Avec le choix, elle passe, et c'est ce logo qui est retenu.
        $this->actingAs($employe)->post(route('badges.store'), [
            'nom_affiche' => 'Claire NKOA',
            'modele' => 'classique',
            'motif' => 'premiere',
            'application_id' => $this->ifpm->id,
        ])->assertRedirect();

        $this->assertSame($this->ifpm->id, DemandeBadge::firstOrFail()->application_id);
    }

    public function test_on_ne_choisit_pas_un_institut_auquel_on_n_est_pas_rattache(): void
    {
        $this->actingAs($this->employe($this->ium))->post(route('badges.store'), [
            'nom_affiche' => 'Claire NKOA',
            'modele' => 'classique',
            'motif' => 'premiere',
            'application_id' => $this->ifpm->id,
        ])->assertSessionHasErrors('application_id');
    }

    public function test_sans_institut_la_demande_passe_sans_logo(): void
    {
        $this->actingAs($this->employe())->post(route('badges.store'), [
            'nom_affiche' => 'Claire NKOA',
            'motif' => 'premiere',
        ])->assertRedirect();

        $this->assertNull(DemandeBadge::firstOrFail()->application_id);
    }

    public function test_un_modele_inconnu_est_refuse(): void
    {
        $this->actingAs($this->employe($this->ium))->post(route('badges.store'), [
            'nom_affiche' => 'Claire NKOA',
            'modele' => 'paillettes',
            'motif' => 'premiere',
            'application_id' => $this->ium->id,
        ])->assertSessionHasErrors('modele');
    }

    public function test_le_nom_affiche_est_obligatoire(): void
    {
        $this->actingAs($this->employe($this->ium))->post(route('badges.store'), [
            'modele' => 'classique',
            'motif' => 'premiere',
            'application_id' => $this->ium->id,
        ])->assertSessionHasErrors('nom_affiche');
    }

    public function test_on_n_ouvre_pas_deux_demandes_a_la_fois(): void
    {
        $employe = $this->employe($this->ium);
        $this->demande($employe);

        $this->actingAs($employe)->post(route('badges.store'), [
            'nom_affiche' => 'Claire NKOA',
            'modele' => 'classique',
            'motif' => 'perte',
            'application_id' => $this->ium->id,
        ])->assertSessionHasErrors('badge');

        $this->assertSame(1, DemandeBadge::count());
    }

    public function test_une_demande_remise_laisse_la_place_a_la_suivante(): void
    {
        $employe = $this->employe($this->ium);
        $this->demande($employe, ['statut' => 'remise']);

        $this->actingAs($employe)->post(route('badges.store'), [
            'nom_affiche' => 'Claire NKOA',
            'modele' => 'classique',
            'motif' => 'perte',
            'application_id' => $this->ium->id,
        ])->assertRedirect();

        $this->assertSame(2, DemandeBadge::count());
    }

    public function test_la_photo_jointe_remplace_celle_du_compte(): void
    {
        Storage::fake('public');

        $this->actingAs($this->employe($this->ium))->post(route('badges.store'), [
            'nom_affiche' => 'Claire NKOA',
            'modele' => 'classique',
            'motif' => 'premiere',
            'application_id' => $this->ium->id,
            'photo_file' => UploadedFile::fake()->image('portrait.jpg', 400, 500),
        ])->assertRedirect();

        $demande = DemandeBadge::firstOrFail();
        $this->assertNotNull($demande->photo);
        Storage::disk('public')->assertExists($demande->photo);
    }

    public function test_le_demandeur_annule_tant_que_rien_n_est_fait(): void
    {
        $employe = $this->employe($this->ium);
        $demande = $this->demande($employe);

        $this->actingAs($employe)->delete(route('badges.destroy', $demande))->assertRedirect();
        $this->assertSame(0, DemandeBadge::count());
    }

    public function test_on_n_annule_pas_une_demande_deja_approuvee(): void
    {
        $employe = $this->employe($this->ium);
        $demande = $this->demande($employe, ['statut' => 'approuvee']);

        $this->actingAs($employe)->delete(route('badges.destroy', $demande))
            ->assertSessionHasErrors('badge');

        $this->assertSame(1, DemandeBadge::count());
    }

    public function test_on_n_annule_pas_la_demande_d_un_autre(): void
    {
        $demande = $this->demande($this->employe($this->ium));

        $this->actingAs($this->employe($this->ifpm))->delete(route('badges.destroy', $demande))
            ->assertForbidden();
    }

    // -------------------------------------------------------- traitement

    public function test_le_circuit_va_de_l_attente_a_la_remise(): void
    {
        $demande = $this->demande($this->employe($this->ium));
        $guichet = $this->guichet();

        foreach (['approuvee', 'imprimee', 'remise'] as $etape) {
            $this->actingAs($guichet)->post(route('badges.traiter', $demande), ['statut' => $etape])
                ->assertRedirect();
            $this->assertSame($etape, $demande->refresh()->statut);
        }

        $this->assertSame($guichet->id, $demande->traite_par);
        $this->assertNotNull($demande->traite_le);
    }

    public function test_un_refus_exige_son_motif(): void
    {
        $demande = $this->demande($this->employe($this->ium));
        $guichet = $this->guichet();

        $this->actingAs($guichet)->post(route('badges.traiter', $demande), ['statut' => 'refusee'])
            ->assertSessionHasErrors('motif_refus');

        $this->assertSame('en_attente', $demande->refresh()->statut);

        $this->actingAs($guichet)->post(route('badges.traiter', $demande), [
            'statut' => 'refusee',
            'motif_refus' => 'Photo inexploitable.',
        ])->assertRedirect();

        $this->assertSame('refusee', $demande->refresh()->statut);
        $this->assertSame('Photo inexploitable.', $demande->motif_refus);
    }

    public function test_un_employe_ne_traite_pas_sa_propre_demande(): void
    {
        $employe = $this->employe($this->ium);
        $demande = $this->demande($employe);

        $this->actingAs($employe)->post(route('badges.traiter', $demande), ['statut' => 'approuvee'])
            ->assertForbidden();

        $this->assertSame('en_attente', $demande->refresh()->statut);
    }

    public function test_la_gestion_compte_et_filtre(): void
    {
        $this->demande($this->employe($this->ium));
        $this->demande($this->employe($this->ifpm), ['application_id' => $this->ifpm->id, 'statut' => 'approuvee']);

        $guichet = $this->guichet();

        $this->actingAs($guichet)->get(route('badges.gestion'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/badges/gestion')
                ->has('demandes.data', 2)
                ->where('compteurs.en_attente', 1)
                ->where('compteurs.approuvee', 1));

        $this->actingAs($guichet)->get(route('badges.gestion', ['statut' => 'approuvee']))
            ->assertInertia(fn (Assert $page) => $page->has('demandes.data', 1));

        $this->actingAs($guichet)->get(route('badges.gestion', ['institut' => $this->ifpm->id]))
            ->assertInertia(fn (Assert $page) => $page->has('demandes.data', 1));
    }

    public function test_la_planche_ne_tire_que_les_badges_approuves(): void
    {
        $this->demande($this->employe($this->ium));
        $this->demande($this->employe($this->ifpm), ['statut' => 'approuvee']);
        $this->demande($this->employe($this->ium), ['statut' => 'remise']);

        $this->actingAs($this->guichet())->get(route('badges.impression'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/badges/impression')
                ->has('demandes', 1)
                ->where('demandes.0.statut', 'approuvee'));
    }

    public function test_la_planche_accepte_une_selection(): void
    {
        $a = $this->demande($this->employe($this->ium), ['statut' => 'imprimee']);
        $this->demande($this->employe($this->ifpm), ['statut' => 'approuvee']);

        $this->actingAs($this->guichet())->get(route('badges.impression', ['demandes' => $a->id]))
            ->assertInertia(fn (Assert $page) => $page->has('demandes', 1)->where('demandes.0.id', $a->id));
    }

    public function test_le_badge_porte_le_logo_de_l_institut_retenu(): void
    {
        $demande = $this->demande($this->employe($this->ium, $this->ifpm), ['application_id' => $this->ifpm->id]);

        $this->actingAs($this->guichet())->get(route('badges.gestion'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('demandes.data.0.institut.name', 'IFPM')
                ->where('demandes.data.0.institut.color', '#1e3a8a'));

        $this->assertSame($this->ifpm->id, $demande->institut->id);
    }
}
