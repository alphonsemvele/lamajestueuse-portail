<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_visiteur_est_redirige_vers_lunique_page_de_connexion(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_la_page_de_connexion_saffiche(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
    }

    public function test_un_compte_en_attente_ne_peut_pas_se_connecter(): void
    {
        // Le formulaire d'inscription est public, mais le compte cree reste
        // inutilisable tant qu'un administrateur ne l'a pas valide.
        $user = User::factory()->create(['status' => 'pending']);

        $this->from(route('login'))
            ->post(route('login'), ['username' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_connexion_avec_adresse_email(): void
    {
        $user = User::factory()->create(['email' => 'jean@lamajestueuse.cm']);

        $this->post(route('login'), [
            'username' => 'jean@lamajestueuse.cm',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('access_logs', ['user_id' => $user->id, 'action' => 'login']);
    }

    public function test_connexion_avec_matricule(): void
    {
        $user = User::factory()->create(['matricule' => 'LM-0042']);

        $this->post(route('login'), ['username' => 'LM-0042', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_mot_de_passe_incorrect_refuse(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))
            ->post(route('login'), ['username' => $user->email, 'password' => 'mauvais'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_compte_suspendu_refuse(): void
    {
        $user = User::factory()->suspended()->create();

        $this->from(route('login'))
            ->post(route('login'), ['username' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_deconnexion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('access_logs', ['user_id' => $user->id, 'action' => 'logout']);
    }

    public function test_la_langue_choisie_avant_connexion_est_conservee_et_memorisee(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);

        $this->get(route('locale.switch', 'en'));
        $this->post(route('login'), ['username' => $user->email, 'password' => 'password']);

        $this->assertSame('en', session('locale'));
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_sans_choix_explicite_la_langue_du_compte_sapplique(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->post(route('login'), ['username' => $user->email, 'password' => 'password']);

        $this->assertSame('en', session('locale'));
    }

    public function test_le_lien_profond_est_conserve_apres_connexion(): void
    {
        $user = User::factory()->create();

        // L'employe visait une page precise : il doit y revenir apres s'etre
        // authentifie, et non atterrir sur le tableau de bord.
        $this->get('/admin')->assertRedirect(route('login'));

        $this->post(route('login'), ['username' => $user->email, 'password' => 'password'])
            ->assertRedirect('/admin');
    }
}
