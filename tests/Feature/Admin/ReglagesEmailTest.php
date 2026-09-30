<?php

namespace Tests\Feature\Admin;

use App\Mail\EssaiEnvoi;
use App\Models\ReglageEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Reglages d'envoi des courriels et essai depuis l'administration.
 *
 * Le point sensible : le mot de passe du relais ne doit jamais ressortir
 * vers l'interface, ni etre efface par un enregistrement.
 */
class ReglagesEmailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function reglagesComplets(): array
    {
        return [
            'actif' => true,
            'hote' => 'smtp-relay.brevo.com',
            'port' => 587,
            'identifiant' => 'bafe2d001@smtp-brevo.com',
            'mot_de_passe' => 'secret-du-relais',
            'chiffrement' => 'tls',
            'expediteur' => 'info@lamajestueuse.com',
            'nom_expediteur' => 'La Majestueuse',
        ];
    }

    public function test_l_ecran_est_reserve_aux_administrateurs(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.email.index'))->assertForbidden();
    }

    public function test_l_ecran_annonce_la_configuration_active(): void
    {
        $this->actingAs($this->admin())->get(route('admin.email.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/email/index')
                ->where('active.transport', config('mail.default'))
                ->has('chiffrements', 3)
                // Rien n'est enregistre : l'expediteur du groupe est proposé.
                ->where('reglages.expediteur', 'info@lamajestueuse.com')
                ->where('reglages.actif', false));
    }

    public function test_les_reglages_s_enregistrent(): void
    {
        $this->actingAs($this->admin())->put(route('admin.email.update'), $this->reglagesComplets())
            ->assertRedirect()->assertSessionHas('status');

        $reglages = ReglageEmail::firstOrFail();
        $this->assertTrue($reglages->actif);
        $this->assertSame('smtp-relay.brevo.com', $reglages->hote);
        $this->assertSame('info@lamajestueuse.com', $reglages->expediteur);
        $this->assertSame('secret-du-relais', $reglages->mot_de_passe);
    }

    /** Le mot de passe est chiffré : il ne se lit pas dans la table. */
    public function test_le_mot_de_passe_n_est_pas_stocke_en_clair(): void
    {
        $this->actingAs($this->admin())->put(route('admin.email.update'), $this->reglagesComplets());

        $brut = \DB::table('reglages_email')->value('mot_de_passe');

        $this->assertNotSame('secret-du-relais', $brut);
        $this->assertStringNotContainsString('secret-du-relais', (string) $brut);
    }

    public function test_le_mot_de_passe_ne_ressort_jamais_vers_l_interface(): void
    {
        $this->actingAs($this->admin())->put(route('admin.email.update'), $this->reglagesComplets());

        $this->actingAs($this->admin())->get(route('admin.email.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('reglages.mot_de_passe')
                ->missing('reglages.motDePasse')
                ->where('reglages.motDePasseEnregistre', true));
    }

    public function test_un_mot_de_passe_laisse_vide_garde_l_actuel(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.email.update'), $this->reglagesComplets());

        $this->actingAs($admin)->put(route('admin.email.update'),
            ['mot_de_passe' => ''] + $this->reglagesComplets() + ['hote' => 'smtp.autre.com'])
            ->assertRedirect();

        $this->assertSame('secret-du-relais', ReglageEmail::firstOrFail()->mot_de_passe);
    }

    public function test_activer_sans_hote_est_refuse(): void
    {
        $this->actingAs($this->admin())->put(route('admin.email.update'), [
            'actif' => true,
            'hote' => '',
        ])->assertSessionHasErrors('hote');
    }

    public function test_des_reglages_inactifs_se_preparent_a_vide(): void
    {
        $this->actingAs($this->admin())->put(route('admin.email.update'), [
            'actif' => false,
            'hote' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse(ReglageEmail::firstOrFail()->actif);
    }

    public function test_un_chiffrement_inconnu_est_refuse(): void
    {
        $this->actingAs($this->admin())->put(route('admin.email.update'),
            ['chiffrement' => 'pigeon'] + $this->reglagesComplets())
            ->assertSessionHasErrors('chiffrement');
    }

    // ------------------------------------------------------------- essai

    public function test_l_essai_part_a_l_adresse_donnee(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())->post(route('admin.email.test'), [
            'destinataire' => 'alphonse@lamajestueuse.cm',
        ])->assertRedirect()->assertSessionHas('status');

        Mail::assertSent(EssaiEnvoi::class, fn ($mail) => $mail->hasTo('alphonse@lamajestueuse.cm'));
    }

    public function test_l_essai_reussi_est_horodate(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())->post(route('admin.email.test'), [
            'destinataire' => 'alphonse@lamajestueuse.cm',
        ]);

        $this->assertNotNull(ReglageEmail::firstOrFail()->teste_le);
    }

    public function test_une_adresse_invalide_est_refusee(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())->post(route('admin.email.test'), ['destinataire' => 'pas-une-adresse'])
            ->assertSessionHasErrors('destinataire');

        Mail::assertNothingSent();
    }

    public function test_un_employe_n_essaie_pas(): void
    {
        $this->actingAs(User::factory()->create())->post(route('admin.email.test'), [
            'destinataire' => 'alphonse@lamajestueuse.cm',
        ])->assertForbidden();
    }

    // ------------------------------------------------- prise en compte

    public function test_les_reglages_actifs_remplacent_la_configuration(): void
    {
        ReglageEmail::create($this->reglagesComplets());

        ReglageEmail::appliquer();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp-relay.brevo.com', config('mail.mailers.smtp.host'));
        $this->assertSame('info@lamajestueuse.com', config('mail.from.address'));
    }

    public function test_des_reglages_inactifs_laissent_le_serveur_decider(): void
    {
        config(['mail.mailers.smtp.host' => 'serveur-du-.env']);
        ReglageEmail::create(['actif' => false] + $this->reglagesComplets());

        ReglageEmail::appliquer();

        $this->assertSame('serveur-du-.env', config('mail.mailers.smtp.host'));
    }
}
