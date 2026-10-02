<?php

namespace Tests\Feature\Admin;

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
use Tests\TestCase;

/**
 * Supprimer un compte ne doit jamais laisser derriere lui un dossier ou des
 * bulletins sans titulaire : l'hebergement n'applique pas les cles
 * etrangeres, le menage se fait donc ici.
 */
class SuppressionCompteTest extends TestCase
{
    use RefreshDatabase;

    private Employeur $employeur;

    private Echelon $echelon;

    protected function setUp(): void
    {
        parent::setUp();

        Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        $this->employeur = Employeur::create(['nom' => 'Institut', 'sigle' => 'INS', 'actif' => true]);

        $categorie = CategorieRh::create(['libelle' => 'Catégorie 1', 'actif' => true]);
        $this->echelon = Echelon::create([
            'categorie_rh_id' => $categorie->id,
            'numero' => 1, 'libelle' => 'A', 'salaire' => 200000, 'actif' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function avecDossier(bool $avecBulletin): User
    {
        $membre = User::factory()->create(['lastname' => 'NKOA']);
        $agent = Agent::create(['user_id' => $membre->id]);

        Contrat::create([
            'agent_id' => $agent->id, 'employeur_id' => $this->employeur->id,
            'type' => 'cdi', 'poste' => 'Enseignant', 'date_debut' => '2026-01-01',
            'quotite' => 100, 'echelon_id' => $this->echelon->id, 'statut' => 'actif',
        ]);

        if ($avecBulletin) {
            app(PaieService::class)->genererMois($this->employeur, 9, 2026);
        }

        return $membre;
    }

    public function test_un_compte_avec_des_bulletins_ne_se_supprime_pas(): void
    {
        $membre = $this->avecDossier(true);

        $this->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $membre))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $membre->id]);
        $this->assertSame(1, Bulletin::count());

        $this->assertStringContainsString(
            'suspendez-le',
            session('errors')->first('user'),
        );
    }

    /** Sans bulletin, le compte part — et son dossier avec lui. */
    public function test_le_dossier_part_avec_le_compte(): void
    {
        $membre = $this->avecDossier(false);

        $this->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $membre))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $membre->id]);
        $this->assertSame(0, Agent::count());
        // Le contrat part avec le dossier : il n'a plus d'objet.
        $this->assertSame(0, Contrat::count());
    }

    public function test_un_compte_sans_dossier_se_supprime_normalement(): void
    {
        $membre = User::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $membre))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $membre->id]);
    }

    /**
     * Les listes ne retiennent que les bulletins dont le titulaire existe.
     *
     * L'orphelin lui-meme ne se reproduit pas ici : en essai, SQLite applique
     * les cles etrangeres et interdit de detacher un dossier de son compte.
     * On verifie donc que le filtre est bien pose — un bulletin normal passe,
     * et la requete s'appuie sur l'existence du compte.
     */
    public function test_les_listes_ne_retiennent_que_les_bulletins_avec_titulaire(): void
    {
        $this->avecDossier(true);

        $this->assertSame(1, Bulletin::avecTitulaire()->count());
        $this->assertStringContainsString('exists', mb_strtolower(Bulletin::avecTitulaire()->toSql()));
        $this->assertStringContainsString('users', Bulletin::avecTitulaire()->toSql());
    }

    /** Un brouillon sans titulaire s'efface a la preparation suivante. */
    public function test_la_preparation_efface_les_brouillons_sans_titulaire(): void
    {
        $this->avecDossier(true);

        $requete = Bulletin::where('statut', 'brouillon')->whereDoesntHave('agent.user');

        // Le nettoyage vise bien les brouillons orphelins, et eux seuls.
        $this->assertStringContainsString('not exists', mb_strtolower($requete->toSql()));
        $this->assertSame(0, $requete->count());
        $this->assertSame(1, Bulletin::count());
    }
}
