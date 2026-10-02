<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A la connexion, les quatre chiffres du matricule suffisent : le prefixe et
 * l'annee se completent.
 */
class MatriculeCourtTest extends TestCase
{
    use RefreshDatabase;

    private function compte(string $matricule): User
    {
        return User::factory()->create([
            'matricule' => $matricule,
            'status' => 'active',
            'password' => Hash::make('motdepasse'),
        ]);
    }

    private function connecter(string $identifiant)
    {
        return $this->post('/connexion', [
            'username' => $identifiant,
            'password' => 'motdepasse',
        ]);
    }

    public function test_quatre_chiffres_suffisent_pour_l_annee_en_cours(): void
    {
        $annee = substr((string) date('Y'), -2);
        $personne = $this->compte('LM-'.$annee.'0147');

        $this->connecter('0147')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($personne);
    }

    public function test_les_zeros_de_tete_sont_facultatifs(): void
    {
        $annee = substr((string) date('Y'), -2);
        $personne = $this->compte('LM-'.$annee.'0007');

        $this->connecter('7')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($personne);
    }

    /** Recrute une autre annee : le seul matricule qui finit ainsi fait foi. */
    public function test_une_autre_annee_passe_si_elle_est_sans_ambiguite(): void
    {
        $personne = $this->compte('LM-220147');

        $this->connecter('0147')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($personne);
    }

    public function test_deux_annees_pour_les_memes_chiffres_refusent_le_raccourci(): void
    {
        $this->compte('LM-220147');
        $this->compte('LM-230147');

        $this->connecter('0147')->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_le_matricule_entier_fonctionne_toujours(): void
    {
        $personne = $this->compte('LM-220147');

        $this->connecter('LM-220147')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($personne);
    }

    public function test_un_ancien_matricule_n_est_pas_touche(): void
    {
        $personne = $this->compte('1100E-20');

        $this->connecter('1100E-20')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($personne);
    }

    public function test_l_adresse_reste_une_adresse(): void
    {
        $personne = $this->compte('LM-260147');

        $this->connecter($personne->email)->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($personne);
    }

    public function test_des_chiffres_qui_ne_menent_a_personne_echouent_normalement(): void
    {
        $this->compte('LM-260147');

        $this->connecter('9999')->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
