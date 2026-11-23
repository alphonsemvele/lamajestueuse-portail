<?php

namespace Tests\Feature\Admin;

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Le journal d'acces : qui vient, ou il va, et combien de fois.
 *
 * Les connexions et les ouvertures d'application s'y inscrivaient deja ;
 * les pages visitees s'y ajoutent, et le tout s'emporte — en detail, ou
 * compte par personne.
 */
class JournalAccesTest extends TestCase
{
    use RefreshDatabase;

    private function patron(): User
    {
        return User::factory()->superadmin()->create(['status' => 'active']);
    }

    private function trace(User $personne, string $action, ?string $cible = null, int $joursAvant = 0): AccessLog
    {
        $trace = AccessLog::create([
            'user_id' => $personne->id,
            'action' => $action,
            'cible' => $cible,
            'ip_address' => '192.168.1.10',
        ]);

        if ($joursAvant > 0) {
            $trace->forceFill(['created_at' => now()->subDays($joursAvant)])->save();
        }

        return $trace;
    }

    /** Ouvrir une page laisse une trace, avec le nom de l'écran. */
    public function test_une_page_ouverte_laisse_sa_trace(): void
    {
        $personne = $this->patron();

        $this->actingAs($personne)->get(route('dashboard'))->assertOk();

        $trace = AccessLog::where('action', 'visite')->firstOrFail();

        $this->assertSame($personne->id, $trace->user_id);
        $this->assertSame('dashboard', $trace->cible);
    }

    /**
     * La même page revue dans la foulée ne s'inscrit qu'une fois : l'écran
     * de présence se recharge tout seul toutes les dix secondes, il
     * remplirait le journal à lui seul.
     */
    public function test_la_meme_page_revue_aussitot_ne_compte_qu_une_fois(): void
    {
        $personne = $this->patron();

        $this->actingAs($personne)->get(route('dashboard'))->assertOk();
        $this->actingAs($personne)->get(route('dashboard'))->assertOk();

        $this->assertSame(1, AccessLog::where('action', 'visite')->count());
    }

    /** Un rechargement partiel d'Inertia n'est pas une visite. */
    public function test_un_rechargement_partiel_n_est_pas_une_visite(): void
    {
        Cache::flush();

        $this->actingAs($this->patron())
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => '1',
                'X-Inertia-Partial-Data' => 'logs',
                'X-Inertia-Partial-Component' => 'dashboard',
            ])
            ->get(route('dashboard'));

        $this->assertSame(0, AccessLog::where('action', 'visite')->count());
    }

    /** Un visiteur sans compte ne laisse pas de trace de visite. */
    public function test_un_visiteur_sans_compte_ne_laisse_rien(): void
    {
        $this->get(route('login'));

        $this->assertSame(0, AccessLog::where('action', 'visite')->count());
    }

    /** Le compte par personne : une ligne, et ce qu'elle pèse. */
    public function test_le_journal_se_compte_par_personne(): void
    {
        $patron = $this->patron();
        $autre = User::factory()->create(['name' => 'Claire', 'lastname' => 'NKOA', 'status' => 'active']);

        $this->trace($patron, 'login');
        $this->trace($patron, 'login');
        $this->trace($patron, 'visite', 'personnel.presence');
        $this->trace($patron, 'sso');
        $this->trace($autre, 'login');

        $this->actingAs($patron)
            ->get(route('admin.logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/logs/index')
                ->where('parUtilisateur.0.connexions', 2)
                ->where('parUtilisateur.0.ouvertures', 1)
                ->where('resume.personnes', 2));
    }

    public function test_le_journal_s_emporte_par_utilisateur(): void
    {
        $patron = $this->patron();

        $this->trace($patron, 'login');
        $this->trace($patron, 'login');
        $this->trace($patron, 'visite', 'badges.index');

        $reponse = $this->actingAs($patron)
            ->get(route('admin.logs.export', ['vue' => 'utilisateurs']));

        $reponse->assertOk();

        $contenu = $reponse->streamedContent();

        $this->assertStringContainsString('Connexions;"Pages visitées"', $contenu);
        $this->assertStringContainsString($patron->nomOfficiel(), $contenu);
        // Deux connexions, une page vue — la visite de l'écran du journal
        // lui-même s'ajoute, donc on ne compte que les connexions.
        $this->assertMatchesRegularExpression('/;2;/', $contenu);
    }

    public function test_le_journal_s_emporte_en_detail(): void
    {
        $patron = $this->patron();
        $this->trace($patron, 'visite', 'personnel.presence');

        $reponse = $this->actingAs($patron)->get(route('admin.logs.export'));

        $reponse->assertOk();

        $contenu = $reponse->streamedContent();

        $this->assertStringContainsString('Date;Heure;Employé;Matricule;Action', $contenu);
        $this->assertStringContainsString('personnel.presence', $contenu);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenu);
    }

    /** Hors de la période demandée, rien ne sort. */
    public function test_la_periode_borne_ce_qui_sort(): void
    {
        $patron = $this->patron();
        $this->trace($patron, 'login', null, 90);

        $reponse = $this->actingAs($patron)->get(route('admin.logs.export', [
            'du' => now()->subDays(7)->toDateString(),
            'au' => now()->toDateString(),
            'vue' => 'utilisateurs',
        ]));

        $this->assertStringNotContainsString(';90;', $reponse->streamedContent());
    }

    public function test_un_employe_ordinaire_n_ouvre_pas_le_journal(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'active']))
            ->get(route('admin.logs.index'))
            ->assertForbidden();
    }
}
