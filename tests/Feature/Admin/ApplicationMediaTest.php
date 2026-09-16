<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationMediaTest extends TestCase
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
            'name' => 'Bibliothèque numérique',
            'url' => 'https://bibliotheque.lamajestueuse.cm',
            'type' => 'application',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_televersement_dune_image_de_couverture_et_dun_logo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.applications.store'), $this->payload([
            'cover_file' => UploadedFile::fake()->image('couverture.jpg', 1200, 600),
            'logo_file' => UploadedFile::fake()->image('logo.png', 256, 256),
        ]))->assertRedirect(route('admin.applications.index'));

        $application = Application::firstOrFail();

        $this->assertStringStartsWith('applications/couvertures/', $application->cover);
        $this->assertStringStartsWith('applications/logos/', $application->logo);
        Storage::disk('public')->assertExists($application->cover);
        Storage::disk('public')->assertExists($application->logo);
        $this->assertStringContainsString('/storage/', $application->coverUrl());
    }

    public function test_un_fichier_qui_nest_pas_une_image_est_refuse(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.applications.create'))
            ->post(route('admin.applications.store'), $this->payload([
                'cover_file' => UploadedFile::fake()->create('facture.pdf', 40, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('cover_file');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_une_couverture_trop_lourde_est_refusee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.applications.create'))
            ->post(route('admin.applications.store'), $this->payload([
                'cover_file' => UploadedFile::fake()->image('enorme.jpg')->size(5000),
            ]))
            ->assertSessionHasErrors('cover_file');
    }

    public function test_remplacer_une_image_efface_le_fichier_precedent(): void
    {
        $admin = User::factory()->admin()->create();
        $application = Application::factory()->create([
            'cover' => UploadedFile::fake()->image('ancienne.jpg')->store('applications/couvertures', 'public'),
        ]);
        $ancienne = $application->cover;

        $this->actingAs($admin)->put(route('admin.applications.update', $application), [
            'name' => $application->name,
            'url' => $application->url,
            'type' => 'application',
            'is_active' => '1',
            'cover_file' => UploadedFile::fake()->image('nouvelle.jpg'),
        ])->assertRedirect(route('admin.applications.index'));

        $application->refresh();

        $this->assertNotSame($ancienne, $application->cover);
        Storage::disk('public')->assertMissing($ancienne);
        Storage::disk('public')->assertExists($application->cover);
    }

    public function test_retirer_une_image_la_supprime_du_disque(): void
    {
        $admin = User::factory()->admin()->create();
        $application = Application::factory()->create([
            'logo' => UploadedFile::fake()->image('logo.png')->store('applications/logos', 'public'),
        ]);
        $chemin = $application->logo;

        $this->actingAs($admin)->put(route('admin.applications.update', $application), [
            'name' => $application->name,
            'url' => $application->url,
            'type' => 'application',
            'is_active' => '1',
            'remove_logo' => '1',
        ])->assertRedirect(route('admin.applications.index'));

        $this->assertNull($application->fresh()->logo);
        Storage::disk('public')->assertMissing($chemin);
    }

    public function test_les_visuels_livres_avec_le_projet_ne_sont_pas_effaces(): void
    {
        $admin = User::factory()->admin()->create();
        $application = Application::factory()->create(['cover' => 'images/apps/ifpm.jpg']);

        // Un chemin sous /public appartient au depot, pas au disque de stockage :
        // le remplacer ne doit jamais tenter de le supprimer.
        $this->actingAs($admin)->put(route('admin.applications.update', $application), [
            'name' => $application->name,
            'url' => $application->url,
            'type' => 'application',
            'is_active' => '1',
            'cover_file' => UploadedFile::fake()->image('nouvelle.jpg'),
        ]);

        $this->assertFileExists(public_path('images/apps/ifpm.jpg'));
        $this->assertStringStartsWith('applications/couvertures/', $application->fresh()->cover);
    }

    public function test_une_url_externe_reste_acceptee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.applications.store'), $this->payload([
            'cover' => 'https://cdn.lamajestueuse.cm/couverture.jpg',
        ]))->assertRedirect(route('admin.applications.index'));

        $application = Application::firstOrFail();

        $this->assertSame('https://cdn.lamajestueuse.cm/couverture.jpg', $application->coverUrl());
    }

    public function test_supprimer_une_application_efface_ses_fichiers(): void
    {
        $admin = User::factory()->admin()->create();
        $application = Application::factory()->create([
            'cover' => UploadedFile::fake()->image('c.jpg')->store('applications/couvertures', 'public'),
            'logo' => UploadedFile::fake()->image('l.png')->store('applications/logos', 'public'),
        ]);
        [$cover, $logo] = [$application->cover, $application->logo];

        $this->actingAs($admin)->delete(route('admin.applications.destroy', $application));

        Storage::disk('public')->assertMissing($cover);
        Storage::disk('public')->assertMissing($logo);
    }
}
