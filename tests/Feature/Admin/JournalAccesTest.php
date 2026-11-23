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

    /** Un compte d'administration technique : il n'est pas du personnel. */
    private function patron(): User
    {
        return User::factory()->superadmin()->create([
            'status' => 'active',
            'dans_le_personnel' => false,
        ]);
    }

    /** Un membre du personnel, celui qui compte dans la liste. */
    private function agent(): User
    {
        return User::factory()->create([
            'name' => 'Claire', 'lastname' => 'NKOA', 'matricule' => 'LM-260147',
            'dans_le_personnel' => true, 'status' => 'active',
        ]);
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
        $agent = $this->agent();

        $this->trace($agent, 'login');
        $this->trace($agent, 'login');
        $this->trace($agent, 'visite', 'personnel.presence');
        $this->trace($agent, 'sso');
        $this->trace($patron, 'login');

        $this->actingAs($patron)
            ->get(route('admin.logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/logs/index')
                // Seul l'agent figure au compte : le patron n'est pas du personnel.
                ->has('parUtilisateur', 1)
                ->where('parUtilisateur.0.connexions', 2)
                ->where('parUtilisateur.0.ouvertures', 1)
                ->where('resume.personnes', 2));
    }

    public function test_le_journal_s_emporte_par_utilisateur(): void
    {
        $patron = $this->patron();
        $agent = $this->agent();

        $this->trace($agent, 'login');
        $this->trace($agent, 'visite', 'badges.index');

        $reponse = $this->actingAs($patron)
            ->get(route('admin.logs.export', ['vue' => 'utilisateurs']));

        $reponse->assertOk();

        $contenu = $reponse->streamedContent();

        $this->assertStringContainsString('"Nom et prénom";Matricule;"Pages visitées"', $contenu);
        $this->assertStringContainsString('NKOA Claire', $contenu);
        $this->assertStringContainsString('LM-260147', $contenu);
        // Le nombre de connexions ne figure plus dans le téléchargement.
        $this->assertStringNotContainsString('Connexions', $contenu);
    }

    /**
     * Un compte d'administration qui ne travaille pas dans le groupe n'a
     * rien a faire dans la liste de ceux qui se servent du logiciel.
     */
    public function test_un_superadministrateur_hors_du_personnel_n_y_figure_pas(): void
    {
        $patron = $this->patron();
        $agent = $this->agent();

        $this->trace($patron, 'login');
        $this->trace($agent, 'login');

        $contenu = $this->actingAs($patron)
            ->get(route('admin.logs.export', ['vue' => 'utilisateurs']))
            ->streamedContent();

        $this->assertStringContainsString('NKOA Claire', $contenu);
        $this->assertStringNotContainsString($patron->nomOfficiel(), $contenu);
    }

    /** Le PDF : le nom, le prénom, le matricule, et rien d'autre. */
    public function test_la_liste_s_emporte_en_pdf(): void
    {
        $patron = $this->patron();
        $this->trace($this->agent(), 'visite', 'badges.index');

        $reponse = $this->actingAs($patron)
            ->get(route('admin.logs.export', ['format' => 'pdf']));

        $reponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $reponse->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', (string) $reponse->getContent());
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
