<?php

namespace Tests\Feature\Modules;

use App\Models\Agent;
use App\Models\Application;
use App\Models\Bulletin;
use App\Models\CategorieRh;
use App\Models\Contrat;
use App\Models\Echelon;
use App\Models\Employeur;
use App\Models\Indemnite;
use App\Models\ProfilSalaire;
use App\Models\Retenue;
use App\Models\User;
use App\Services\BulletinPdf;
use App\Services\PaieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le bulletin en PDF : previsualise ou enregistre, par la RH comme par le
 * salarie, et toujours le meme document.
 */
class ApercuBulletinPdfTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    private Employeur $employeur;

    private Employeur $autre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        Application::factory()->module('bulletins')->create(['name' => 'Mon bulletin de paie']);

        $this->employeur = Employeur::create([
            'nom' => 'Institut Universitaire', 'sigle' => 'IUM', 'actif' => true,
            'niu' => 'M012345678901X', 'numero_cnps' => '1-234567', 'signataire' => 'La Directrice',
        ]);
        $this->autre = Employeur::create(['nom' => 'Autre', 'sigle' => 'AUT', 'actif' => true]);
    }

    private function gestionnaire(): User
    {
        $user = User::factory()->create();
        $user->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);
        $user->employeursRh()->attach($this->employeur);

        return $user;
    }

    private function bulletin(string $statut = 'brouillon', ?Employeur $employeur = null): Bulletin
    {
        $employeur ??= $this->employeur;

        $categorie = CategorieRh::firstOrCreate(['libelle' => 'Catégorie 7'], ['actif' => true]);
        $echelon = Echelon::firstOrCreate(
            ['categorie_rh_id' => $categorie->id, 'libelle' => 'A'],
            ['numero' => 1, 'salaire' => 200000, 'actif' => true],
        );

        Contrat::create([
            'agent_id' => Agent::create(['user_id' => User::factory()->create(['matricule' => 'LM-26'.rand(1000, 9999)])->id])->id,
            'employeur_id' => $employeur->id,
            'type' => 'cdi', 'poste' => 'Enseignant', 'date_debut' => '2026-01-01',
            'quotite' => 100, 'echelon_id' => $echelon->id, 'statut' => 'actif',
        ]);

        app(PaieService::class)->genererMois($employeur, 9, 2026);

        $bulletin = Bulletin::latest('id')->firstOrFail();
        $bulletin->update(['statut' => $statut]);

        return $bulletin->fresh(['agent.user', 'employeur', 'contrat']);
    }

    /** Un contrat en etat de produire un bulletin, sans qu'aucun soit emis. */
    private function contrat(?Employeur $employeur = null, bool $avecProfil = true): Contrat
    {
        $employeur ??= $this->employeur;

        $categorie = CategorieRh::firstOrCreate(['libelle' => 'Catégorie 7'], ['actif' => true]);
        $echelon = Echelon::firstOrCreate(
            ['categorie_rh_id' => $categorie->id, 'libelle' => 'A'],
            ['numero' => 1, 'salaire' => 200000, 'actif' => true],
        );

        $profil = $avecProfil
            ? ProfilSalaire::create([
                'nom' => 'Enseignant permanent', 'categorie_rh_id' => $categorie->id,
                'echelon_id' => $echelon->id, 'actif' => true,
            ])
            : null;

        $profil?->indemnites()->attach(
            Indemnite::create(['libelle' => 'Transport', 'imposable' => true, 'actif' => true])->id,
            ['type_calcul' => 'fixe', 'valeur' => 30000],
        );

        return Contrat::create([
            'agent_id' => Agent::create(['user_id' => User::factory()->create(['matricule' => 'LM-26'.rand(1000, 9999)])->id])->id,
            'employeur_id' => $employeur->id,
            'profil_salaire_id' => $profil?->id,
            'type' => 'cdi', 'poste' => 'Enseignant', 'date_debut' => '2026-01-01',
            'quotite' => 100, 'echelon_id' => $echelon->id, 'statut' => 'actif',
        ]);
    }

    /** Charge le profil d'un contrat de lignes, pour voir le document grossir. */
    private function charger(Contrat $contrat, int $indemnites, int $retenues): void
    {
        $profil = $contrat->profil;

        for ($i = 1; $i <= $indemnites; $i++) {
            $profil->indemnites()->attach(
                Indemnite::create(['libelle' => 'Indemnité numéro '.$i, 'imposable' => true, 'actif' => true])->id,
                ['type_calcul' => $i % 2 ? 'fixe' : 'pourcentage', 'valeur' => $i % 2 ? 15000 : 5],
            );
        }

        for ($r = 1; $r <= $retenues; $r++) {
            $profil->retenues()->attach(
                Retenue::create(['libelle' => 'Retenue numéro '.$r, 'actif' => true])->id,
                ['type_calcul' => $r % 2 ? 'fixe' : 'pourcentage', 'valeur' => $r % 2 ? 4000 : 2],
            );
        }
    }

    /** Le nombre de pages d'un PDF, lu dans son objet « Pages ». */
    private function pages(string $pdf): int
    {
        preg_match('#/Type /Pages.*?/Count (\\d+)#s', $pdf, $trouve);

        return (int) ($trouve[1] ?? 0);
    }

    // ------------------------------------- le bulletin qu'un contrat produirait

    /**
     * Le point de la chose : lire le bulletin des qu'un profil est affecte,
     * sans generer la paie du mois ni rien enregistrer.
     */
    public function test_la_rh_lit_le_bulletin_qu_un_contrat_produirait(): void
    {
        $contrat = $this->contrat();

        $reponse = $this->actingAs($this->gestionnaire())
            ->get(route('personnel.contrat.bulletin', [$contrat, 'apercu' => 1]))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', $reponse->headers->get('content-type'));
        $this->assertStringContainsString('inline', (string) $reponse->headers->get('content-disposition'));

        // Rien n'a ete emis : c'est tout l'interet.
        $this->assertSame(0, Bulletin::count());
    }

    /**
     * Un bulletin tient sur une page, quel que soit le profil : au-dela d'une
     * dizaine de lignes, le gabarit se resserre au lieu de deborder.
     */
    public function test_le_bulletin_tient_sur_une_page_meme_charge(): void
    {
        $gestionnaire = $this->gestionnaire();

        foreach ([[3, 2], [9, 6], [14, 10], [18, 14]] as [$indemnites, $retenues]) {
            $contrat = $this->contrat();
            $this->charger($contrat, $indemnites, $retenues);

            $reponse = $this->actingAs($gestionnaire)
                ->get(route('personnel.contrat.bulletin', [$contrat, 'apercu' => 1]))
                ->assertOk();

            $this->assertSame(
                1,
                $this->pages($reponse->getContent()),
                "Le bulletin deborde avec $indemnites indemnites et $retenues retenues.",
            );
        }
    }

    public function test_l_apercu_n_exige_pas_de_profil_de_salaire(): void
    {
        // L'echelon suffit a produire un bulletin : le profil n'ajoute que
        // des lignes.
        $contrat = $this->contrat(avecProfil: false);

        $this->actingAs($this->gestionnaire())
            ->get(route('personnel.contrat.bulletin', [$contrat, 'apercu' => 1]))
            ->assertOk();
    }

    /** Sans base de remuneration, il n'y a rien a montrer — et on le dit. */
    public function test_un_contrat_sans_echelon_le_dit_au_lieu_d_echouer(): void
    {
        $contrat = $this->contrat(avecProfil: false);
        $contrat->update(['echelon_id' => null]);

        $this->actingAs($this->gestionnaire())
            ->get(route('personnel.contrat.bulletin', $contrat))
            ->assertSessionHasErrors('paie');
    }

    /** Le perimetre vaut ici comme ailleurs. */
    public function test_on_ne_lit_pas_le_bulletin_d_une_entite_qu_on_ne_suit_pas(): void
    {
        $contrat = $this->contrat($this->autre);

        $this->actingAs($this->gestionnaire())
            ->get(route('personnel.contrat.bulletin', $contrat))
            ->assertForbidden();
    }

    // ------------------------------------------------------------- cote RH

    public function test_la_rh_previsualise_le_bulletin(): void
    {
        $bulletin = $this->bulletin();

        $reponse = $this->actingAs($this->gestionnaire())
            ->get(route('personnel.paie.bulletin.pdf', [$bulletin, 'apercu' => 1]))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', $reponse->headers->get('content-type'));
        // Servi en ligne : c'est ce qui permet de l'afficher dans la page.
        $this->assertStringContainsString('inline', (string) $reponse->headers->get('content-disposition'));
    }

    public function test_la_rh_telecharge_le_bulletin(): void
    {
        $bulletin = $this->bulletin();

        $reponse = $this->actingAs($this->gestionnaire())
            ->get(route('personnel.paie.bulletin.pdf', $bulletin))
            ->assertOk();

        $this->assertStringContainsString('attachment', (string) $reponse->headers->get('content-disposition'));
    }

    /** Un brouillon se relit avant d'etre arrete : c'est tout l'interet. */
    public function test_un_brouillon_se_previsualise(): void
    {
        $bulletin = $this->bulletin('brouillon');

        $this->actingAs($this->gestionnaire())
            ->get(route('personnel.paie.bulletin.pdf', [$bulletin, 'apercu' => 1]))
            ->assertOk();
    }

    public function test_un_gestionnaire_d_une_autre_entite_n_y_accede_pas(): void
    {
        $bulletin = $this->bulletin('valide', $this->autre);

        $this->actingAs($this->gestionnaire())
            ->get(route('personnel.paie.bulletin.pdf', $bulletin))
            ->assertForbidden();
    }

    public function test_un_employe_ordinaire_n_accede_pas_au_pdf_de_la_rh(): void
    {
        $bulletin = $this->bulletin('valide');

        $this->actingAs(User::factory()->create())
            ->get(route('personnel.paie.bulletin.pdf', $bulletin))
            ->assertForbidden();
    }

    // -------------------------------------------------------- cote salarie

    public function test_le_salarie_previsualise_son_bulletin(): void
    {
        $bulletin = $this->bulletin('valide');

        $reponse = $this->actingAs($bulletin->agent->user)
            ->get(route('mes-bulletins.pdf', [$bulletin, 'apercu' => 1]))
            ->assertOk();

        $this->assertStringContainsString('inline', (string) $reponse->headers->get('content-disposition'));
    }

    public function test_le_salarie_telecharge_son_bulletin(): void
    {
        $bulletin = $this->bulletin('valide');

        $reponse = $this->actingAs($bulletin->agent->user)
            ->get(route('mes-bulletins.pdf', $bulletin))
            ->assertOk();

        $this->assertStringContainsString('attachment', (string) $reponse->headers->get('content-disposition'));
    }

    public function test_le_salarie_ne_voit_pas_un_brouillon(): void
    {
        $bulletin = $this->bulletin('brouillon');

        $this->actingAs($bulletin->agent->user)
            ->get(route('mes-bulletins.pdf', [$bulletin, 'apercu' => 1]))
            ->assertNotFound();
    }

    public function test_personne_ne_previsualise_le_bulletin_d_un_autre(): void
    {
        $bulletin = $this->bulletin('valide');

        $this->actingAs(User::factory()->create())
            ->get(route('mes-bulletins.pdf', [$bulletin, 'apercu' => 1]))
            ->assertForbidden();
    }

    // ------------------------------------------------------- le document

    public function test_le_document_porte_l_identite_et_le_decompte(): void
    {
        $bulletin = $this->bulletin('paye');

        $contenu = $this->actingAs($this->gestionnaire())
            ->get(route('personnel.paie.bulletin.pdf', $bulletin))->getContent();

        // Le PDF est compresse : on verifie sur le rendu HTML du meme modele.
        $html = view('pdf.bulletin', [
            'bulletin' => $bulletin,
            'enTete' => app(BulletinPdf::class)->enTete($bulletin),
            'employeur' => $bulletin->employeur,
            'contrat' => $bulletin->contrat,
            'agent' => $bulletin->agent,
            'salarie' => $bulletin->agent->user,
            'mention' => 'Essai',
        ])->render();

        $this->assertNotEmpty($contenu);
        $this->assertStringContainsString('BULLETIN DE PAIE', $html);
        $this->assertStringContainsString('septembre 2026', $html);
        $this->assertStringContainsString('Institut Universitaire', $html);
        $this->assertStringContainsString('M012345678901X', $html);          // NIU
        $this->assertStringContainsString('La Directrice', $html);           // signataire
        $this->assertStringContainsString('Net à payer', $html);
        $this->assertStringContainsString('200 000 F', $html);               // salaire de base
        $this->assertStringContainsString($bulletin->agent->user->matricule, $html);
    }

    public function test_un_brouillon_est_annonce_comme_provisoire(): void
    {
        $bulletin = $this->bulletin('brouillon');

        $html = view('pdf.bulletin', [
            'bulletin' => $bulletin,
            'enTete' => app(BulletinPdf::class)->enTete($bulletin),
            'employeur' => $bulletin->employeur,
            'contrat' => $bulletin->contrat,
            'agent' => $bulletin->agent,
            'salarie' => $bulletin->agent->user,
            'mention' => 'Essai',
        ])->render();

        $this->assertStringContainsString('Provisoire', $html);
    }

    public function test_le_fichier_porte_le_matricule_et_la_periode(): void
    {
        $bulletin = $this->bulletin('valide');
        $nom = app(BulletinPdf::class)->nomDuFichier($bulletin);

        $this->assertSame(
            'bulletin-'.$bulletin->agent->user->matricule.'-2026-09.pdf',
            $nom,
        );
    }
}
