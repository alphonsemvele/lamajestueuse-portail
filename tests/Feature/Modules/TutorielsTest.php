<?php

namespace Tests\Feature\Modules;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Les tutoriels : publics, pas a pas, et precedes des memes prealables.
 */
class TutorielsTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    protected function setUp(): void
    {
        parent::setUp();

        // Les pages sont publiques, mais le module doit etre en service.
        $this->module = Application::factory()->module('tutoriels')->create(['name' => 'Tutoriels']);
    }

    public function test_les_tutoriels_se_consultent_sans_compte(): void
    {
        $this->get(route('tutoriels.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('modules/tutoriels/index'));
    }

    public function test_un_tutoriel_se_consulte_sans_compte(): void
    {
        $this->get(route('tutoriels.show', 'demander-son-badge'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/tutoriels/show')
                ->where('tutoriel.cle', 'demander-son-badge'));
    }

    public function test_un_employe_connecte_y_accede_aussi(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('tutoriels.index'))
            ->assertOk();
    }

    public function test_un_tutoriel_inconnu_repond_404(): void
    {
        $this->get(route('tutoriels.show', 'ce-tutoriel-nexiste-pas'))->assertNotFound();
    }

    /** Tout tutoriel commence par l'inscription et l'acces au module. */
    public function test_chaque_tutoriel_est_precede_des_prealables(): void
    {
        $this->get(route('tutoriels.show', 'demander-son-badge'))
            ->assertInertia(function (Assert $page) {
                $prealables = $page->toArray()['props']['prealables'];

                $titres = array_column($prealables['etapes'], 'titre');

                // On commence par la connexion : c'est la porte du portail.
                $this->assertStringContainsString('connecter', mb_strtolower($titres[0]));
                $this->assertStringContainsString('compte', mb_strtolower($titres[1]));
                $this->assertStringContainsString('validation', mb_strtolower($titres[2]));
                $this->assertStringContainsString('module', mb_strtolower(end($titres)));
            });
    }

    public function test_le_tutoriel_du_badge_deroule_ses_etapes(): void
    {
        $this->get(route('tutoriels.show', 'demander-son-badge'))
            ->assertInertia(function (Assert $page) {
                $etapes = $page->toArray()['props']['tutoriel']['etapes'];

                $this->assertGreaterThanOrEqual(6, count($etapes));

                foreach ($etapes as $etape) {
                    $this->assertNotEmpty($etape['titre']);
                    $this->assertNotEmpty($etape['texte']);
                    $this->assertArrayHasKey('capture', $etape);
                }
            });
    }

    /** Une capture annoncee mais absente n'est pas transmise. */
    public function test_une_capture_absente_n_est_pas_transmise(): void
    {
        $this->get(route('tutoriels.show', 'demander-son-badge'))
            ->assertInertia(function (Assert $page) {
                foreach ($page->toArray()['props']['tutoriel']['etapes'] as $etape) {
                    if ($etape['capture'] !== null) {
                        $chemin = parse_url($etape['capture'], PHP_URL_PATH);
                        $this->assertFileExists(public_path(ltrim((string) $chemin, '/')));
                    }
                }
            });
    }

    public function test_le_tutoriel_propose_d_ouvrir_son_module(): void
    {
        Application::factory()->module('badges')->create(['name' => 'Badges']);

        $this->get(route('tutoriels.show', 'demander-son-badge'))
            ->assertInertia(fn (Assert $page) => $page->where('module.nom', 'Badges'));
    }

    /** Sans le module installe, on ne propose pas un lien qui mene nulle part. */
    public function test_sans_module_installe_aucun_lien_n_est_propose(): void
    {
        $this->get(route('tutoriels.show', 'demander-son-badge'))
            ->assertInertia(fn (Assert $page) => $page->where('module', null));
    }

    public function test_la_liste_annonce_le_nombre_d_etapes(): void
    {
        $this->get(route('tutoriels.index'))
            ->assertInertia(function (Assert $page) {
                $tutoriels = $page->toArray()['props']['tutoriels'];

                $this->assertNotEmpty($tutoriels);
                $this->assertSame('demander-son-badge', $tutoriels[0]['cle']);
                $this->assertGreaterThan(0, $tutoriels[0]['etapes']);
            });
    }

    /**
     * Le module s'ouvre a tous, mais sa tuile s'attribue : on ne se la voit
     * pas poser d'office sur son tableau de bord.
     */
    public function test_la_tuile_ne_parait_que_si_elle_est_attribuee(): void
    {
        $employe = User::factory()->create(['status' => 'active']);

        $tuiles = fn () => collect(
            $this->actingAs($employe)->get(route('dashboard'))->viewData('page')['props']['apps']
        )->pluck('moduleKey');

        $this->assertNotContains('tutoriels', $tuiles());

        $employe->applications()->attach($this->module);

        $this->assertContains('tutoriels', $tuiles());
    }

    /**
     * Retirer le module du portail le retire pour de bon : ses pages
     * publiques se ferment aussi, sans quoi « retirer » ne voudrait rien
     * dire — la page resterait en ligne et les liens y renverraient.
     */
    public function test_le_module_retire_ferme_ses_pages_publiques(): void
    {
        $this->module->update(['is_active' => false]);

        $this->get(route('tutoriels.index'))->assertNotFound();
        $this->get(route('tutoriels.show', 'demander-son-badge'))->assertNotFound();
    }

    /** Et les pages publiques cessent d'y renvoyer. */
    public function test_les_pages_publiques_cessent_d_y_renvoyer(): void
    {
        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('tutorielsEnService', true));

        $this->module->update(['is_active' => false]);

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('tutorielsEnService', false));
    }
}
