<?php

namespace Tests\Feature\Modules;

use App\Models\Agent;
use App\Models\Application;
use App\Models\Bulletin;
use App\Models\CategorieRh;
use App\Models\Contrat;
use App\Models\Echelon;
use App\Models\Employeur;
use App\Models\User;
use App\Services\PaieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le bulletin en PDF : previsualise ou enregistre, par la RH comme par le
 * salarie, et toujours le meme document.
 */
class ApercuBulletinPdfTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    private Employeur $employeur;

    private Employeur $autre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        Application::factory()->module('bulletins')->create(['name' => 'Mon bulletin de paie']);

        $this->employeur = Employeur::create([
            'nom' => 'Institut Universitaire', 'sigle' => 'IUM', 'actif' => true,
            'niu' => 'M012345678901X', 'numero_cnps' => '1-234567', 'signataire' => 'La Directrice',
        ]);
        $this->autre = Employeur::create(['nom' => 'Autre', 'sigle' => 'AUT', 'actif' => true]);
    }

    private function gestionnaire(): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $user->employeursRh()->attach($this->employeur);

        return $user;
    }

    private function bulletin(string $statut = 'brouillon', ?Employeur $employeur = null): Bulletin
    {
        $employeur ??= $this->employeur;

        $categorie = CategorieRh::firstOrCreate(['libelle' => 'Catégorie 7'], ['actif' => true]);
        $echelon = Echelon::firstOrCreate(
            ['categorie_rh_id' => $categorie->id, 'libelle' => 'A'],
            ['numero' => 1, 'salaire' => 200000, 'actif' => true],
        );

        Contrat::create([
            'agent_id' => Agent::create(['user_id' => User::factory()->create(['matricule' => 'LM-26'.rand(1000, 9999)])->id])->id,
            'employeur_id' => $employeur->id,
            'type' => 'cdi', 'poste' => 'Enseignant', 'date_debut' => '2026-01-01',
            'quotite' => 100, 'echelon_id' => $echelon->id, 'statut' => 'actif',
        ]);

        app(PaieService::class)->genererMois($employeur, 9, 2026);

        $bulletin = Bulletin::latest('id')->firstOrFail();
        $bulletin->update(['statut' => $statut]);

        return $bulletin->fresh(['agent.user', 'employeur', 'contrat']);
    }

    // ------------------------------------------------------------- cote RH

    public function test_la_rh_previsualise_le_bulletin(): void
    {
        $bulletin = $this->bulletin();

        $reponse = $this->actingAs($this->gestionnaire())
            ->get(route('personnel.paie.bulletin.pdf', [$bulletin, 'apercu' => 1]))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', $reponse->headers->get('content-type'));
        // Servi en ligne : c'est ce qui permet de l'afficher dans la page.
        $this->assertStringContainsString('inline', (string) $reponse->headers->get('content-disposition'));
    }

    public function test_la_rh_telecharge_le_bulletin(): void
    {
        $bulletin = $this->bulletin();

        $reponse = $this->actingAs($this->gestionnaire())
            ->get(route('personnel.paie.bulletin.pdf', $bulletin))
            ->assertOk();

        $this->assertStringContainsString('attachment', (string) $reponse->headers->get('content-disposition'));
    }

    /** Un brouillon se relit avant d'etre arrete : c'est tout l'interet. */
    public function test_un_brouillon_se_previsualise(): void
    {
        $bulletin = $this->bulletin('brouillon');

        $this->actingAs($this->gestionnaire())
            ->get(route('personnel.paie.bulletin.pdf', [$bulletin, 'apercu' => 1]))
            ->assertOk();
    }

    public function test_un_gestionnaire_d_une_autre_entite_n_y_accede_pas(): void
    {
        $bulletin = $this->bulletin('valide', $this->autre);

        $this->actingAs($this->gestionnaire())
            ->get(route('personnel.paie.bulletin.pdf', $bulletin))
            ->assertForbidden();
    }

    public function test_un_employe_ordinaire_n_accede_pas_au_pdf_de_la_rh(): void
    {
        $bulletin = $this->bulletin('valide');

        $this->actingAs(User::factory()->create())
            ->get(route('personnel.paie.bulletin.pdf', $bulletin))
            ->assertForbidden();
    }

    // -------------------------------------------------------- cote salarie

    public function test_le_salarie_previsualise_son_bulletin(): void
    {
        $bulletin = $this->bulletin('valide');

        $reponse = $this->actingAs($bulletin->agent->user)
            ->get(route('mes-bulletins.pdf', [$bulletin, 'apercu' => 1]))
            ->assertOk();

        $this->assertStringContainsString('inline', (string) $reponse->headers->get('content-disposition'));
    }

    public function test_le_salarie_telecharge_son_bulletin(): void
    {
        $bulletin = $this->bulletin('valide');

        $reponse = $this->actingAs($bulletin->agent->user)
            ->get(route('mes-bulletins.pdf', $bulletin))
            ->assertOk();

        $this->assertStringContainsString('attachment', (string) $reponse->headers->get('content-disposition'));
    }

    public function test_le_salarie_ne_voit_pas_un_brouillon(): void
    {
        $bulletin = $this->bulletin('brouillon');

        $this->actingAs($bulletin->agent->user)
            ->get(route('mes-bulletins.pdf', [$bulletin, 'apercu' => 1]))
            ->assertNotFound();
    }

    public function test_personne_ne_previsualise_le_bulletin_d_un_autre(): void
    {
        $bulletin = $this->bulletin('valide');

        $this->actingAs(User::factory()->create())
            ->get(route('mes-bulletins.pdf', [$bulletin, 'apercu' => 1]))
            ->assertForbidden();
    }

    // ------------------------------------------------------- le document

    public function test_le_document_porte_l_identite_et_le_decompte(): void
    {
        $bulletin = $this->bulletin('paye');

        $contenu = $this->actingAs($this->gestionnaire())
            ->get(route('personnel.paie.bulletin.pdf', $bulletin))->getContent();

        // Le PDF est compresse : on verifie sur le rendu HTML du meme modele.
        $html = view('pdf.bulletin', [
            'bulletin' => $bulletin,
            'enTete' => app(\App\Services\BulletinPdf::class)->enTete($bulletin),
            'employeur' => $bulletin->employeur,
            'contrat' => $bulletin->contrat,
            'agent' => $bulletin->agent,
            'salarie' => $bulletin->agent->user,
            'mention' => 'Essai',
        ])->render();

        $this->assertNotEmpty($contenu);
        $this->assertStringContainsString('BULLETIN DE PAIE', $html);
        $this->assertStringContainsString('septembre 2026', $html);
        $this->assertStringContainsString('Institut Universitaire', $html);
        $this->assertStringContainsString('M012345678901X', $html);          // NIU
        $this->assertStringContainsString('La Directrice', $html);           // signataire
        $this->assertStringContainsString('Net à payer', $html);
        $this->assertStringContainsString('200 000 F', $html);               // salaire de base
        $this->assertStringContainsString($bulletin->agent->user->matricule, $html);
    }

    public function test_un_brouillon_est_annonce_comme_provisoire(): void
    {
        $bulletin = $this->bulletin('brouillon');

        $html = view('pdf.bulletin', [
            'bulletin' => $bulletin,
            'enTete' => app(\App\Services\BulletinPdf::class)->enTete($bulletin),
            'employeur' => $bulletin->employeur,
            'contrat' => $bulletin->contrat,
            'agent' => $bulletin->agent,
            'salarie' => $bulletin->agent->user,
            'mention' => 'Essai',
        ])->render();

        $this->assertStringContainsString('Provisoire', $html);
    }

    public function test_le_fichier_porte_le_matricule_et_la_periode(): void
    {
        $bulletin = $this->bulletin('valide');
        $nom = app(\App\Services\BulletinPdf::class)->nomDuFichier($bulletin);

        $this->assertSame(
            'bulletin-'.$bulletin->agent->user->matricule.'-2026-09.pdf',
            $nom,
        );
    }
}
