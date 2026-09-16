<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_lemploye_ne_voit_que_les_applications_qui_lui_sont_attribuees(): void
    {
        $user = User::factory()->create();
        $accessible = Application::factory()->create(['name' => 'Fondation Medicale']);
        $autre = Application::factory()->create(['name' => 'ERP NDAZOA']);

        $user->applications()->attach($accessible);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('apps.0.name', 'Fondation Medicale')
                ->has('apps', 1));
    }

    public function test_les_applications_inactives_sont_masquees(): void
    {
        $user = User::factory()->create();
        $app = Application::factory()->inactive()->create(['name' => 'Application Retiree']);
        $user->applications()->attach($app);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('apps', 0));
    }

    public function test_les_liens_rapides_sont_separes_des_applications_metier(): void
    {
        $user = User::factory()->create();
        $user->applications()->attach(Application::factory()->create(['name' => 'IFPM']));
        $user->applications()->attach(Application::factory()->quickLink()->create(['name' => 'Webmail']));

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('apps', 1)
                ->where('apps.0.name', 'IFPM')
                ->has('quickLinks', 1)
                ->where('quickLinks.0.name', 'Webmail'));
    }

    public function test_la_recherche_filtre_les_applications(): void
    {
        $user = User::factory()->create();
        $user->applications()->attach(Application::factory()->create(['name' => 'IFPM', 'description' => 'Institut']));
        $user->applications()->attach(Application::factory()->create(['name' => 'GSBM', 'description' => 'Ecole']));

        $this->actingAs($user)->get(route('dashboard', ['q' => 'gsbm']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('apps', 1)->where('apps.0.name', 'GSBM'));
    }

    public function test_le_centre_dinformation_ignore_les_brouillons(): void
    {
        $user = User::factory()->create();
        Post::create(['title' => 'Publie', 'slug' => 'publie', 'type' => 'news', 'published_at' => now()->subDay()]);
        Post::create(['title' => 'Brouillon', 'slug' => 'brouillon', 'type' => 'news', 'published_at' => null]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('news', 1)->where('news.0.title', 'Publie'));
    }

    public function test_le_pointage_enregistre_larrivee_puis_la_sortie(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('checkin.store'));
        $this->assertNotNull($user->checkIns()->first()->checked_in_at);

        $this->actingAs($user)->post(route('checkin.store'));
        $this->assertNotNull($user->checkIns()->first()->checked_out_at);
    }
}
