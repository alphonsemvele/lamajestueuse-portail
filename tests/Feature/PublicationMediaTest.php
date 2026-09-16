<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicationMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Journée portes ouvertes',
            'excerpt' => 'Samedi de 9h à 16h.',
            'type' => 'announcement',
            'published_at' => now()->toDateTimeString(),
        ], $overrides);
    }

    public function test_televersement_dune_image_depuis_ladministration(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.posts.store'), $this->payload([
            'image_file' => UploadedFile::fake()->image('affiche.jpg', 1200, 800),
        ]))->assertRedirect(route('admin.posts.index'));

        $post = Post::firstOrFail();

        $this->assertStringStartsWith('publications/', $post->image);
        Storage::disk('public')->assertExists($post->image);
        $this->assertStringContainsString('/storage/', $post->imageUrl());
    }

    public function test_televersement_dune_image_depuis_le_module(): void
    {
        $module = Application::factory()->module()->create();
        $redacteur = User::factory()->create();
        $redacteur->applications()->attach($module, ['role_in_app' => 'redacteur']);

        $this->actingAs($redacteur)->post(route('informations.store'), $this->payload([
            'image_file' => UploadedFile::fake()->image('affiche.png', 900, 600),
        ]))->assertRedirect(route('informations.index'));

        $post = Post::firstOrFail();

        $this->assertStringStartsWith('publications/', $post->image);
        Storage::disk('public')->assertExists($post->image);
    }

    public function test_un_fichier_qui_nest_pas_une_image_est_refuse(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.posts.create'))
            ->post(route('admin.posts.store'), $this->payload([
                'image_file' => UploadedFile::fake()->create('note.pdf', 30, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('image_file');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_remplacer_limage_efface_la_precedente(): void
    {
        $admin = User::factory()->admin()->create();
        $post = Post::create($this->payload([
            'slug' => 'journee-portes-ouvertes',
            'image' => UploadedFile::fake()->image('ancienne.jpg')->store('publications', 'public'),
        ]));
        $ancienne = $post->image;

        $this->actingAs($admin)->put(route('admin.posts.update', $post), $this->payload([
            'image_file' => UploadedFile::fake()->image('nouvelle.jpg'),
        ]))->assertRedirect(route('admin.posts.index'));

        $post->refresh();

        $this->assertNotSame($ancienne, $post->image);
        Storage::disk('public')->assertMissing($ancienne);
        Storage::disk('public')->assertExists($post->image);
    }

    public function test_retirer_limage_la_supprime_du_disque(): void
    {
        $admin = User::factory()->admin()->create();
        $post = Post::create($this->payload([
            'slug' => 'a-retirer',
            'image' => UploadedFile::fake()->image('affiche.jpg')->store('publications', 'public'),
        ]));
        $chemin = $post->image;

        $this->actingAs($admin)->put(route('admin.posts.update', $post), $this->payload([
            'remove_image' => '1',
        ]))->assertRedirect(route('admin.posts.index'));

        $this->assertNull($post->fresh()->image);
        Storage::disk('public')->assertMissing($chemin);
    }

    public function test_les_visuels_livres_avec_le_projet_ne_sont_pas_effaces(): void
    {
        $admin = User::factory()->admin()->create();
        $post = Post::create($this->payload(['slug' => 'depot', 'image' => 'images/news/n1.jpg']));

        $this->actingAs($admin)->put(route('admin.posts.update', $post), $this->payload([
            'image_file' => UploadedFile::fake()->image('nouvelle.jpg'),
        ]));

        $this->assertFileExists(public_path('images/news/n1.jpg'));
        $this->assertStringStartsWith('publications/', $post->fresh()->image);
    }

    public function test_supprimer_une_publication_efface_son_image(): void
    {
        $admin = User::factory()->admin()->create();
        $post = Post::create($this->payload([
            'slug' => 'a-supprimer',
            'image' => UploadedFile::fake()->image('affiche.jpg')->store('publications', 'public'),
        ]));
        $chemin = $post->image;

        $this->actingAs($admin)->delete(route('admin.posts.destroy', $post));

        Storage::disk('public')->assertMissing($chemin);
    }

    public function test_une_url_externe_reste_acceptee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.posts.store'), $this->payload([
            'image' => 'https://cdn.lamajestueuse.cm/affiche.jpg',
        ]))->assertRedirect(route('admin.posts.index'));

        $this->assertSame('https://cdn.lamajestueuse.cm/affiche.jpg', Post::firstOrFail()->imageUrl());
    }
}
