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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Le logo du bulletin se cherche en trois temps : l'employeur, puis
 * l'institut auquel il est rattache, enfin le module.
 */
class LogoEmployeurTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    private Application $institut;

    private Employeur $employeur;

    private Echelon $echelon;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        $this->module = Application::factory()->module('bulletins')->create(['name' => 'Mon bulletin de paie']);
        $this->institut = Application::factory()->create(['name' => 'IUM']);
        $this->employeur = Employeur::create([
            'nom' => 'Institut Universitaire',
            'sigle' => 'IUM',
            'application_id' => $this->institut->id,
            'actif' => true,
        ]);

        $categorie = CategorieRh::create(['libelle' => 'Catégorie 1', 'actif' => true]);
        $this->echelon = Echelon::create([
            'categorie_rh_id' => $categorie->id,
            'numero' => 1, 'libelle' => 'A', 'salaire' => 200000, 'actif' => true,
        ]);
    }

    /**
     * Pose une image sur le disque public et renvoie son chemin. Le contenu
     * varie avec le chemin, pour distinguer les trois logos a l'arrivee.
     */
    private function poserUneImage(string $chemin): string
    {
        Storage::disk('public')->put($chemin, $this->png($chemin));

        return $chemin;
    }

    /** Un PNG 1x1 valide, suivi d'une marque propre a chaque fichier. */
    private function png(string $marque): string
    {
        $pixel = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        return $pixel.$marque;
    }

    private function bulletinDe(): Bulletin
    {
        Contrat::create([
            'agent_id' => Agent::create(['user_id' => User::factory()->create()->id])->id,
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi', 'poste' => 'Enseignant', 'date_debut' => '2026-01-01',
            'quotite' => 100, 'echelon_id' => $this->echelon->id, 'statut' => 'actif',
        ]);

        app(PaieService::class)->genererMois($this->employeur, 9, 2026);

        $bulletin = Bulletin::firstOrFail();
        $bulletin->update(['statut' => 'valide']);

        return $bulletin->fresh(['agent.user', 'employeur', 'contrat']);
    }

    private function pdf(Bulletin $bulletin): string
    {
        return $this->actingAs($bulletin->agent->user)
            ->get(route('mes-bulletins.pdf', $bulletin))
            ->assertOk()
            ->getContent();
    }

    // --------------------------------------------------- la chaine de repli

    public function test_le_bulletin_porte_le_logo_de_l_employeur(): void
    {
        $this->employeur->update(['logo' => $this->poserUneImage('employeurs/logos/ium.png')]);
        $this->institut->update(['logo' => $this->poserUneImage('applications/logos/institut.png')]);
        $this->module->update(['logo' => $this->poserUneImage('applications/logos/module.png')]);

        $this->assertNotEmpty($this->pdf($this->bulletinDe()));
        $this->assertSame(
            Storage::disk('public')->get('employeurs/logos/ium.png'),
            $this->logoRetenu(),
        );
    }

    public function test_sans_logo_d_employeur_c_est_celui_de_l_institut(): void
    {
        $this->institut->update(['logo' => $this->poserUneImage('applications/logos/institut.png')]);
        $this->module->update(['logo' => $this->poserUneImage('applications/logos/module.png')]);

        $this->assertSame(
            Storage::disk('public')->get('applications/logos/institut.png'),
            $this->logoRetenu(),
        );
    }

    public function test_sans_institut_c_est_le_logo_du_module(): void
    {
        $this->module->update(['logo' => $this->poserUneImage('applications/logos/module.png')]);

        $this->assertSame(
            Storage::disk('public')->get('applications/logos/module.png'),
            $this->logoRetenu(),
        );
    }

    public function test_sans_aucun_logo_le_bulletin_s_edite_quand_meme(): void
    {
        $this->assertNull($this->logoRetenu());
        $this->assertNotEmpty($this->pdf($this->bulletinDe()));
    }

    /** Un fichier annonce mais absent du disque ne bloque pas le repli. */
    public function test_un_logo_d_employeur_introuvable_cede_au_suivant(): void
    {
        $this->employeur->update(['logo' => 'employeurs/logos/disparu.png']);
        $this->institut->update(['logo' => $this->poserUneImage('applications/logos/institut.png')]);

        $this->assertSame(
            Storage::disk('public')->get('applications/logos/institut.png'),
            $this->logoRetenu(),
        );
    }

    /**
     * Le logo retenu par le controleur, decode depuis la data URI : c'est le
     * seul moyen fiable de savoir lequel des trois a gagne.
     */
    private function logoRetenu(): ?string
    {
        $bulletin = Bulletin::first() ?? $this->bulletinDe();

        $methode = new \ReflectionMethod(
            \App\Http\Controllers\Modules\MesBulletinsController::class,
            'enTeteDeLEmployeur'
        );

        $enTete = $methode->invoke(app(\App\Http\Controllers\Modules\MesBulletinsController::class), $bulletin);

        if ($enTete['logo'] === null) {
            return null;
        }

        return base64_decode(substr($enTete['logo'], strpos($enTete['logo'], ',') + 1));
    }

    // ------------------------------------------------------- la saisie du logo

    public function test_un_administrateur_televerse_le_logo_d_un_employeur(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->post(route('personnel.employeurs.store'), [
            'nom' => 'Institut de Formation',
            'sigle' => 'IFPM',
            'actif' => true,
            'logo_file' => UploadedFile::fake()->image('ifpm.png', 60, 60),
        ])->assertSessionHasNoErrors();

        $cree = Employeur::where('sigle', 'IFPM')->firstOrFail();

        $this->assertNotNull($cree->logo);
        Storage::disk('public')->assertExists($cree->logo);
    }

    public function test_le_logo_se_retire(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->employeur->update(['logo' => $this->poserUneImage('employeurs/logos/ium.png')]);

        $this->actingAs($admin)->put(route('personnel.employeurs.update', $this->employeur), [
            'nom' => $this->employeur->nom,
            'sigle' => $this->employeur->sigle,
            'actif' => true,
            'remove_logo' => true,
        ])->assertSessionHasNoErrors();

        $this->assertNull($this->employeur->fresh()->logo);
        Storage::disk('public')->assertMissing('employeurs/logos/ium.png');
    }

    public function test_une_modification_sans_logo_garde_celui_en_place(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $chemin = $this->poserUneImage('employeurs/logos/ium.png');
        $this->employeur->update(['logo' => $chemin]);

        $this->actingAs($admin)->put(route('personnel.employeurs.update', $this->employeur), [
            'nom' => 'Institut Universitaire de la Majestueuse',
            'sigle' => $this->employeur->sigle,
            'actif' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame($chemin, $this->employeur->fresh()->logo);
    }

    public function test_un_fichier_trop_lourd_est_refuse(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->post(route('personnel.employeurs.store'), [
            'nom' => 'Trop lourd', 'sigle' => 'TL', 'actif' => true,
            'logo_file' => UploadedFile::fake()->create('logo.png', 2048, 'image/png'),
        ])->assertSessionHasErrors('logo_file');
    }

    public function test_un_gestionnaire_ne_change_pas_le_logo(): void
    {
        $gestionnaire = User::factory()->create();
        $gestionnaire->applications()->attach(
            Application::where('module_key', 'personnel')->firstOrFail(),
            ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]
        );

        $this->actingAs($gestionnaire)->put(route('personnel.employeurs.update', $this->employeur), [
            'nom' => $this->employeur->nom, 'sigle' => $this->employeur->sigle, 'actif' => true,
            'logo_file' => UploadedFile::fake()->image('pirate.png'),
        ])->assertForbidden();
    }

    public function test_l_ecran_des_employeurs_porte_l_adresse_du_logo(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->employeur->update(['logo' => $this->poserUneImage('employeurs/logos/ium.png')]);

        $this->actingAs($admin)->get(route('personnel.employeurs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('employeurs.0.logo', 'employeurs/logos/ium.png')
                ->where('employeurs.0.logoUrl', Storage::disk('public')->url('employeurs/logos/ium.png')));
    }
}
