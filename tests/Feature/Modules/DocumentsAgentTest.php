<?php

namespace Tests\Feature\Modules;

use App\Models\Agent;
use App\Models\Application;
use App\Models\DocumentAgent;
use App\Models\Employeur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pieces du dossier du personnel.
 *
 * Le point sensible : un contrat signe ou une copie de CNI ne doit jamais
 * etre atteignable par sa seule adresse, ni par quelqu'un hors perimetre.
 */
class DocumentsAgentTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    private Application $institut;

    private Employeur $employeur;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(DocumentAgent::DISQUE);

        $this->module = Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        $this->institut = Application::factory()->create(['name' => 'IUM']);
        $this->employeur = Employeur::create([
            'nom' => 'Institut Universitaire', 'sigle' => 'IUM', 'application_id' => $this->institut->id,
        ]);
    }

    private function gestionnaire(): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $user->employeursRh()->attach($this->employeur);

        return $user;
    }

    private function membre(string $nom = 'NKOA'): User
    {
        $membre = User::factory()->create(['lastname' => $nom]);
        $membre->applications()->attach($this->institut);

        return $membre;
    }

    private function deposer(User $gestionnaire, User $membre, array $champs = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($gestionnaire)->post(route('personnel.documents.store', $membre), array_merge([
            'type' => 'cv',
            'fichier' => UploadedFile::fake()->create('CV Claire NKOA.pdf', 180, 'application/pdf'),
        ], $champs));
    }

    public function test_une_piece_se_depose_et_ouvre_le_dossier(): void
    {
        $membre = $this->membre();

        $this->assertSame(0, Agent::count());

        $this->deposer($this->gestionnaire(), $membre)->assertRedirect()->assertSessionHas('status');

        // Le dossier naît au dépôt, comme à toute première saisie.
        $this->assertSame(1, Agent::where('user_id', $membre->id)->count());

        $document = DocumentAgent::firstOrFail();
        $this->assertSame('cv', $document->type);
        $this->assertSame('CV Claire NKOA.pdf', $document->nom_origine);
        Storage::disk(DocumentAgent::DISQUE)->assertExists($document->fichier);
    }

    /** Le nom stocké ne révèle rien : le nom d'origine vit à part. */
    public function test_le_fichier_est_range_sous_un_nom_tire_au_hasard(): void
    {
        $this->deposer($this->gestionnaire(), $this->membre());

        $document = DocumentAgent::firstOrFail();
        $this->assertStringStartsWith('personnel/documents/', $document->fichier);
        $this->assertStringNotContainsString('NKOA', $document->fichier);
    }

    public function test_l_intitule_reprend_la_nature_quand_il_est_vide(): void
    {
        $this->deposer($this->gestionnaire(), $this->membre(), ['type' => 'contrat', 'libelle' => '']);

        $this->assertSame('Contrat signé', DocumentAgent::firstOrFail()->libelle);
    }

    public function test_un_fichier_trop_lourd_est_refuse(): void
    {
        $this->deposer($this->gestionnaire(), $this->membre(), [
            'fichier' => UploadedFile::fake()->create('gros.pdf', 11000, 'application/pdf'),
        ])->assertSessionHasErrors('fichier');

        $this->assertSame(0, DocumentAgent::count());
    }

    public function test_un_format_non_prevu_est_refuse(): void
    {
        $this->deposer($this->gestionnaire(), $this->membre(), [
            'fichier' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors('fichier');
    }

    public function test_une_nature_inconnue_est_refusee(): void
    {
        $this->deposer($this->gestionnaire(), $this->membre(), ['type' => 'photo-de-vacances'])
            ->assertSessionHasErrors('type');
    }

    public function test_la_piece_se_telecharge_sous_son_nom_d_origine(): void
    {
        $gestionnaire = $this->gestionnaire();
        $this->deposer($gestionnaire, $this->membre());

        $reponse = $this->actingAs($gestionnaire)
            ->get(route('personnel.documents.telecharger', DocumentAgent::firstOrFail()));

        $reponse->assertOk();
        $this->assertStringContainsString('CV Claire NKOA.pdf', $reponse->headers->get('content-disposition'));
    }

    public function test_la_fiche_liste_les_pieces(): void
    {
        $gestionnaire = $this->gestionnaire();
        $membre = $this->membre();
        $this->deposer($gestionnaire, $membre);
        $this->deposer($gestionnaire, $membre, [
            'type' => 'diplome',
            'fichier' => UploadedFile::fake()->create('master.pdf', 90, 'application/pdf'),
        ]);

        $this->actingAs($gestionnaire)->get(route('personnel.agents.show', $membre))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents', 2)
                ->has('referentiels.documents', count(DocumentAgent::TYPES)));
    }

    public function test_retirer_une_piece_efface_le_fichier(): void
    {
        $gestionnaire = $this->gestionnaire();
        $this->deposer($gestionnaire, $this->membre());
        $document = DocumentAgent::firstOrFail();
        $chemin = $document->fichier;

        $this->actingAs($gestionnaire)->delete(route('personnel.documents.destroy', $document))
            ->assertRedirect();

        $this->assertSame(0, DocumentAgent::count());
        Storage::disk(DocumentAgent::DISQUE)->assertMissing($chemin);
    }

    /** Un fichier disparu du disque ne laisse pas un lien mort. */
    public function test_une_piece_dont_le_fichier_manque_est_signalee(): void
    {
        $gestionnaire = $this->gestionnaire();
        $membre = $this->membre();
        $this->deposer($gestionnaire, $membre);

        $document = DocumentAgent::firstOrFail();
        Storage::disk(DocumentAgent::DISQUE)->delete($document->fichier);

        $this->actingAs($gestionnaire)->get(route('personnel.agents.show', $membre))
            ->assertInertia(fn (Assert $page) => $page->where('documents.0.manquant', true));

        $this->actingAs($gestionnaire)->get(route('personnel.documents.telecharger', $document))
            ->assertNotFound();
    }

    // --------------------------------------------------------------- accès

    public function test_un_lecteur_ne_depose_rien(): void
    {
        $lecteur = User::factory()->create();
        $lecteur->applications()->attach($this->module, ['role_in_app' => 'lecteur', 'roles' => json_encode(['lecteur'])]);

        $this->deposer($lecteur, $this->membre())->assertForbidden();
        $this->assertSame(0, DocumentAgent::count());
    }

    public function test_un_gestionnaire_d_un_autre_institut_n_y_accede_pas(): void
    {
        $this->deposer($this->gestionnaire(), $this->membre());
        $document = DocumentAgent::firstOrFail();

        $autreInstitut = Application::factory()->create(['name' => 'IFPM']);
        $autreEmployeur = Employeur::create([
            'nom' => 'Formation', 'sigle' => 'IFPM', 'application_id' => $autreInstitut->id,
        ]);
        $etranger = User::factory()->create();
        $etranger->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $etranger->employeursRh()->attach($autreEmployeur);

        $this->actingAs($etranger)->get(route('personnel.documents.telecharger', $document))->assertForbidden();
        $this->actingAs($etranger)->delete(route('personnel.documents.destroy', $document))->assertForbidden();

        $this->assertSame(1, DocumentAgent::count());
    }

    public function test_un_employe_ordinaire_n_y_accede_pas(): void
    {
        $this->deposer($this->gestionnaire(), $this->membre());

        $this->actingAs(User::factory()->create())
            ->get(route('personnel.documents.telecharger', DocumentAgent::firstOrFail()))
            ->assertForbidden();
    }
}
