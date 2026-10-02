<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Une page laissee ouverte trop longtemps perd son jeton de session.
 *
 * Sans traitement, l'envoi semble ne rien faire : on charge, on revient,
 * sans un mot. On renvoie l'utilisateur sur son formulaire, avec sa saisie
 * et une explication.
 */
class SessionExpireeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/essai-jeton', fn () => throw new TokenMismatchException())->middleware('web');
    }

    public function test_un_jeton_expire_renvoie_au_formulaire_avec_un_message(): void
    {
        $reponse = $this->from('/inscription')->post('/essai-jeton', [
            'name' => 'Célestin',
            'password' => 'MotDePasse2026!',
        ]);

        $reponse->assertRedirect('/inscription');
        $reponse->assertSessionHasErrors('session');

        $this->assertStringContainsString(
            'session a expiré',
            session('errors')->first('session'),
        );
    }

    public function test_la_saisie_est_conservee_sauf_les_mots_de_passe(): void
    {
        $this->from('/inscription')->post('/essai-jeton', [
            'name' => 'Célestin',
            'phone' => '680646122',
            'password' => 'MotDePasse2026!',
            'password_confirmation' => 'MotDePasse2026!',
        ]);

        $this->assertSame('Célestin', session('_old_input.name'));
        $this->assertSame('680646122', session('_old_input.phone'));

        // Un mot de passe ne repart jamais en session.
        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));
    }
}
