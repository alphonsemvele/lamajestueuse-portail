<?php

namespace Tests\Feature\Modules;

use App\Models\Agent;
use App\Models\Ajustement;
use App\Models\Application;
use App\Models\Bulletin;
use App\Models\CategorieRh;
use App\Models\Contrat;
use App\Models\Echelon;
use App\Models\Employeur;
use App\Models\User;
use App\Services\PaieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Supprimer une paie preparee : un bulletin, une selection, ou tout un mois.
 * Rien n'est verrouille — un mois rate doit pouvoir etre refait.
 */
class SuppressionPaieTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    private Employeur $employeur;

    private Employeur $autre;

    private Echelon $echelon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        Application::factory()->module('bulletins')->create(['name' => 'Mon bulletin de paie']);
        $this->employeur = Employeur::create(['nom' => 'Institut Universitaire', 'sigle' => 'IUM', 'actif' => true]);
        $this->autre = Employeur::create(['nom' => 'Institut de Formation', 'sigle' => 'IFPM', 'actif' => true]);

        $categorie = CategorieRh::create(['libelle' => 'Catégorie 1', 'actif' => true]);
        $this->echelon = Echelon::create([
            'categorie_rh_id' => $categorie->id,
            'numero' => 1, 'libelle' => 'A', 'salaire' => 200000, 'actif' => true,
        ]);
    }

    private function gestionnaire(?Employeur $employeur = null): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $user->employeursRh()->attach($employeur ?? $this->employeur);

        return $user;
    }

    private function contrat(?Employeur $employeur = null): Contrat
    {
        return Contrat::create([
            'agent_id' => Agent::create(['user_id' => User::factory()->create()->id])->id,
            'employeur_id' => ($employeur ?? $this->employeur)->id,
            'type' => 'cdi', 'poste' => 'Enseignant', 'date_debut' => '2026-01-01',
            'quotite' => 100, 'echelon_id' => $this->echelon->id, 'statut' => 'actif',
        ]);
    }

    private function bulletin(string $statut = 'brouillon', ?Employeur $employeur = null): Bulletin
    {
        $bulletin = app(PaieService::class)->genererMois($employeur ?? $this->employeur, 9, 2026);
        unset($bulletin);

        $dernier = Bulletin::latest('id')->firstOrFail();
        $dernier->update(['statut' => $statut]);

        return $dernier;
    }

    public function test_un_brouillon_se_supprime(): void
    {
        $this->contrat();
        $bulletin = $this->bulletin();

        $this->actingAs($this->gestionnaire())
            ->delete(route('personnel.paie.bulletin.destroy', $bulletin))
            ->assertRedirect(route('personnel.paie.index', ['mois' => 9, 'annee' => 2026]));

        $this->assertDatabaseMissing('bulletins', ['id' => $bulletin->id]);
    }

    /** Meme paye : le mot de l'utilisateur fait foi, rien n'est verrouille. */
    public function test_un_bulletin_paye_se_supprime_aussi(): void
    {
        $this->contrat();
        $bulletin = $this->bulletin('paye');

        $this->actingAs($this->gestionnaire())
            ->delete(route('personnel.paie.bulletin.destroy', $bulletin))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('bulletins', ['id' => $bulletin->id]);
    }

    public function test_la_suppression_ne_renvoie_pas_sur_la_page_disparue(): void
    {
        $this->contrat();
        $bulletin = $this->bulletin();

        $this->actingAs($this->gestionnaire())
            ->from(route('personnel.paie.bulletin', $bulletin))
            ->delete(route('personnel.paie.bulletin.destroy', $bulletin))
            ->assertRedirect(route('personnel.paie.index', ['mois' => 9, 'annee' => 2026]));
    }

    public function test_les_ajustements_du_mois_survivent_pour_la_regeneration(): void
    {
        $contrat = $this->contrat();
        $ajustement = Ajustement::create([
            'contrat_id' => $contrat->id, 'mois' => 9, 'annee' => 2026,
            'type' => 'bonus', 'mode' => 'fixe', 'montant' => 15000, 'libelle' => 'Prime',
        ]);
        $bulletin = $this->bulletin();

        $this->actingAs($this->gestionnaire())
            ->delete(route('personnel.paie.bulletin.destroy', $bulletin));

        $this->assertDatabaseHas('ajustements', ['id' => $ajustement->id]);

        // La preparation suivante le reprend.
        app(PaieService::class)->genererMois($this->employeur, 9, 2026);
        $this->assertSame(215000.0, (float) Bulletin::latest('id')->firstOrFail()->salaire_net);
    }

    public function test_une_selection_se_supprime_en_lot(): void
    {
        $this->contrat();
        $this->contrat();
        app(PaieService::class)->genererMois($this->employeur, 9, 2026);

        $ids = Bulletin::pluck('id')->all();
        $this->assertCount(2, $ids);

        $this->actingAs($this->gestionnaire())
            ->post(route('personnel.paie.lot'), ['action' => 'supprimer', 'bulletins' => $ids])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Bulletin::count());
    }

    public function test_vider_la_periode_epargne_les_payes_sans_accord_explicite(): void
    {
        $this->contrat();
        $this->contrat();
        app(PaieService::class)->genererMois($this->employeur, 9, 2026);
        $paye = Bulletin::latest('id')->firstOrFail();
        $paye->update(['statut' => 'paye']);

        $this->actingAs($this->gestionnaire())
            ->delete(route('personnel.paie.vider'), ['mois' => 9, 'annee' => 2026])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Bulletin::count());
        $this->assertDatabaseHas('bulletins', ['id' => $paye->id]);
    }

    public function test_vider_la_periode_emporte_les_payes_quand_on_le_demande(): void
    {
        $this->contrat();
        app(PaieService::class)->genererMois($this->employeur, 9, 2026);
        Bulletin::query()->update(['statut' => 'paye']);

        $this->actingAs($this->gestionnaire())
            ->delete(route('personnel.paie.vider'), ['mois' => 9, 'annee' => 2026, 'inclure_payes' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Bulletin::count());
    }

    public function test_vider_une_periode_ne_touche_pas_aux_autres_mois(): void
    {
        $this->contrat();
        app(PaieService::class)->genererMois($this->employeur, 9, 2026);
        app(PaieService::class)->genererMois($this->employeur, 10, 2026);

        $this->actingAs($this->gestionnaire())
            ->delete(route('personnel.paie.vider'), ['mois' => 9, 'annee' => 2026]);

        $this->assertSame(1, Bulletin::count());
        $this->assertSame(10, Bulletin::firstOrFail()->mois);
    }

    public function test_vider_une_periode_sans_bulletin_le_dit(): void
    {
        $this->actingAs($this->gestionnaire())
            ->delete(route('personnel.paie.vider'), ['mois' => 9, 'annee' => 2026])
            ->assertSessionHasErrors('paie');
    }

    public function test_un_gestionnaire_ne_supprime_pas_hors_de_son_perimetre(): void
    {
        $this->contrat($this->autre);
        $bulletin = $this->bulletin('brouillon', $this->autre);

        $this->actingAs($this->gestionnaire())
            ->delete(route('personnel.paie.bulletin.destroy', $bulletin))
            ->assertForbidden();

        $this->assertDatabaseHas('bulletins', ['id' => $bulletin->id]);
    }

    public function test_vider_la_periode_s_arrete_au_perimetre(): void
    {
        $this->contrat();
        $this->contrat($this->autre);
        app(PaieService::class)->genererMois($this->employeur, 9, 2026);
        app(PaieService::class)->genererMois($this->autre, 9, 2026);

        $this->actingAs($this->gestionnaire())
            ->delete(route('personnel.paie.vider'), ['mois' => 9, 'annee' => 2026]);

        // Celui de l'autre institut reste.
        $this->assertSame(1, Bulletin::count());
        $this->assertSame($this->autre->id, Bulletin::firstOrFail()->employeur_id);
    }

    public function test_un_lecteur_ne_supprime_rien(): void
    {
        $this->contrat();
        $bulletin = $this->bulletin();

        $lecteur = User::factory()->create();
        $lecteur->applications()->attach($this->module, ['role_in_app' => 'lecteur', 'roles' => json_encode(['lecteur'])]);

        $this->actingAs($lecteur)
            ->delete(route('personnel.paie.bulletin.destroy', $bulletin))
            ->assertForbidden();

        $this->assertDatabaseHas('bulletins', ['id' => $bulletin->id]);
    }

    public function test_un_bulletin_supprime_disparait_de_mes_bulletins(): void
    {
        $contrat = $this->contrat();
        $bulletin = $this->bulletin('valide');

        $employe = $contrat->agent->user;
        $this->actingAs($employe)
            ->get(route('mes-bulletins.pdf', $bulletin))->assertOk();

        $this->actingAs($this->gestionnaire())
            ->delete(route('personnel.paie.bulletin.destroy', $bulletin));

        $this->actingAs($employe)
            ->get(route('mes-bulletins.pdf', $bulletin))->assertNotFound();
    }
}
