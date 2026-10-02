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

    public function test_la_tuile_se_pose_sur_le_tableau_de_bord_de_tous(): void
    {
        Application::factory()->module('tutoriels')->create(['name' => 'Tutoriels']);

        $this->actingAs(User::factory()->create(['status' => 'active']))
            ->get(route('dashboard'))
            ->assertInertia(function (Assert $page) {
                $tuiles = collect($page->toArray()['props']['apps'])->pluck('moduleKey');

                $this->assertContains('tutoriels', $tuiles);
            });
    }
}
