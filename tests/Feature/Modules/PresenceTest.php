<?php

namespace Tests\Feature\Modules;

use App\Models\Application;
use App\Models\Pointage;
use App\Models\User;
use App\Services\JetonRelais;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * La presence : ce que le lecteur annonce, et ce que l'ecran en montre.
 *
 * Le lecteur pousse chaque passage vers le portail des qu'il a lieu. Il
 * n'accepte ni en-tete ni signature sur ces envois : un jeton voyage donc
 * dans l'adresse, deduit du secret partage.
 */
class PresenceTest extends TestCase
{
    use RefreshDatabase;

    private Application $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Application::factory()->module('personnel')->create(['name' => 'Personnel & paie']);
        config()->set('hikvision.portail.client_id', 'relais-lecteur');
        config()->set('hikvision.portail.secret', str_repeat('e', 64));
    }

    private function jeton(): string
    {
        return (string) JetonRelais::jetonPointage();
    }

    /** @return array<string, mixed> */
    private function passage(string $numero = '260147', string $moment = '2026-11-19T08:12:30+01:00'): array
    {
        return [
            'ipAddress' => '192.168.2.252',
            'dateTime' => $moment,
            'eventType' => 'AccessControllerEvent',
            'AccessControllerEvent' => [
                'majorEventType' => 5,
                'subEventType' => 75,
                'serialNo' => 4123,
                'employeeNoString' => $numero,
                'name' => 'MVELE Alphonse',
                'cardNo' => '2046362426',
                'cardReaderNo' => 1,
                'doorNo' => 1,
                'currentVerifyMode' => 'cardOrFaceOrFp',
            ],
        ];
    }

    private function rh(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->applications()->attach($this->module, ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])]);

        return $user;
    }

    public function test_le_lecteur_pousse_un_passage_et_il_est_garde(): void
    {
        $employe = User::factory()->create([
            'name' => 'Alphonse', 'lastname' => 'MVELE', 'matricule' => 'LM-260147',
            'dans_le_personnel' => true, 'status' => 'active',
        ]);

        $this->postJson('/api/lecteur/pointage/'.$this->jeton(), $this->passage())
            ->assertOk()
            ->assertJsonPath('gardes', 1);

        $pointage = Pointage::firstOrFail();

        $this->assertSame('260147', $pointage->numero);
        $this->assertSame($employe->id, $pointage->user_id);
        $this->assertSame('entree', $pointage->sens);
        $this->assertSame('carte', $pointage->moyen);
        $this->assertSame('08:12', $pointage->moment->format('H:i'));
    }

    /** Le lecteur repete ses annonces quand il doute : on ne compte qu'une fois. */
    public function test_le_meme_passage_annonce_deux_fois_ne_compte_qu_une(): void
    {
        $this->postJson('/api/lecteur/pointage/'.$this->jeton(), $this->passage())->assertOk();
        $this->postJson('/api/lecteur/pointage/'.$this->jeton(), $this->passage())->assertOk();

        $this->assertSame(1, Pointage::count());
    }

    public function test_un_jeton_inconnu_n_entre_pas(): void
    {
        $this->postJson('/api/lecteur/pointage/'.str_repeat('x', 48), $this->passage())
            ->assertNotFound();

        $this->assertSame(0, Pointage::count());
    }

    /** Une annonce qui ne designe personne — une porte ouverte — ne pointe rien. */
    public function test_une_annonce_sans_personne_ne_pointe_rien(): void
    {
        $evenement = $this->passage();
        $evenement['AccessControllerEvent']['employeeNoString'] = '';

        $this->postJson('/api/lecteur/pointage/'.$this->jeton(), $evenement)
            ->assertOk()
            ->assertJsonPath('gardes', 0);

        $this->assertSame(0, Pointage::count());
    }

    /** Un numero que le portail ne connait pas est garde quand meme, et signale. */
    public function test_un_numero_inconnu_est_garde_et_signale(): void
    {
        $this->postJson('/api/lecteur/pointage/'.$this->jeton(), $this->passage('999999'))->assertOk();

        $pointage = Pointage::firstOrFail();

        $this->assertNull($pointage->user_id);
        $this->assertSame('999999', $pointage->numero);
    }

    public function test_l_ecran_montre_les_passages_du_jour(): void
    {
        $this->postJson('/api/lecteur/pointage/'.$this->jeton(), $this->passage(
            '260147', now()->setTime(8, 12)->toIso8601String()
        ))->assertOk();

        $this->actingAs($this->rh())
            ->get(route('personnel.presence'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/personnel/presence')
                ->where('resume.passages', 1)
                ->where('resume.personnes', 1)
                ->where('pointages.data.0.numero', '260147'));
    }

    public function test_un_employe_ordinaire_n_entre_pas_dans_la_presence(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'active']))
            ->get(route('personnel.presence'))
            ->assertForbidden();
    }
}
