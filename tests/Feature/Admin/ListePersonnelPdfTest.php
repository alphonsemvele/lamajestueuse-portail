<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\Employeur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La liste du personnel en PDF : nom, prenom et matricule, par ordre
 * alphabetique.
 */
class ListePersonnelPdfTest extends TestCase
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
            'nom' => 'Institut Universitaire', 'sigle' => 'IUM',
            'application_id' => $this->institut->id, 'actif' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function membre(string $prenom, string $nom, ?string $email = null): User
    {
        $membre = User::factory()->create(['name' => $prenom, 'lastname' => $nom, 'email' => $email]);
        $membre->applications()->attach($this->institut);

        return $membre;
    }

    /** Le rendu HTML du meme modele, pour lire ce que le PDF contient. */
    private function rendu(User $admin): string
    {
        $this->actingAs($admin)->get(route('admin.users.liste'))->assertOk();

        $personnel = User::duPersonnel()
            ->orderByRaw('LOWER(COALESCE(lastname, name)) ASC')
            ->orderByRaw('LOWER(name) ASC')
            ->get(['id', 'name', 'lastname', 'matricule']);

        return view('pdf.liste-personnel', [
            'personnel' => $personnel,
            'editeLe' => now()->translatedFormat('j F Y'),
            'couleur' => '#0f766e',
            'logo' => null,
        ])->render();
    }

    public function test_la_liste_s_obtient_en_pdf(): void
    {
        $this->membre('Célestin', 'NSOE');

        $reponse = $this->actingAs($this->admin())->get(route('admin.users.liste'))->assertOk();

        $this->assertStringContainsString('application/pdf', $reponse->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $reponse->headers->get('content-disposition'));
    }

    public function test_elle_se_previsualise_en_ligne(): void
    {
        $this->membre('Célestin', 'NSOE');

        $reponse = $this->actingAs($this->admin())
            ->get(route('admin.users.liste', ['apercu' => 1]))->assertOk();

        $this->assertStringContainsString('inline', (string) $reponse->headers->get('content-disposition'));
    }

    public function test_elle_est_classee_par_ordre_alphabetique(): void
    {
        $this->membre('Alvine', 'ZOA');
        $this->membre('Célestin', 'ABENA');
        $this->membre('Marie', 'MBALLA');

        $html = $this->rendu($this->admin());

        $this->assertLessThan(
            strpos($html, 'MBALLA'),
            strpos($html, 'ABENA'),
            'ABENA devrait précéder MBALLA.'
        );
        $this->assertLessThan(
            strpos($html, 'ZOA'),
            strpos($html, 'MBALLA'),
            'MBALLA devrait précéder ZOA.'
        );
    }

    public function test_elle_s_ouvre_aussi_en_page_imprimable(): void
    {
        $this->membre('Célestin', 'NSOE');

        $reponse = $this->actingAs($this->admin())
            ->get(route('admin.users.liste', ['format' => 'html']))
            ->assertOk();

        $this->assertStringContainsString('text/html', $reponse->headers->get('content-type'));
        $reponse->assertSee('LISTE DU PERSONNEL', false);
        $reponse->assertSee('NSOE', false);
        // De quoi lancer l'impression depuis la page.
        $reponse->assertSee('window.print()', false);
    }

    public function test_un_employe_ordinaire_n_obtient_pas_la_liste(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.users.liste'))
            ->assertForbidden();
    }

    /** Seuls ceux marques comme personnel figurent sur la liste. */
    public function test_un_compte_hors_personnel_n_y_figure_pas(): void
    {
        $this->membre('Marie', 'MBALLA');

        $technique = User::factory()->create([
            'name' => 'Compte', 'lastname' => 'TECHNIQUE', 'dans_le_personnel' => false,
        ]);
        $technique->applications()->attach($this->institut);

        $html = $this->rendu($this->admin());

        $this->assertStringContainsString('MBALLA', $html);
        $this->assertStringNotContainsString('TECHNIQUE', $html);
    }

    public function test_un_gestionnaire_rh_n_y_a_pas_acces(): void
    {
        $gestionnaire = User::factory()->create();
        $gestionnaire->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $gestionnaire->employeursRh()->attach($this->employeur);

        // La liste releve de l'administration du portail, pas du module RH.
        $this->actingAs($gestionnaire)->get(route('admin.users.liste'))->assertForbidden();
    }
}
