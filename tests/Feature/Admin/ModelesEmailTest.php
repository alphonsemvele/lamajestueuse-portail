<?php

namespace Tests\Feature\Admin;

use App\Mail\Badge\BadgePret;
use App\Mail\Compte\CompteValide;
use App\Mail\CourrielDuPortail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Modeles de courriels : les relire et les essayer sans reproduire la
 * situation qui les declenche.
 */
class ModelesEmailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create(['email' => 'admin@lamajestueuse.cm']);
    }

    public function test_l_ecran_est_reserve_aux_administrateurs(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.email.modeles'))->assertForbidden();
    }

    public function test_le_catalogue_est_groupe_par_procedure(): void
    {
        $this->actingAs($this->admin())->get(route('admin.email.modeles'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/email/modeles')
                ->has('groupes', count(config('emails')))
                ->where('groupes.0.nom', 'Compte')
                // L'adresse de l'administrateur est proposée d'office.
                ->where('destinataire', 'admin@lamajestueuse.cm'));
    }

    /** Chaque courriel declare doit savoir se construire pour l'apercu. */
    public function test_tous_les_courriels_declares_se_rendent(): void
    {
        $admin = $this->admin();

        foreach (collect(config('emails'))->collapse() as $cle => $courriel) {
            $classe = $courriel['classe'];

            $this->assertTrue(
                is_subclass_of($classe, CourrielDuPortail::class),
                "{$classe} doit étendre CourrielDuPortail.",
            );

            $reponse = $this->actingAs($admin)->get(route('admin.email.apercu', $cle));

            $reponse->assertOk();
            $reponse->assertHeader('content-type', 'text/html; charset=UTF-8');
            $this->assertNotEmpty($reponse->getContent(), "L'aperçu de {$cle} est vide.");
        }
    }

    public function test_un_apercu_montre_les_donnees_d_exemple(): void
    {
        $contenu = $this->actingAs($this->admin())->get(route('admin.email.apercu', 'compte-valide'))->getContent();

        $this->assertStringContainsString('Claire NKOA', $contenu);
        $this->assertStringContainsString('LM-260147', $contenu);
    }

    public function test_un_modele_inconnu_repond_404(): void
    {
        $this->actingAs($this->admin())->get(route('admin.email.apercu', 'inexistant'))->assertNotFound();
    }

    public function test_l_envoi_d_essai_part_a_l_adresse_donnee(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())->post(route('admin.email.envoyer', 'badge-pret'), [
            'destinataire' => 'alphonse@lamajestueuse.cm',
        ])->assertRedirect()->assertSessionHas('status');

        Mail::assertSent(BadgePret::class, fn ($mail) => $mail->hasTo('alphonse@lamajestueuse.cm'));
    }

    public function test_l_envoi_porte_bien_le_modele_demande(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())->post(route('admin.email.envoyer', 'compte-valide'), [
            'destinataire' => 'alphonse@lamajestueuse.cm',
        ]);

        Mail::assertSent(CompteValide::class);
        Mail::assertNotSent(BadgePret::class);
    }

    public function test_une_adresse_invalide_est_refusee(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())->post(route('admin.email.envoyer', 'compte-valide'), [
            'destinataire' => 'pas-une-adresse',
        ])->assertSessionHasErrors('destinataire');

        Mail::assertNothingSent();
    }

    public function test_un_employe_n_envoie_rien(): void
    {
        Mail::fake();

        $this->actingAs(User::factory()->create())->post(route('admin.email.envoyer', 'compte-valide'), [
            'destinataire' => 'alphonse@lamajestueuse.cm',
        ])->assertForbidden();

        Mail::assertNothingSent();
    }
}
