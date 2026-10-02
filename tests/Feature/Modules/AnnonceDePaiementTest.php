<?php

namespace Tests\Feature\Modules;

use App\Mail\Paie\BulletinDisponible;
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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * L'annonce qui suit la mise en paiement.
 *
 * Elle ne porte aucun montant : la remuneration reste derriere la connexion,
 * le message ne fait que prevenir.
 */
class AnnonceDePaiementTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    private Employeur $employeur;

    private Echelon $echelon;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->module = Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        $this->employeur = Employeur::create([
            'nom' => 'Institut Universitaire', 'sigle' => 'IUM', 'actif' => true,
        ]);

        $categorie = CategorieRh::create(['libelle' => 'Catégorie 1', 'actif' => true]);
        $this->echelon = Echelon::create([
            'categorie_rh_id' => $categorie->id,
            'numero' => 1, 'libelle' => 'A', 'salaire' => 200000, 'actif' => true,
        ]);
    }

    private function gestionnaire(): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $user->employeursRh()->attach($this->employeur);

        return $user;
    }

    private function bulletin(string $statut = 'brouillon'): Bulletin
    {
        Contrat::create([
            'agent_id' => Agent::create(['user_id' => User::factory()->create()->id])->id,
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi', 'poste' => 'Enseignant', 'date_debut' => '2026-01-01',
            'quotite' => 100, 'echelon_id' => $this->echelon->id, 'statut' => 'actif',
        ]);

        app(PaieService::class)->genererMois($this->employeur, 9, 2026);

        $bulletin = Bulletin::latest('id')->firstOrFail();

        if ($statut !== 'brouillon') {
            $bulletin->update(['statut' => $statut]);
        }

        return $bulletin->fresh(['agent.user', 'employeur']);
    }

    public function test_le_paiement_previent_le_salarie(): void
    {
        $bulletin = $this->bulletin();
        $titulaire = $bulletin->agent->user;

        $this->actingAs($this->gestionnaire())
            ->post(route('personnel.paie.payer', $bulletin))
            ->assertSessionHasNoErrors();

        Mail::assertSent(BulletinDisponible::class, fn ($mail) => $mail->hasTo($titulaire->email));
    }

    public function test_le_message_ne_porte_aucun_montant(): void
    {
        $bulletin = $this->bulletin();

        $this->actingAs($this->gestionnaire())->post(route('personnel.paie.payer', $bulletin));

        Mail::assertSent(BulletinDisponible::class, function ($mail) {
            $rendu = $mail->render();

            // Ni le net, ni la base, ni le total des indemnités.
            $this->assertStringNotContainsString('200 000', $rendu);
            $this->assertStringNotContainsString('200000', $rendu);

            return true;
        });
    }

    public function test_le_message_nomme_la_periode_et_l_employeur(): void
    {
        $bulletin = $this->bulletin();

        $this->actingAs($this->gestionnaire())->post(route('personnel.paie.payer', $bulletin));

        Mail::assertSent(BulletinDisponible::class, function ($mail) {
            $this->assertSame('septembre 2026', $mail->periode);
            $this->assertSame('Institut Universitaire', $mail->employeur);
            $this->assertStringContainsString('septembre 2026', $mail->envelope()->subject);

            return true;
        });
    }

    public function test_la_validation_seule_n_annonce_rien(): void
    {
        $bulletin = $this->bulletin();

        $this->actingAs($this->gestionnaire())->post(route('personnel.paie.valider', $bulletin));

        Mail::assertNothingSent();
    }

    public function test_le_traitement_en_lot_previent_chaque_salarie(): void
    {
        $premier = $this->bulletin();
        $second = $this->bulletin();

        $this->actingAs($this->gestionnaire())->post(route('personnel.paie.lot'), [
            'action' => 'payer',
            'bulletins' => [$premier->id, $second->id],
        ])->assertSessionHasNoErrors();

        Mail::assertSent(BulletinDisponible::class, 2);
    }

    public function test_un_paiement_refuse_n_annonce_rien(): void
    {
        $bulletin = $this->bulletin('paye');

        $this->actingAs($this->gestionnaire())->post(route('personnel.paie.payer', $bulletin))
            ->assertSessionHasErrors('paie');

        Mail::assertNothingSent();
    }

    public function test_le_message_se_renvoie(): void
    {
        $bulletin = $this->bulletin('paye');

        $this->actingAs($this->gestionnaire())
            ->post(route('personnel.paie.relancer', $bulletin))
            ->assertSessionHasNoErrors();

        Mail::assertSent(BulletinDisponible::class, 1);
    }

    public function test_on_ne_renvoie_rien_pour_un_bulletin_non_paye(): void
    {
        $bulletin = $this->bulletin('valide');

        $this->actingAs($this->gestionnaire())
            ->post(route('personnel.paie.relancer', $bulletin))
            ->assertSessionHasErrors('paie');

        Mail::assertNothingSent();
    }

    public function test_un_lecteur_ne_renvoie_rien(): void
    {
        $bulletin = $this->bulletin('paye');

        $lecteur = User::factory()->create();
        $lecteur->applications()->attach($this->module, ['role_in_app' => 'lecteur', 'roles' => json_encode(['lecteur'])]);

        $this->actingAs($lecteur)
            ->post(route('personnel.paie.relancer', $bulletin))
            ->assertForbidden();
    }

    /** Une panne d'envoi ne doit pas defaire le paiement. */
    public function test_une_panne_d_envoi_ne_fait_pas_echouer_le_paiement(): void
    {
        $bulletin = $this->bulletin();

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('relais injoignable'));

        $this->actingAs($this->gestionnaire())
            ->post(route('personnel.paie.payer', $bulletin))
            ->assertSessionHasNoErrors();

        $this->assertSame('paye', $bulletin->fresh()->statut);
    }

    public function test_le_catalogue_des_modeles_porte_le_volet_paie(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->get(route('admin.email.modeles'))
            ->assertOk()
            ->assertSee('Bulletin de paie disponible');
    }

    public function test_le_modele_sait_se_construire_en_exemple(): void
    {
        $exemple = BulletinDisponible::exemple();

        $this->assertStringContainsString('septembre 2026', $exemple->envelope()->subject);
        $this->assertStringContainsString('Claire NKOA', $exemple->render());
    }
}
