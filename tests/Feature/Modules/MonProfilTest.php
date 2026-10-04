<?php

namespace Tests\Feature\Modules;

use App\Models\Agent;
use App\Models\Application;
use App\Models\CategorieRh;
use App\Models\Contrat;
use App\Models\DemandeBadge;
use App\Models\Diplome;
use App\Models\DocumentAgent;
use App\Models\Echelon;
use App\Models\Employeur;
use App\Models\Indemnite;
use App\Models\ProfilSalaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Mon profil : ce que chacun voit de lui-meme, et ce qu'il peut soumettre
 * au service du personnel.
 */
class MonProfilTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    private Application $institut;

    private Employeur $employeur;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->module = Application::factory()->module('profil')->create(['name' => 'Mon profil']);
        Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);

        $this->institut = Application::factory()->create(['name' => 'IUM']);
        $this->employeur = Employeur::create([
            'nom' => 'Institut Universitaire', 'sigle' => 'IUM',
            'application_id' => $this->institut->id, 'actif' => true,
        ]);
    }

    private function moi(): User
    {
        $moi = User::factory()->create(['lastname' => 'NKOA', 'status' => 'active']);
        $moi->applications()->attach($this->institut, ['poste' => 'Chargée de scolarité']);
        // Une tuile ne parait que si elle est attribuee, celle-ci comprise.
        $moi->applications()->attach($this->module);

        return $moi;
    }

    // ------------------------------------------------------------ l'acces

    public function test_le_module_est_ouvert_a_tout_le_personnel(): void
    {
        // Sans meme la tuile, le module s'ouvre : il concerne chacun.
        $sansTuile = User::factory()->create(['status' => 'active']);

        $this->actingAs($sansTuile)->get(route('profil.index'))->assertOk();
    }

    public function test_un_visiteur_ne_voit_pas_mon_profil(): void
    {
        $this->get(route('profil.index'))->assertRedirect(route('login'));
    }

    public function test_la_tuile_porte_ma_photo(): void
    {
        $moi = $this->moi();
        $moi->update(['avatar' => 'utilisateurs/photos/moi.png']);

        $this->actingAs($moi)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($moi) {
                $tuile = collect($page->toArray()['props']['apps'])->firstWhere('moduleKey', 'profil');

                $this->assertNotNull($tuile, 'La tuile « Mon profil » devrait être posée.');
                $this->assertSame($moi->avatarUrl(), $tuile['photoDeProfil']);
            });
    }

    /** Chacun voit son badge depuis son profil, sans passer par le module. */
    public function test_mon_profil_montre_mon_badge(): void
    {
        Application::factory()->module('badges')->create(['name' => 'Badges']);
        $moi = $this->moi();

        $demande = DemandeBadge::create([
            'numero' => 'BDG-000001', 'user_id' => $moi->id, 'nom_affiche' => 'Claire NKOA',
            'application_id' => $this->institut->id, 'modele' => 'classique',
            'motif' => 'premiere', 'statut' => 'approuvee',
        ]);

        $this->actingAs($moi)->get(route('profil.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('badge.demande.numero', $demande->numero)
                ->where('badge.demande.statut', 'approuvee')
                ->where('badge.demande.institut.name', 'IUM')
                ->where('badge.validite', (int) config('badges.validite_annees'))
                ->where('badge.mention', config('badges.mention'))
                ->where('badge.moduleOuvert', true));
    }

    /** Sans demande, il n'y a rien a montrer — et c'est dit. */
    public function test_sans_demande_aucun_badge_n_est_montre(): void
    {
        $this->actingAs($this->moi())->get(route('profil.index'))
            ->assertInertia(fn (Assert $page) => $page->where('badge.demande', null));
    }

    /** Le message de validation renvoie droit sur l'onglet du badge. */
    public function test_un_lien_peut_ouvrir_l_onglet_du_badge(): void
    {
        $this->actingAs($this->moi())->get(route('profil.index', ['onglet' => 'badge']))
            ->assertInertia(fn (Assert $page) => $page->where('ongletInitial', 'badge'));
    }

    /** Module retiré : on montre le badge, mais sans proposer de le demander. */
    public function test_sans_module_badges_on_ne_propose_pas_la_demande(): void
    {
        $this->actingAs($this->moi())->get(route('profil.index'))
            ->assertInertia(fn (Assert $page) => $page->where('badge.moduleOuvert', false));
    }

    public function test_la_tuile_s_affiche_en_premier(): void
    {
        $this->actingAs($this->moi())->get(route('dashboard'))
            ->assertInertia(function (Assert $page) {
                $apps = collect($page->toArray()['props']['apps']);

                $this->assertSame('profil', $apps->first()['moduleKey']);
            });
    }

    // ------------------------------------------------------ ce que je vois

    public function test_je_vois_mon_identite_et_mes_instituts(): void
    {
        $moi = $this->moi();

        $this->actingAs($moi)->get(route('profil.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('identite.matricule', $moi->matricule)
                ->has('instituts', 1)
                ->where('instituts.0.nom', 'IUM')
                ->where('instituts.0.poste', 'Chargée de scolarité'));
    }

    public function test_je_vois_mon_profil_de_salaire(): void
    {
        $moi = $this->moi();
        $categorie = CategorieRh::create(['libelle' => 'Catégorie 7', 'actif' => true]);
        $echelon = Echelon::create([
            'categorie_rh_id' => $categorie->id,
            'numero' => 1, 'libelle' => 'A', 'salaire' => 200000, 'actif' => true,
        ]);

        $profil = ProfilSalaire::create(['nom' => 'Comptable', 'echelon_id' => $echelon->id, 'actif' => true]);
        $profil->indemnites()->attach(
            Indemnite::create(['libelle' => 'Transport', 'imposable' => true, 'actif' => true])->id,
            ['type_calcul' => 'fixe', 'valeur' => 30000],
        );

        Contrat::create([
            'agent_id' => Agent::create(['user_id' => $moi->id])->id,
            'employeur_id' => $this->employeur->id,
            'type' => 'cdi', 'poste' => 'Comptable', 'date_debut' => '2026-01-01',
            'quotite' => 100, 'profil_salaire_id' => $profil->id, 'echelon_id' => $echelon->id,
            'statut' => 'actif',
        ]);

        $this->actingAs($moi)->get(route('profil.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('remuneration.profil', 'Comptable')
                ->where('remuneration.categorie', 'Catégorie 7')
                ->where('remuneration.echelon', 'A')
                ->where('remuneration.salaireBase', 200000)
                ->has('remuneration.indemnites', 1)
                ->has('affectations', 1));
    }

    public function test_sans_contrat_la_remuneration_est_vide(): void
    {
        $this->actingAs($this->moi())->get(route('profil.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('remuneration', null)
                ->where('dossier.ouvert', false));
    }

    public function test_je_ne_vois_que_mes_propres_pieces(): void
    {
        $moi = $this->moi();
        $autre = Agent::create(['user_id' => User::factory()->create()->id]);
        $autre->diplomes()->create(['intitule' => 'Doctorat du voisin']);

        $this->actingAs($moi)->get(route('profil.index'))
            ->assertInertia(fn (Assert $page) => $page->has('diplomes', 0));
    }

    // ------------------------------------------- ce que je corrige moi-meme

    public function test_je_corrige_mes_informations(): void
    {
        $moi = $this->moi();

        $this->actingAs($moi)->post(route('profil.mettre-a-jour'), [
            'name' => 'Célestin',
            'lastname' => 'NSOE',
            'phone' => '680646122',
            'email' => 'celestin@lamajestueuse.com',
            'lieu_naissance' => 'Yaoundé',
            'situation_familiale' => 'marie',
            'enfants' => 2,
            'adresse' => 'Bastos, Yaoundé',
            'urgence_nom' => 'Marie NSOE',
            'urgence_telephone' => '690000000',
        ])->assertSessionHasNoErrors();

        $moi->refresh();

        $this->assertSame('Célestin', $moi->name);
        $this->assertSame('celestin@lamajestueuse.com', $moi->email);
        $this->assertSame('680646122', $moi->phone);

        // Le dossier nait a la premiere correction s'il n'existait pas.
        $this->assertSame('Yaoundé', $moi->agent->lieu_naissance);
        $this->assertSame('marie', $moi->agent->situation_familiale);
        $this->assertSame(2, (int) $moi->agent->enfants);
        $this->assertSame('Marie NSOE', $moi->agent->urgence_nom);
    }

    public function test_je_change_ma_photo_de_profil(): void
    {
        Storage::fake('public');
        $moi = $this->moi();

        $this->actingAs($moi)->post(route('profil.mettre-a-jour'), [
            'name' => $moi->name,
            'photo' => UploadedFile::fake()->image('moi.jpg', 300, 300),
        ])->assertSessionHasNoErrors();

        $moi->refresh();

        $this->assertNotNull($moi->avatar);
        Storage::disk('public')->assertExists($moi->avatar);
    }

    public function test_un_envoi_sans_photo_garde_celle_en_place(): void
    {
        $moi = $this->moi();
        $moi->update(['avatar' => 'utilisateurs/photos/avant.png']);

        $this->actingAs($moi)->post(route('profil.mettre-a-jour'), ['name' => 'Célestin']);

        $this->assertSame('utilisateurs/photos/avant.png', $moi->fresh()->avatar);
    }

    /** Le matricule et le poste s'attribuent : ils ne se declarent pas. */
    public function test_je_ne_change_ni_mon_matricule_ni_mon_poste(): void
    {
        $moi = $this->moi();
        $matricule = $moi->matricule;

        $this->actingAs($moi)->post(route('profil.mettre-a-jour'), [
            'name' => $moi->name,
            'matricule' => 'LM-269999',
            'poste' => 'Directeur général',
        ])->assertSessionHasNoErrors();

        $moi->refresh();

        $this->assertSame($matricule, $moi->matricule);
        $this->assertNotSame('Directeur général', $moi->poste);
    }

    public function test_je_ne_prends_pas_l_adresse_d_un_autre(): void
    {
        $voisin = User::factory()->create(['email' => 'occupee@lamajestueuse.com']);
        $moi = $this->moi();

        $this->actingAs($moi)->post(route('profil.mettre-a-jour'), [
            'name' => $moi->name,
            'email' => 'occupee@lamajestueuse.com',
        ])->assertSessionHasErrors('email');

        unset($voisin);
    }

    public function test_le_formulaire_recoit_mes_valeurs_brutes(): void
    {
        $moi = $this->moi();
        Agent::create(['user_id' => $moi->id, 'date_naissance' => '1990-05-14', 'lieu_naissance' => 'Douala']);

        $this->actingAs($moi)->get(route('profil.index'))
            ->assertInertia(fn (Assert $page) => $page
                // Le formulaire a besoin de la date en format ISO.
                ->where('saisie.date_naissance', '1990-05-14')
                ->where('saisie.lieu_naissance', 'Douala')
                ->where('saisie.name', $moi->name));
    }

    // -------------------------------------------------- ce que je soumets

    public function test_je_declare_un_diplome_qui_attend_la_validation(): void
    {
        $moi = $this->moi();

        $this->actingAs($moi)->post(route('profil.diplomes.store'), [
            'intitule' => 'Licence en gestion',
            'niveau' => 'Licence',
            'annee_obtention' => 2019,
        ])->assertSessionHasNoErrors();

        $diplome = Diplome::firstOrFail();

        $this->assertSame('en_attente', $diplome->statut);
        $this->assertSame($moi->id, $diplome->soumis_par);
        // Le dossier nait au premier depot.
        $this->assertSame($moi->id, $diplome->agent->user_id);
    }

    public function test_je_depose_une_piece_qui_attend_la_validation(): void
    {
        $moi = $this->moi();

        $this->actingAs($moi)->post(route('profil.documents.store'), [
            'type' => 'cv',
            'fichier' => UploadedFile::fake()->create('CV.pdf', 120, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $piece = DocumentAgent::firstOrFail();

        $this->assertSame('en_attente', $piece->statut);
        $this->assertSame($moi->id, $piece->soumis_par);
        Storage::disk('local')->assertExists($piece->fichier);
    }

    public function test_je_retire_ce_que_j_ai_soumis_tant_qu_il_attend(): void
    {
        $moi = $this->moi();
        $this->actingAs($moi)->post(route('profil.diplomes.store'), ['intitule' => 'Erreur de saisie']);

        $diplome = Diplome::firstOrFail();

        $this->actingAs($moi)->delete(route('profil.diplomes.destroy', $diplome))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Diplome::count());
    }

    public function test_je_ne_retire_plus_ce_qui_a_ete_valide(): void
    {
        $moi = $this->moi();
        $this->actingAs($moi)->post(route('profil.diplomes.store'), ['intitule' => 'Licence']);

        $diplome = Diplome::firstOrFail();
        $diplome->update(['statut' => 'valide']);

        $this->actingAs($moi)->delete(route('profil.diplomes.destroy', $diplome))
            ->assertForbidden();

        $this->assertSame(1, Diplome::count());
    }

    public function test_je_ne_retire_pas_la_piece_d_un_autre(): void
    {
        $moi = $this->moi();
        $voisin = $this->moi();
        $this->actingAs($voisin)->post(route('profil.diplomes.store'), ['intitule' => 'Licence']);

        $this->actingAs($moi)->delete(route('profil.diplomes.destroy', Diplome::firstOrFail()))
            ->assertForbidden();
    }

    public function test_je_telecharge_ma_piece_mais_pas_celle_d_un_autre(): void
    {
        $moi = $this->moi();
        $voisin = $this->moi();

        $this->actingAs($moi)->post(route('profil.documents.store'), [
            'type' => 'cv', 'fichier' => UploadedFile::fake()->create('CV.pdf', 100, 'application/pdf'),
        ]);

        $piece = DocumentAgent::firstOrFail();

        $this->actingAs($moi)->get(route('profil.documents.telecharger', $piece))->assertOk();
        $this->actingAs($voisin)->get(route('profil.documents.telecharger', $piece))->assertForbidden();
    }

    // ------------------------------------------------- ce que la RH tranche

    private function gestionnaire(): User
    {
        $rh = User::factory()->create(['status' => 'active']);
        $rh->applications()->attach(
            Application::where('module_key', 'personnel')->firstOrFail(),
            ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])],
        );
        $rh->employeursRh()->attach($this->employeur);

        return $rh;
    }

    public function test_la_rh_valide_un_diplome_soumis(): void
    {
        $moi = $this->moi();
        $this->actingAs($moi)->post(route('profil.diplomes.store'), ['intitule' => 'Licence']);
        $diplome = Diplome::firstOrFail();

        $rh = $this->gestionnaire();
        $this->actingAs($rh)->put(route('personnel.pieces.trancher', ['diplome', $diplome->id]), [
            'decision' => 'valide',
        ])->assertSessionHasNoErrors();

        $diplome->refresh();
        $this->assertSame('valide', $diplome->statut);
        $this->assertSame($rh->id, $diplome->decide_par);
        $this->assertNotNull($diplome->decide_le);
    }

    public function test_un_refus_porte_son_motif_jusqu_au_profil_de_l_agent(): void
    {
        $moi = $this->moi();
        $this->actingAs($moi)->post(route('profil.diplomes.store'), ['intitule' => 'Licence']);

        $this->actingAs($this->gestionnaire())
            ->put(route('personnel.pieces.trancher', ['diplome', Diplome::firstOrFail()->id]), [
                'decision' => 'refuse',
                'motif_refus' => 'Copie illisible : redéposez-la.',
            ]);

        $this->actingAs($moi)->get(route('profil.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('diplomes.0.statut', 'refuse')
                ->where('diplomes.0.motifRefus', 'Copie illisible : redéposez-la.'));
    }

    public function test_un_employe_ordinaire_ne_tranche_rien(): void
    {
        $moi = $this->moi();
        $this->actingAs($moi)->post(route('profil.diplomes.store'), ['intitule' => 'Licence']);

        $this->actingAs($moi)
            ->put(route('personnel.pieces.trancher', ['diplome', Diplome::firstOrFail()->id]), [
                'decision' => 'valide',
            ])->assertForbidden();
    }

    /** Ce que la RH saisit elle-meme est vrai par construction. */
    public function test_une_piece_saisie_par_la_rh_est_validee_d_emblee(): void
    {
        $moi = $this->moi();
        $agent = Agent::create(['user_id' => $moi->id]);
        Contrat::create([
            'agent_id' => $agent->id, 'employeur_id' => $this->employeur->id,
            'type' => 'cdi', 'poste' => 'Comptable', 'date_debut' => '2026-01-01',
            'quotite' => 100, 'statut' => 'actif',
        ]);

        $this->actingAs($this->gestionnaire())->post(route('personnel.documents.store', $moi), [
            'type' => 'contrat',
            'fichier' => UploadedFile::fake()->create('contrat.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame('valide', DocumentAgent::firstOrFail()->statut);
    }
}
