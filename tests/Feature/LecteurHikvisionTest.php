<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LecteurHikvision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Ce que le portail envoie au lecteur d'empreintes, et ce qu'il en lit.
 *
 * Le lecteur n'est pas joignable depuis les tests : on le remplace par des
 * reponses ISAPI telles qu'il les rend, y compris ses refus — il repond
 * 200 meme quand il n'a rien fait.
 */
class LecteurHikvisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('hikvision', [
            'url' => 'https://lecteur.test',
            'utilisateur' => 'admin',
            'mot_de_passe' => 'secret',
            'verifier_certificat' => false,
            'porte' => 1,
            'plan_horaire' => '1',
            'validite_annees' => 10,
            'delai' => 5,
        ]);
    }

    private function lecteur(): LecteurHikvision
    {
        return LecteurHikvision::depuisLaConfiguration();
    }

    private function personne(array $valeurs = []): User
    {
        return User::factory()->create($valeurs + [
            'name' => 'Alphonse',
            'lastname' => 'MVELE',
            'matricule' => 'LM-260147',
            'dans_le_personnel' => true,
            'status' => 'active',
        ]);
    }

    /** Le numero envoye est la partie chiffree du matricule : le lecteur n'aime pas les lettres. */
    public function test_le_numero_envoye_est_la_partie_chiffree_du_matricule(): void
    {
        $this->assertSame('260147', LecteurHikvision::numeroDe($this->personne()));
    }

    /** Un numero repris d'un autre systeme part tel quel. */
    public function test_un_matricule_hors_gabarit_part_tel_quel(): void
    {
        $this->assertSame('A-77', LecteurHikvision::numeroDe($this->personne(['matricule' => 'A-77'])));
    }

    /** Le nom part dans l'ordre officiel : nom de famille d'abord. */
    public function test_le_nom_part_dans_l_ordre_officiel(): void
    {
        $this->assertSame('MVELE Alphonse', LecteurHikvision::nomDe($this->personne()));
    }

    /** Un nom tres long est coupe : au-dela de trente-deux signes, l'appareil refuse. */
    public function test_un_nom_trop_long_est_coupe(): void
    {
        $personne = $this->personne(['lastname' => 'NGUEMATCHOUANGSIEUFFA', 'name' => 'Jean-Baptiste Emmanuel']);

        $this->assertSame(32, mb_strlen(LecteurHikvision::nomDe($personne)));
    }

    /** Un numero inconnu du lecteur est ajoute. */
    public function test_une_personne_inconnue_est_ajoutee(): void
    {
        Http::fake([
            '*UserInfo/Search*' => Http::response(['UserInfoSearch' => ['responseStatusStrg' => 'NO MATCH']]),
            '*UserInfo/Record*' => Http::response(['statusCode' => 1, 'statusString' => 'OK']),
        ]);

        $personne = $this->personne();

        $this->assertSame('ajoute', $this->lecteur()->enregistrer(
            LecteurHikvision::numeroDe($personne),
            LecteurHikvision::nomDe($personne),
        ));

        Http::assertSent(function ($requete) {
            if (! str_contains($requete->url(), 'UserInfo/Record')) {
                return false;
            }

            $fiche = $requete->data()['UserInfo'];

            return $requete->method() === 'POST'
                && $fiche['employeeNo'] === '260147'
                && $fiche['name'] === 'MVELE Alphonse'
                && $fiche['userType'] === 'normal'
                && $fiche['Valid']['enable'] === true;
        });
    }

    /** Un numero deja pose est mis a jour, jamais double. */
    public function test_une_personne_deja_presente_est_mise_a_jour(): void
    {
        Http::fake([
            '*UserInfo/Search*' => Http::response([
                'UserInfoSearch' => ['numOfMatches' => 1, 'UserInfo' => [['employeeNo' => '260147']]],
            ]),
            '*UserInfo/Modify*' => Http::response(['statusCode' => 1, 'statusString' => 'OK']),
        ]);

        $personne = $this->personne();

        $this->assertSame('mis a jour', $this->lecteur()->enregistrer(
            LecteurHikvision::numeroDe($personne),
            LecteurHikvision::nomDe($personne),
        ));

        Http::assertSent(fn ($requete) => str_contains($requete->url(), 'UserInfo/Modify')
            && $requete->method() === 'PUT');
    }

    /** Le lecteur refuse en repondant 200 : le motif est dans le corps, et il doit remonter. */
    public function test_un_refus_du_lecteur_est_signale(): void
    {
        Http::fake([
            '*UserInfo/Search*' => Http::response(['UserInfoSearch' => ['responseStatusStrg' => 'NO MATCH']]),
            '*UserInfo/Record*' => Http::response([
                'statusCode' => 6,
                'statusString' => 'Invalid Content',
                'subStatusCode' => 'employeeNoAlreadyExist',
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('employeeNoAlreadyExist');

        $personne = $this->personne();

        $this->lecteur()->enregistrer(LecteurHikvision::numeroDe($personne), LecteurHikvision::nomDe($personne));
    }

    /** Un mot de passe refuse se dit clairement, sans parler de HTTP. */
    public function test_un_mot_de_passe_refuse_est_dit_clairement(): void
    {
        Http::fake(['*' => Http::response('', 401)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('refusé l’identifiant ou le mot de passe');

        $this->lecteur()->informations();
    }

    /** Sans adresse ni mot de passe, on ne tente rien. */
    public function test_sans_reglage_le_lecteur_n_est_pas_appele(): void
    {
        config()->set('hikvision.url', null);
        Http::fake();

        $this->expectException(RuntimeException::class);

        try {
            $this->lecteur()->informations();
        } finally {
            Http::assertNothingSent();
        }
    }

    /** La commande liste le personnel a matricule, sans rien ecrire, en simulation. */
    public function test_la_commande_simule_sans_rien_ecrire(): void
    {
        $this->personne();
        $this->personne(['matricule' => null, 'lastname' => 'SANS', 'name' => 'Matricule']);
        Http::fake();

        $this->artisan('hikvision:personnel --simuler')
            ->expectsOutputToContain('1 personne(s) seraient envoyées')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    /**
     * Le fichier du portail remplace la base : c'est par la que passe le
     * personnel quand la base vit ailleurs que sur le poste du site.
     */
    public function test_la_commande_lit_le_fichier_du_portail(): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'personnel').'.csv';
        file_put_contents($chemin, "\xEF\xBB\xBFMatricule;Nom;Prénom;E-mail\n"
            ."LM-260147;MVELE;Alphonse;a@b.cm\n"
            .";SANS;Matricule;c@d.cm\n"
            ."LM-260148;NGONO;Marie;e@f.cm\n");

        Http::fake();

        $this->artisan('hikvision:personnel --simuler --fichier='.$chemin)
            ->expectsOutputToContain('2 personne(s) seraient envoyées')
            ->assertSuccessful();

        Http::assertNothingSent();
        unlink($chemin);
    }

    /** Un fichier sans les bonnes colonnes est refuse, avec le chemin a suivre. */
    public function test_un_fichier_etranger_est_refuse(): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'autre').'.csv';
        file_put_contents($chemin, "colonne;autre\nvaleur;valeur\n");

        $this->artisan('hikvision:personnel --simuler --fichier='.$chemin)
            ->expectsOutputToContain('Personnel')
            ->assertFailed();

        unlink($chemin);
    }

    /** Sans reglage, la commande le dit et ne part pas en erreur silencieuse. */
    public function test_la_commande_refuse_un_lecteur_non_configure(): void
    {
        config()->set('hikvision.mot_de_passe', null);

        $this->artisan('hikvision:personnel')
            ->expectsOutputToContain('n’est pas configuré')
            ->assertFailed();
    }
}
