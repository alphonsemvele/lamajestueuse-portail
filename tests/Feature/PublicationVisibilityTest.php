<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function publication(array $overrides = []): Post
    {
        return Post::create(array_merge([
            'title' => 'Réunion de rentrée',
            'slug' => 'reunion-de-rentree',
            'type' => 'announcement',
            'published_at' => now()->subDay(),
            'is_visible' => true,
        ], $overrides));
    }

    private function employe(): User
    {
        $user = User::factory()->create();
        $user->applications()->attach(Application::factory()->module()->create());

        return $user;
    }

    public function test_une_publication_active_saffiche(): void
    {
        $post = $this->publication();

        $this->actingAs($this->employe())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('announcements', 1));

        $this->actingAs($this->employe())->get(route('posts.show', $post))->assertOk();
    }

    public function test_une_publication_inactive_disparait_partout(): void
    {
        $post = $this->publication(['is_visible' => false]);

        $this->actingAs($this->employe())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('announcements', 0));

        // Son lien direct ne doit plus repondre.
        $this->actingAs($this->employe())->get(route('posts.show', $post))->assertNotFound();
    }

    public function test_masquer_conserve_la_date_de_publication(): void
    {
        $admin = User::factory()->admin()->create();
        $post = $this->publication();
        $date = $post->published_at;

        $this->actingAs($admin)->post(route('admin.posts.visibility', $post))->assertRedirect();

        $post->refresh();
        $this->assertFalse($post->is_visible);
        $this->assertEquals($date, $post->published_at);
    }

    public function test_la_bascule_fonctionne_dans_les_deux_sens(): void
    {
        $admin = User::factory()->admin()->create();
        $post = $this->publication();

        $this->actingAs($admin)->post(route('admin.posts.visibility', $post));
        $this->assertFalse($post->fresh()->is_visible);

        $this->actingAs($admin)->post(route('admin.posts.visibility', $post));
        $this->assertTrue($post->fresh()->is_visible);
    }

    public function test_un_redacteur_bascule_depuis_le_module(): void
    {
        $module = Application::factory()->module()->create();
        $redacteur = User::factory()->create();
        $redacteur->applications()->attach($module, ['role_in_app' => 'redacteur']);
        $post = $this->publication();

        $this->actingAs($redacteur)->post(route('informations.visibility', $post))->assertRedirect();
        $this->assertFalse($post->fresh()->is_visible);
    }

    public function test_un_lecteur_ne_peut_pas_basculer(): void
    {
        $module = Application::factory()->module()->create();
        $lecteur = User::factory()->create();
        $lecteur->applications()->attach($module);
        $post = $this->publication();

        $this->actingAs($lecteur)->post(route('informations.visibility', $post))->assertForbidden();
        $this->assertTrue($post->fresh()->is_visible);
    }

    public function test_les_quatre_etats_sont_distingues(): void
    {
        $this->assertSame('visible', $this->publication(['slug' => 'a'])->visibilityState());
        $this->assertSame('hidden', $this->publication(['slug' => 'b', 'is_visible' => false])->visibilityState());
        $this->assertSame('draft', $this->publication(['slug' => 'c', 'published_at' => null])->visibilityState());
        $this->assertSame('scheduled', $this->publication(['slug' => 'd', 'published_at' => now()->addWeek()])->visibilityState());
    }

    public function test_une_publication_programmee_reste_masquee_jusqua_sa_date(): void
    {
        $post = $this->publication(['published_at' => now()->addDay()]);

        $this->actingAs($this->employe())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('announcements', 0));

        $this->travel(2)->days();

        $this->actingAs($this->employe())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('announcements', 1));
    }

    public function test_le_formulaire_enregistre_letat_choisi(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.posts.store'), [
            'title' => 'Brouillon interne',
            'type' => 'news',
            'published_at' => now()->toDateTimeString(),
            // Case decochee : le champ n'est pas transmis.
        ])->assertRedirect(route('admin.posts.index'));

        $this->assertFalse(Post::firstOrFail()->is_visible);

        $this->actingAs($admin)->post(route('admin.posts.store'), [
            'title' => 'Annonce publique',
            'type' => 'news',
            'published_at' => now()->toDateTimeString(),
            'is_visible' => '1',
        ]);

        $this->assertTrue(Post::where('title', 'Annonce publique')->firstOrFail()->is_visible);
    }
}
