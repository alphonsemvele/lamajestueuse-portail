<?php

namespace Tests\Feature;

use App\Models\RelaisLecteur;
use App\Models\TacheLecteur;
use App\Models\User;
use App\Services\JetonRelais;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le canal entre le portail et le lecteur de presence.
 *
 * Le lecteur vit sur le reseau du site, derriere une adresse privee : le
 * portail ne l'atteint pas. Il depose donc ses ordres dans une file, et un
 * relais installe sur place vient les chercher. Tout le trafic sort du site,
 * jamais l'inverse — on verifie ici que la file tient ses promesses et que
 * nul autre que le relais n'y touche.
 */
class RelaisLecteurTest extends TestCase
{
    use RefreshDatabase;

    private RelaisLecteur $relais;

    protected function setUp(): void
    {
        parent::setUp();

        // Le relais naît avec la migration : on lui donne juste un secret
        // connu, pour pouvoir signer comme lui.
        $this->relais = RelaisLecteur::firstOrFail();
        $this->relais->update(['client_secret' => str_repeat('s', 64)]);
    }

    private function jeton(string $usage = 'lecteur'): string
    {
        return JetonRelais::signer('relais-lecteur', $this->relais->client_secret, $usage);
    }

    private function ordre(array $valeurs = []): TacheLecteur
    {
        return TacheLecteur::create($valeurs + [
            'action' => 'lister',
            'parametres' => [],
            'statut' => 'en_attente',
        ]);
    }

    public function test_le_relais_emporte_les_ordres_en_attente(): void
    {
        $ordre = $this->ordre(['action' => 'personnel', 'parametres' => ['personnes' => [['matricule' => 'LM-260147', 'nom' => 'MVELE Alphonse']]]]);

        $this->withToken($this->jeton())
            ->getJson(route('api.lecteur.taches'))
            ->assertOk()
            ->assertJsonPath('taches.0.id', $ordre->id)
            ->assertJsonPath('taches.0.action', 'personnel')
            ->assertJsonPath('taches.0.parametres.personnes.0.matricule', 'LM-260147');

        // Pris, donc plus dans la file : deux relais ne font pas le travail deux fois.
        $this->assertSame('prise', $ordre->refresh()->statut);
        $this->assertNotNull($ordre->prise_le);
    }

    public function test_un_ordre_deja_pris_n_est_pas_redonne(): void
    {
        $this->ordre();

        $this->withToken($this->jeton())->getJson(route('api.lecteur.taches'))->assertOk();

        $this->withToken($this->jeton())
            ->getJson(route('api.lecteur.taches'))
            ->assertOk()
            ->assertJsonCount(0, 'taches');
    }

    public function test_sans_jeton_valable_la_file_reste_fermee(): void
    {
        $ordre = $this->ordre();

        $this->getJson(route('api.lecteur.taches'))->assertUnauthorized();
        $this->withToken('n’importe quoi')->getJson(route('api.lecteur.taches'))->assertUnauthorized();

        // Un jeton signe avec un autre secret ne vaut rien non plus.
        $this->withToken(JetonRelais::signer('relais-lecteur', str_repeat('x', 64), 'lecteur'))
            ->getJson(route('api.lecteur.taches'))
            ->assertUnauthorized();

        $this->assertSame('en_attente', $ordre->refresh()->statut);
    }

    /** Un jeton ne sert qu'une fois : rejoue, il est refuse. */
    public function test_un_jeton_rejoue_est_refuse(): void
    {
        $this->ordre();
        $jeton = $this->jeton();

        $this->withToken($jeton)->getJson(route('api.lecteur.taches'))->assertOk();
        $this->withToken($jeton)->getJson(route('api.lecteur.taches'))->assertUnauthorized();
    }

    /** Un jeton taille pour un autre usage ne donne pas acces a la file. */
    public function test_un_jeton_d_un_autre_usage_est_refuse(): void
    {
        $this->withToken($this->jeton('effectif'))
            ->getJson(route('api.lecteur.taches'))
            ->assertUnauthorized();
    }

    public function test_le_relais_rapporte_ce_qu_il_a_fait(): void
    {
        $ordre = $this->ordre(['statut' => 'prise']);

        $this->withToken($this->jeton())
            ->postJson(route('api.lecteur.compte-rendu', $ordre), [
                'resultat' => ['personnes' => 124, 'cartes' => 12],
            ])
            ->assertOk();

        $ordre->refresh();

        $this->assertSame('faite', $ordre->statut);
        $this->assertSame(124, $ordre->resultat['personnes']);
        $this->assertNotNull($ordre->terminee_le);
    }

    public function test_un_echec_est_rapporte_comme_tel(): void
    {
        $ordre = $this->ordre(['statut' => 'prise']);

        $this->withToken($this->jeton())
            ->postJson(route('api.lecteur.compte-rendu', $ordre), [
                'erreur' => 'Le lecteur a refusé l’identifiant ou le mot de passe.',
            ])
            ->assertOk();

        $this->assertSame('echouee', $ordre->refresh()->statut);
        $this->assertStringContainsString('refusé', (string) $ordre->erreur);
    }

    /**
     * Un relais coupe en pleine tache laisserait l'ordre « en cours » pour
     * toujours : passe un quart d'heure, il retourne a la file.
     */
    public function test_un_ordre_abandonne_retourne_a_la_file(): void
    {
        $ordre = $this->ordre(['statut' => 'prise']);
        $ordre->forceFill(['prise_le' => now()->subHour()])->save();

        $this->withToken($this->jeton())
            ->getJson(route('api.lecteur.taches'))
            ->assertOk()
            ->assertJsonPath('taches.0.id', $ordre->id);
    }

    /** L'ordre « personnel » emporte la liste du portail, telle qu'elle est. */
    public function test_l_ordre_personnel_emporte_la_liste_du_portail(): void
    {
        User::factory()->create([
            'name' => 'Alphonse', 'lastname' => 'MVELE', 'matricule' => 'LM-260147',
            'dans_le_personnel' => true, 'status' => 'active',
        ]);
        User::factory()->create([
            'name' => 'Sans', 'lastname' => 'MATRICULE', 'matricule' => null,
            'dans_le_personnel' => true, 'status' => 'active',
        ]);

        $this->artisan('lecteur:demander personnel')->assertSuccessful();

        $ordre = TacheLecteur::firstOrFail();

        $this->assertSame('personnel', $ordre->action);
        $this->assertCount(1, $ordre->parametres['personnes']);
        $this->assertSame('MVELE Alphonse', $ordre->parametres['personnes'][0]['nom']);
    }

    /**
     * Le secret peut venir du .env du portail : c'est la voie de celui qui
     * n'a pas la main sur la base, et elle doit valoir celle de la base.
     */
    public function test_le_secret_peut_venir_du_env_du_portail(): void
    {
        // La base porte un autre secret : c'est bien celui du .env qui sert.
        $this->relais->update(['client_secret' => str_repeat('z', 64)]);

        config()->set('hikvision.portail.client_id', 'relais-lecteur');
        config()->set('hikvision.portail.secret', $secret = str_repeat('e', 64));

        $ordre = $this->ordre();

        $this->withToken(JetonRelais::signer('relais-lecteur', $secret, 'lecteur'))
            ->getJson(route('api.lecteur.taches'))
            ->assertOk()
            ->assertJsonPath('taches.0.id', $ordre->id);

        $this->assertNotNull($this->relais->refresh()->derniere_visite);
    }

    public function test_un_ordre_inconnu_est_refuse(): void
    {
        $this->artisan('lecteur:demander danser')->assertFailed();

        $this->assertSame(0, TacheLecteur::count());
    }
}
