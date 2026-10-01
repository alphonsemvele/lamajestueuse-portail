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
use App\Models\User;
use App\Services\PaieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Mes bulletins de paie, de bout en bout : la paie est preparee puis validee
 * dans le module RH, et c'est alors seulement que le salarie la voit.
 */
class MesBulletinsTest extends TestCase
{
    use RefreshDatabase;

    private Application $moduleBulletins;

    private Application $modulePersonnel;

    private Application $ium;

    private Application $ifpm;

    private Employeur $employeurIum;

    private Echelon $echelon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleBulletins = Application::factory()->module('bulletins')->create(['name' => 'Mon bulletin de paie']);
        $this->modulePersonnel = Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);

        $this->ium = Application::factory()->create(['name' => 'IUM', 'color' => '#047857']);
        $this->ifpm = Application::factory()->create(['name' => 'IFPM', 'color' => '#1e3a8a']);

        $this->employeurIum = Employeur::create([
            'nom' => 'Institut Universitaire La Majestueuse', 'sigle' => 'IUM',
            'application_id' => $this->ium->id, 'niu' => 'M021700000001', 'numero_cnps' => 'CNPS-4471',
            'signataire' => 'Le Directeur Général',
        ]);

        $categorie = CategorieRh::create(['libelle' => 'Enseignants']);
        $this->echelon = Echelon::create(['categorie_rh_id' => $categorie->id, 'numero' => 1, 'salaire' => 200000]);
    }

    /** Le service RH qui prépare et valide la paie. */
    private function responsableRh(): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->modulePersonnel, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $user->employeursRh()->attach($this->employeurIum);

        return $user;
    }

    /** Un salarié, rattaché aux instituts donnés, avec son contrat à l'IUM. */
    private function salarie(?array $instituts = null, bool $avecContrat = true): User
    {
        $user = User::factory()->create(['name' => 'Claire', 'lastname' => 'NKOA', 'matricule' => 'LM-260147']);

        foreach ($instituts ?? [$this->ium] as $institut) {
            $user->applications()->attach($institut);
        }

        if ($avecContrat) {
            $agent = Agent::create(['user_id' => $user->id, 'numero_cnps' => 'CN-778120']);
            Contrat::create([
                'agent_id' => $agent->id,
                'employeur_id' => $this->employeurIum->id,
                'type' => 'cdi',
                'poste' => 'Enseignante',
                'date_debut' => '2025-01-01',
                'quotite' => 100,
                'echelon_id' => $this->echelon->id,
                'statut' => 'actif',
            ]);
        }

        return $user;
    }

    // ----------------------------------- le parcours, du module RH au salarié

    /** Le cœur du module : rien n'est visible avant la validation. */
    public function test_un_brouillon_ne_se_voit_pas_puis_la_validation_le_revele(): void
    {
        $salarie = $this->salarie();
        $rh = $this->responsableRh();

        // La RH prépare la paie du mois : le bulletin est au brouillon.
        $this->actingAs($rh)->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026]);
        $bulletin = Bulletin::firstOrFail();
        $this->assertSame('brouillon', $bulletin->statut);

        $this->actingAs($salarie)->get(route('mes-bulletins.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('bulletins.data', 0));

        $this->actingAs($salarie)->get(route('mes-bulletins.pdf', $bulletin))->assertNotFound();

        // La RH valide : le salarié le voit, et peut le télécharger.
        $this->actingAs($rh)->post(route('personnel.paie.valider', $bulletin))->assertRedirect();

        $this->actingAs($salarie)->get(route('mes-bulletins.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('bulletins.data', 1)
                ->where('bulletins.data.0.periode', 'septembre 2026')
                ->where('bulletins.data.0.salaireNet', fn ($net) => (float) $net === 200000.0));

        $this->actingAs($salarie)->get(route('mes-bulletins.pdf', $bulletin))->assertOk();
    }

    public function test_un_bulletin_paye_reste_visible(): void
    {
        $salarie = $this->salarie();
        $rh = $this->responsableRh();

        $this->actingAs($rh)->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026]);
        $bulletin = Bulletin::firstOrFail();
        $this->actingAs($rh)->post(route('personnel.paie.valider', $bulletin));
        $this->actingAs($rh)->post(route('personnel.paie.payer', $bulletin));

        $this->actingAs($salarie)->get(route('mes-bulletins.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('bulletins.data', 1)
                ->where('bulletins.data.0.statut', 'paye'));
    }

    /** Le décompte du bulletin suit celui du module RH, ligne à ligne. */
    public function test_le_detail_est_celui_calcule_par_la_paie(): void
    {
        $profil = ProfilSalaire::create(['nom' => 'Enseignant 1', 'echelon_id' => $this->echelon->id]);
        $profil->indemnites()->attach(
            Indemnite::create(['libelle' => 'Logement'])->id,
            ['type_calcul' => 'pourcentage', 'valeur' => 10],
        );

        $salarie = $this->salarie();
        $salarie->agent->contrats()->first()->update(['profil_salaire_id' => $profil->id]);

        $rh = $this->responsableRh();
        $this->actingAs($rh)->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026]);
        $this->actingAs($rh)->post(route('personnel.paie.valider', Bulletin::firstOrFail()));

        $this->actingAs($salarie)->get(route('mes-bulletins.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('bulletins.data.0.detail.indemnites.0.libelle', 'Logement')
                ->where('bulletins.data.0.salaireNet', fn ($net) => (float) $net === 220000.0));
    }

    // ------------------------------------------------------------- l'en-tête

    public function test_un_seul_institut_donne_ses_couleurs_au_bulletin(): void
    {
        $this->actingAs($this->salarie([$this->ium]))->get(route('mes-bulletins.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('enTete.nom', 'IUM')
                ->where('enTete.couleur', '#047857')
                ->where('enTete.groupe', false));
    }

    /** Servir plusieurs instituts n'en privilégie aucun : c'est le groupe. */
    public function test_plusieurs_instituts_donnent_la_majestueuse(): void
    {
        $this->actingAs($this->salarie([$this->ium, $this->ifpm]))->get(route('mes-bulletins.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('enTete.nom', 'LA MAJESTUEUSE')
                ->where('enTete.groupe', true));
    }

    public function test_sans_institut_c_est_aussi_le_groupe(): void
    {
        $this->actingAs($this->salarie([]))->get(route('mes-bulletins.index'))
            ->assertInertia(fn (Assert $page) => $page->where('enTete.nom', 'LA MAJESTUEUSE'));
    }

    // ------------------------------------------------------------ le fichier

    public function test_le_pdf_porte_le_bulletin(): void
    {
        $salarie = $this->salarie();
        $rh = $this->responsableRh();
        $this->actingAs($rh)->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026]);
        $bulletin = Bulletin::firstOrFail();
        $this->actingAs($rh)->post(route('personnel.paie.valider', $bulletin));

        $reponse = $this->actingAs($salarie)->get(route('mes-bulletins.pdf', $bulletin));

        $reponse->assertOk();
        $reponse->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'bulletin-LM-260147-2026-09.pdf',
            $reponse->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    // --------------------------------------------------------------- l'accès

    public function test_on_ne_telecharge_pas_le_bulletin_d_un_autre(): void
    {
        $salarie = $this->salarie();
        $rh = $this->responsableRh();
        $this->actingAs($rh)->post(route('personnel.paie.generer'), ['mois' => 9, 'annee' => 2026]);
        $bulletin = Bulletin::firstOrFail();
        $this->actingAs($rh)->post(route('personnel.paie.valider', $bulletin));

        $curieux = User::factory()->create();

        $this->actingAs($curieux)->get(route('mes-bulletins.pdf', $bulletin))->assertForbidden();
        $this->actingAs($curieux)->get(route('mes-bulletins.index'))
            ->assertInertia(fn (Assert $page) => $page->has('bulletins.data', 0));

        $this->assertNotNull($salarie->id);
    }

    public function test_le_module_est_ouvert_sans_tuile_attribuee(): void
    {
        $this->actingAs($this->salarie(null, false))->get(route('mes-bulletins.index'))->assertOk();
    }

    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        $this->get(route('mes-bulletins.index'))->assertRedirect(route('login'));
    }

    public function test_le_module_desactive_repond_404(): void
    {
        $salarie = $this->salarie();
        $this->moduleBulletins->update(['is_active' => false]);

        $this->actingAs($salarie)->get(route('mes-bulletins.index'))->assertNotFound();
    }

    // ------------------------------------------------------------ recherche

    public function test_la_recherche_et_le_filtre_par_annee(): void
    {
        $salarie = $this->salarie();
        $rh = $this->responsableRh();

        foreach ([[9, 2026], [8, 2025]] as [$mois, $annee]) {
            $this->actingAs($rh)->post(route('personnel.paie.generer'), ['mois' => $mois, 'annee' => $annee]);
        }

        foreach (Bulletin::all() as $bulletin) {
            $this->actingAs($rh)->post(route('personnel.paie.valider', $bulletin));
        }

        $this->actingAs($salarie)->get(route('mes-bulletins.index'))
            ->assertInertia(fn (Assert $page) => $page->has('bulletins.data', 2)->has('annees', 2));

        $this->actingAs($salarie)->get(route('mes-bulletins.index', ['annee' => 2025]))
            ->assertInertia(fn (Assert $page) => $page->has('bulletins.data', 1));

        $this->actingAs($salarie)->get(route('mes-bulletins.index', ['q' => 'Enseignante']))
            ->assertInertia(fn (Assert $page) => $page->has('bulletins.data', 2));

        $this->actingAs($salarie)->get(route('mes-bulletins.index', ['q' => 'Chauffeur']))
            ->assertInertia(fn (Assert $page) => $page->has('bulletins.data', 0));
    }
}
