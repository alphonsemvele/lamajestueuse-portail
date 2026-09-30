<?php

namespace Tests\Feature\Admin;

use App\Mail\Badge\BadgePret;
use App\Mail\Badge\BadgeRefuse;
use App\Mail\Badge\DemandeEnregistree;
use App\Mail\Compte\CompteValide;
use App\Mail\Compte\DemandeRefusee;
use App\Models\Application;
use App\Models\DemandeBadge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Les courriels partent bien depuis les procedures reelles — et un relais
 * muet ne fait jamais echouer la procedure elle-meme.
 */
class CourrielsDesProceduresTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_validation_d_un_compte_previent_la_personne(): void
    {
        Mail::fake();

        $demandeur = User::factory()->create(['status' => 'pending', 'email' => 'claire@lamajestueuse.cm']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.approve', $demandeur))->assertRedirect();

        Mail::assertSent(CompteValide::class, fn ($mail) => $mail->hasTo('claire@lamajestueuse.cm'));
    }

    public function test_le_refus_previent_aussi(): void
    {
        Mail::fake();

        $demandeur = User::factory()->create(['status' => 'pending', 'email' => 'claire@lamajestueuse.cm']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.reject', $demandeur));

        Mail::assertSent(DemandeRefusee::class);
    }

    /** Sans adresse, rien ne part — et la procedure aboutit quand meme. */
    public function test_un_compte_sans_adresse_n_empeche_pas_la_validation(): void
    {
        Mail::fake();

        $demandeur = User::factory()->create(['status' => 'pending', 'email' => null]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.approve', $demandeur))->assertRedirect();

        Mail::assertNothingSent();
        $this->assertSame('active', $demandeur->refresh()->status);
    }

    public function test_une_demande_de_badge_est_accusee_reception(): void
    {
        Mail::fake();

        $module = Application::factory()->module('badges')->create(['name' => 'Badges']);
        $institut = Application::factory()->create(['name' => 'IUM']);
        $employe = User::factory()->create(['email' => 'claire@lamajestueuse.cm']);
        $employe->applications()->attach($institut);

        $this->actingAs($employe)->post(route('badges.store'), [
            'nom_affiche' => 'Claire NKOA',
            'motif' => 'premiere',
            'application_id' => $institut->id,
        ])->assertRedirect();

        Mail::assertSent(DemandeEnregistree::class, fn ($mail) => $mail->hasTo('claire@lamajestueuse.cm'));
        $this->assertNotNull($module->id);
    }

    public function test_le_demandeur_est_prevenu_quand_son_badge_est_pret(): void
    {
        Mail::fake();

        $module = Application::factory()->module('badges')->create(['name' => 'Badges']);
        $guichet = User::factory()->create();
        $guichet->applications()->attach($module, ['role_in_app' => 'accueil', 'roles' => json_encode(['accueil'])]);

        $employe = User::factory()->create(['email' => 'claire@lamajestueuse.cm']);
        $demande = DemandeBadge::create([
            'numero' => 'BDG-000001', 'user_id' => $employe->id, 'nom_affiche' => 'Claire NKOA',
            'modele' => 'classique', 'motif' => 'premiere', 'statut' => 'approuvee',
        ]);

        $this->actingAs($guichet)->post(route('badges.traiter', $demande), ['statut' => 'imprimee']);

        Mail::assertSent(BadgePret::class, fn ($mail) => $mail->hasTo('claire@lamajestueuse.cm'));
    }

    public function test_un_refus_de_badge_porte_son_motif(): void
    {
        Mail::fake();

        $module = Application::factory()->module('badges')->create(['name' => 'Badges']);
        $guichet = User::factory()->create();
        $guichet->applications()->attach($module, ['role_in_app' => 'accueil', 'roles' => json_encode(['accueil'])]);

        $employe = User::factory()->create(['email' => 'claire@lamajestueuse.cm']);
        $demande = DemandeBadge::create([
            'numero' => 'BDG-000001', 'user_id' => $employe->id, 'nom_affiche' => 'Claire NKOA',
            'modele' => 'classique', 'motif' => 'premiere', 'statut' => 'en_attente',
        ]);

        $this->actingAs($guichet)->post(route('badges.traiter', $demande), [
            'statut' => 'refusee',
            'motif_refus' => 'Photo trop sombre.',
        ]);

        Mail::assertSent(BadgeRefuse::class, fn ($mail) => $mail->motif === 'Photo trop sombre.');
    }

    // ----------------------------------------------------------- renvoi

    /** Le renvoi réexpédie le message qui correspond à l'état du compte. */
    public function test_le_renvoi_suit_l_etat_du_compte(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $enAttente = User::factory()->create(['status' => 'pending', 'email' => 'a@lamajestueuse.cm']);
        $this->actingAs($admin)->post(route('admin.users.renvoyer', $enAttente))->assertRedirect();
        Mail::assertSent(\App\Mail\Compte\InscriptionRecue::class);

        $valide = User::factory()->create([
            'status' => 'active', 'email' => 'b@lamajestueuse.cm', 'self_registered' => true,
        ]);
        $this->actingAs($admin)->post(route('admin.users.renvoyer', $valide));
        Mail::assertSent(CompteValide::class);

        $refuse = User::factory()->create(['status' => 'suspended', 'email' => 'c@lamajestueuse.cm']);
        $this->actingAs($admin)->post(route('admin.users.renvoyer', $refuse));
        Mail::assertSent(DemandeRefusee::class);
    }

    /** Un compte ouvert par les RH n'a pas été demandé par son titulaire. */
    public function test_le_renvoi_distingue_un_compte_ouvert_par_les_rh(): void
    {
        Mail::fake();

        $cree = User::factory()->create([
            'status' => 'active', 'email' => 'd@lamajestueuse.cm', 'self_registered' => false,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.renvoyer', $cree))->assertRedirect();

        Mail::assertSent(\App\Mail\Compte\CompteCree::class);
        Mail::assertNotSent(CompteValide::class);
    }

    public function test_on_ne_renvoie_rien_a_un_compte_sans_adresse(): void
    {
        Mail::fake();

        $sansAdresse = User::factory()->create(['status' => 'active', 'email' => null]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.renvoyer', $sansAdresse))
            ->assertSessionHasErrors('courriel');

        Mail::assertNothingSent();
    }

    public function test_le_renvoi_d_un_badge_suit_son_etat(): void
    {
        Mail::fake();

        $module = Application::factory()->module('badges')->create(['name' => 'Badges']);
        $guichet = User::factory()->create();
        $guichet->applications()->attach($module, ['role_in_app' => 'accueil', 'roles' => json_encode(['accueil'])]);

        $employe = User::factory()->create(['email' => 'claire@lamajestueuse.cm']);
        $demande = DemandeBadge::create([
            'numero' => 'BDG-000001', 'user_id' => $employe->id, 'nom_affiche' => 'Claire NKOA',
            'modele' => 'classique', 'motif' => 'premiere', 'statut' => 'remise',
        ]);

        $this->actingAs($guichet)->post(route('badges.renvoyer', $demande))->assertRedirect();

        Mail::assertSent(BadgePret::class, fn ($mail) => $mail->hasTo('claire@lamajestueuse.cm'));
    }

    public function test_un_employe_ne_renvoie_pas_les_messages_des_autres(): void
    {
        Mail::fake();

        Application::factory()->module('badges')->create(['name' => 'Badges']);
        $demande = DemandeBadge::create([
            'numero' => 'BDG-000001', 'user_id' => User::factory()->create()->id, 'nom_affiche' => 'Claire NKOA',
            'modele' => 'classique', 'motif' => 'premiere', 'statut' => 'remise',
        ]);

        $this->actingAs(User::factory()->create())->post(route('badges.renvoyer', $demande))->assertForbidden();

        Mail::assertNothingSent();
    }

    /** Une etape intermediaire ne derange personne. */
    public function test_l_approbation_d_un_badge_n_envoie_rien(): void
    {
        Mail::fake();

        $module = Application::factory()->module('badges')->create(['name' => 'Badges']);
        $guichet = User::factory()->create();
        $guichet->applications()->attach($module, ['role_in_app' => 'accueil', 'roles' => json_encode(['accueil'])]);

        $demande = DemandeBadge::create([
            'numero' => 'BDG-000001', 'user_id' => User::factory()->create()->id, 'nom_affiche' => 'Claire NKOA',
            'modele' => 'classique', 'motif' => 'premiere', 'statut' => 'en_attente',
        ]);

        $this->actingAs($guichet)->post(route('badges.traiter', $demande), ['statut' => 'approuvee']);

        Mail::assertNothingSent();
    }
}
