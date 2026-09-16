<?php

namespace Tests\Feature\Modules;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DirectoryModuleTest extends TestCase
{
    use RefreshDatabase;

    private function module(): Application
    {
        return Application::factory()->module('annuaire')->create(['name' => 'Annuaire']);
    }

    private function lecteur(): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module());

        return $user;
    }

    private function personnel(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Claire',
            'lastname' => 'NGONO',
            'matricule' => 'LM-7701',
            'email' => 'claire.ngono@lamajestueuse.cm',
            'phone' => '+237 699112204',
            'poste' => 'Secrétaire de direction',
            'entite' => 'GSBM',
            'status' => 'active',
        ], $overrides));
    }

    public function test_le_module_est_inaccessible_sans_la_tuile(): void
    {
        $this->module();

        $this->actingAs(User::factory()->create())->get(route('annuaire.index'))->assertForbidden();
    }

    public function test_laccueil_ne_liste_personne_avant_une_recherche(): void
    {
        $lecteur = $this->lecteur();
        $this->personnel();

        $this->actingAs($lecteur)->get(route('annuaire.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/annuaire/index')
                ->where('personnel', null)
                ->where('total', 2));
    }

    public function test_recherche_par_nom_matricule_email_et_telephone(): void
    {
        $lecteur = $this->lecteur();
        $this->personnel();

        foreach (['ngono', 'LM-7701', 'claire.ngono', '699112204'] as $terme) {
            $this->actingAs($lecteur)->get(route('annuaire.index', ['q' => $terme]))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('personnel.data', 1)
                    ->where('personnel.data.0.fullName', 'Claire NGONO'));
        }
    }

    public function test_seules_des_informations_non_sensibles_sont_transmises(): void
    {
        $lecteur = $this->lecteur();
        $this->personnel();

        $this->actingAs($lecteur)->get(route('annuaire.index', ['q' => 'ngono']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('personnel.data.0', fn (Assert $fiche) => $fiche
                    ->hasAll(['id', 'fullName', 'initials', 'avatarUrl', 'poste', 'entite', 'matricule', 'email', 'phone', 'sexe', 'instituts'])
                    // Rien d'autre ne doit sortir.
                    ->missingAll(['password', 'remember_token', 'role', 'status', 'locale', 'last_login_at', 'self_registered', 'approved_at'])));
    }

    public function test_les_comptes_non_actifs_sont_exclus(): void
    {
        $lecteur = $this->lecteur();
        $this->personnel(['status' => 'pending', 'matricule' => 'LM-7702', 'email' => 'a@b.cm']);
        $this->personnel(['status' => 'suspended', 'matricule' => 'LM-7703', 'email' => 'c@d.cm']);

        $this->actingAs($lecteur)->get(route('annuaire.index', ['q' => 'ngono']))
            ->assertInertia(fn (Assert $page) => $page->has('personnel.data', 0));
    }

    public function test_filtre_par_institut(): void
    {
        $lecteur = $this->lecteur();
        $gsbm = Application::factory()->create(['slug' => 'gsbm', 'name' => 'GSBM']);
        $ifpm = Application::factory()->create(['slug' => 'ifpm', 'name' => 'IFPM']);

        $this->personnel()->applications()->attach($gsbm);
        $this->personnel(['name' => 'Paul', 'lastname' => 'ESSOMBA', 'matricule' => 'LM-7704', 'email' => 'p@e.cm'])
            ->applications()->attach($ifpm);

        $this->actingAs($lecteur)->get(route('annuaire.index', ['institut' => 'gsbm']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('personnel.data', 1)
                ->where('personnel.data.0.fullName', 'Claire NGONO'));
    }

    public function test_filtre_par_poste(): void
    {
        $lecteur = $this->lecteur();
        $this->personnel();
        $this->personnel(['name' => 'Paul', 'lastname' => 'ESSOMBA', 'matricule' => 'LM-7705', 'email' => 'p2@e.cm', 'poste' => 'Enseignant']);

        $this->actingAs($lecteur)->get(route('annuaire.index', ['poste' => 'Enseignant']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('personnel.data', 1)
                ->where('personnel.data.0.fullName', 'Paul ESSOMBA'));
    }

    public function test_tri_et_ordre(): void
    {
        $lecteur = $this->lecteur();
        $this->personnel(['lastname' => 'ABENA', 'matricule' => 'LM-7801', 'email' => 'a1@x.cm']);
        $this->personnel(['lastname' => 'ZOA', 'matricule' => 'LM-7802', 'email' => 'z1@x.cm']);

        $this->actingAs($lecteur)->get(route('annuaire.index', ['q' => 'LM-78', 'tri' => 'nom', 'ordre' => 'asc']))
            ->assertInertia(fn (Assert $page) => $page->where('personnel.data.0.matricule', 'LM-7801'));

        $this->actingAs($lecteur)->get(route('annuaire.index', ['q' => 'LM-78', 'tri' => 'nom', 'ordre' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page->where('personnel.data.0.matricule', 'LM-7802'));
    }

    public function test_un_champ_de_tri_inconnu_retombe_sur_le_nom(): void
    {
        // Le tri vient de l'URL : il ne doit jamais servir de colonne libre.
        $lecteur = $this->lecteur();
        $this->personnel();

        $this->actingAs($lecteur)
            ->get(route('annuaire.index', ['q' => 'ngono', 'tri' => 'password']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filters.tri', 'nom'));
    }

    public function test_ouvrir_le_module_mene_a_lannuaire(): void
    {
        $module = $this->module();
        $user = User::factory()->create();
        $user->applications()->attach($module);

        $this->actingAs($user)->get(route('applications.open', $module))
            ->assertRedirect(route('annuaire.index'));
    }
}
