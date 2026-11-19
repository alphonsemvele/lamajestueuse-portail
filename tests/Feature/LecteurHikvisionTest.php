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

    /** Un numero repris d'un autre systeme garde ses chiffres : le lecteur refuse le reste. */
    public function test_un_matricule_hors_gabarit_garde_ses_chiffres(): void
    {
        $this->assertSame('110020', LecteurHikvision::numeroDepuisMatricule('1100E-20'));
        $this->assertSame('77', LecteurHikvision::numeroDepuisMatricule('A-77'));
    }

    /**
     * Deux matricules qui donnent le meme numero arretent la commande : sur
     * le lecteur, le second ecraserait le premier, empreintes comprises.
     */
    public function test_deux_matricules_sur_un_meme_numero_arretent_tout(): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'doublon').'.csv';
        file_put_contents($chemin, "Matricule;Nom;Prénom\nLM-260147;MVELE;Alphonse\n26-0147;NGONO;Marie\n");

        Http::fake();

        $this->artisan('hikvision:personnel --simuler --fichier='.$chemin)
            ->expectsOutputToContain('même numéro')
            ->assertFailed();

        Http::assertNothingSent();
        unlink($chemin);
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

    /**
     * Sans nom de fichier, la commande prend le dernier export telecharge.
     *
     * Et elle dit lequel : pousser une liste de l'an dernier sans s'en
     * apercevoir laisserait la moitie du personnel a la porte.
     */
    public function test_la_commande_prend_le_dernier_export_telecharge(): void
    {
        $maison = sys_get_temp_dir().'/telechargements-'.uniqid();
        mkdir($maison.'/Downloads', 0775, true);

        $vieux = $maison.'/Downloads/personnel-la-majestueuse-2026-01-01.csv';
        $recent = $maison.'/Downloads/personnel-la-majestueuse-2026-11-18.csv';

        file_put_contents($vieux, "Matricule;Nom;Prénom\nLM-260001;MVELE;Alphonse\n");
        file_put_contents($recent, "Matricule;Nom;Prénom\nLM-260001;MVELE;Alphonse\nLM-260002;NGONO;Marie\n");

        touch($vieux, time() - 86400 * 30);
        touch($recent, time() - 60);

        $avant = getenv('HOME');
        putenv('HOME='.$maison);
        Http::fake();

        try {
            $this->artisan('hikvision:personnel --dernier --simuler')
                ->expectsOutputToContain('personnel-la-majestueuse-2026-11-18.csv')
                ->expectsOutputToContain('2 personne(s) seraient envoyées')
                ->assertSuccessful();
        } finally {
            putenv('HOME='.$avant);
            unlink($vieux);
            unlink($recent);
            rmdir($maison.'/Downloads');
            rmdir($maison);
        }

        Http::assertNothingSent();
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
