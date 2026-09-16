<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Middleware\HandleInertiaRequests;
use Tests\TestCase;

class ApplicationLaunchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * En-tetes d'une visite Inertia, version comprise : sans la bonne version,
     * le middleware court-circuite le controleur pour forcer un rechargement.
     *
     * @return array<string, string>
     */
    private function enTetesInertia(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) (new HandleInertiaRequests())->version(request()),
        ];
    }

    public function test_ouvrir_une_application_redirige_vers_son_lien(): void
    {
        $user = User::factory()->create();
        $app = Application::factory()->create(['url' => 'https://fondation.lamajestueuse.cm']);
        $user->applications()->attach($app);

        $this->actingAs($user)->get(route('applications.open', $app))
            ->assertRedirect('https://fondation.lamajestueuse.cm');
    }

    public function test_une_requete_inertia_recoit_une_navigation_complete(): void
    {
        // C'est le chemin reel du navigateur : la tuile est un lien Inertia,
        // donc le clic part en XHR. Une XHR ne peut pas suivre une redirection
        // hors domaine ; Inertia attend un 409 + X-Inertia-Location.
        $user = User::factory()->create();
        $app = Application::factory()->create(['url' => 'https://ium.lamajestueuse.cm']);
        $user->applications()->attach($app);

        $this->actingAs($user)
            ->withHeaders($this->enTetesInertia())
            ->get(route('applications.open', $app))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://ium.lamajestueuse.cm');
    }

    public function test_un_module_reste_une_redirection_ordinaire(): void
    {
        $user = User::factory()->create();
        $module = Application::factory()->module()->create();
        $user->applications()->attach($module);

        $this->actingAs($user)
            ->withHeaders($this->enTetesInertia())
            ->get(route('applications.open', $module))
            ->assertRedirect(route('informations.index'));
    }

    public function test_ouverture_refusee_sans_acces(): void
    {
        $user = User::factory()->create();
        $app = Application::factory()->create();

        $this->actingAs($user)->get(route('applications.open', $app))->assertForbidden();
    }

    public function test_ouverture_impossible_si_lapplication_est_desactivee(): void
    {
        $user = User::factory()->create();
        $app = Application::factory()->inactive()->create();
        $user->applications()->attach($app);

        $this->actingAs($user)->get(route('applications.open', $app))->assertNotFound();
    }

    public function test_louverture_est_comptee_et_journalisee(): void
    {
        $user = User::factory()->create();
        $app = Application::factory()->create();
        $user->applications()->attach($app);

        $this->actingAs($user)->get(route('applications.open', $app));
        $this->actingAs($user)->get(route('applications.open', $app));

        $pivot = $user->applications()->first()->pivot;

        $this->assertSame(2, (int) $pivot->opens_count);
        $this->assertNotNull($pivot->last_opened_at);
        $this->assertDatabaseCount('access_logs', 2);
        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'application_id' => $app->id,
            'action' => 'open',
        ]);
    }
}
