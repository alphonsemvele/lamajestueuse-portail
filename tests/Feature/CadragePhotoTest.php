<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\DemandeBadge;
use App\Models\User;
use App\Support\Cadrage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Recadrer une photo ne doit jamais l'entamer.
 *
 * Le fichier reste tel qu'il a ete depose ; a cote, trois nombres disent ou
 * regarder. On peut donc recadrer autant de fois qu'on veut, sur une photo
 * deja en place, sans jamais la redeposer ni perdre ce qui depasse du cadre.
 */
class CadragePhotoTest extends TestCase
{
    use RefreshDatabase;

    private function patron(): User
    {
        return User::factory()->superadmin()->create();
    }

    // ------------------------------------------------------------ la lecture

    public function test_un_cadrage_est_borne_a_ce_qui_a_du_sens(): void
    {
        $cadrage = Cadrage::depuis(['x' => 300, 'y' => -40, 'zoom' => 99]);

        $this->assertSame(100.0, $cadrage['x']);
        $this->assertSame(0.0, $cadrage['y']);
        $this->assertSame(Cadrage::ZOOM_MAX, $cadrage['zoom']);
    }

    public function test_un_cadrage_neutre_ne_s_enregistre_pas(): void
    {
        // C'est deja ce que fait l'affichage sans rien : inutile de le garder.
        $this->assertNull(Cadrage::depuis(['x' => 50, 'y' => 50, 'zoom' => 1]));
        $this->assertNull(Cadrage::depuis(null));
        $this->assertNull(Cadrage::depuis('ceci n’est pas un cadrage'));
    }

    public function test_un_cadrage_arrive_aussi_en_texte(): void
    {
        // Les formulaires qui portent un fichier envoient du multipart : le
        // cadrage y voyage en JSON, pas en tableau.
        $cadrage = Cadrage::depuis('{"x":20,"y":30,"zoom":1.5}');

        $this->assertSame(['x' => 20.0, 'y' => 30.0, 'zoom' => 1.5], $cadrage);
    }

    // ------------------------------------------------------------ les comptes

    public function test_recadrer_une_photo_ne_la_remplace_pas(): void
    {
        Storage::fake('public');

        $patron = $this->patron();
        $personne = User::factory()->create(['status' => 'active']);

        $champs = [
            'name' => $personne->name,
            'email' => $personne->email,
            'role' => 'employee',
            'status' => 'active',
            'locale' => 'fr',
        ];

        // Une photo, puis un cadrage, en deux fois.
        $this->actingAs($patron)->put(route('admin.users.update', $personne), $champs + [
            'avatar_file' => UploadedFile::fake()->image('portrait.jpg', 600, 800),
        ])->assertSessionHasNoErrors();

        $photo = $personne->refresh()->avatar;
        $this->assertNotNull($photo);
        $this->assertNull($personne->avatar_cadrage);

        $this->actingAs($patron)->put(route('admin.users.update', $personne), $champs + [
            'avatar_cadrage' => '{"x":30,"y":20,"zoom":1.8}',
        ])->assertSessionHasNoErrors();

        $personne->refresh();

        // Le fichier n'a pas bouge, et il est toujours la.
        $this->assertSame($photo, $personne->avatar);
        Storage::disk('public')->assertExists($photo);

        // Seul le regard a change.
        $this->assertEquals(['x' => 30.0, 'y' => 20.0, 'zoom' => 1.8], $personne->avatar_cadrage);
    }

    public function test_le_cadrage_se_refait_autant_de_fois_qu_on_veut(): void
    {
        Storage::fake('public');

        $patron = $this->patron();
        $personne = User::factory()->create([
            'status' => 'active',
            'avatar' => 'utilisateurs/photos/deja.png',
            'avatar_cadrage' => ['x' => 10.0, 'y' => 10.0, 'zoom' => 2.0],
        ]);

        $this->actingAs($patron)->put(route('admin.users.update', $personne), [
            'name' => $personne->name,
            'email' => $personne->email,
            'role' => 'employee',
            'status' => 'active',
            'locale' => 'fr',
            'avatar_cadrage' => '{"x":70,"y":80,"zoom":1.2}',
        ])->assertSessionHasNoErrors();

        $personne->refresh();

        $this->assertSame('utilisateurs/photos/deja.png', $personne->avatar);
        $this->assertEquals(['x' => 70.0, 'y' => 80.0, 'zoom' => 1.2], $personne->avatar_cadrage);
    }

    // ------------------------------------------------------------- les badges

    public function test_une_demande_de_badge_porte_son_cadrage(): void
    {
        Storage::fake('public');

        $institut = Application::factory()->create(['name' => 'IUM']);
        Application::factory()->module('badges')->create(['name' => 'Badges']);

        $employe = User::factory()->create(['status' => 'active']);
        $employe->applications()->attach($institut);

        $this->actingAs($employe)->post(route('badges.store'), [
            'nom_affiche' => 'Claire NKOA',
            'motif' => 'premiere',
            'application_id' => $institut->id,
            'photo_file' => UploadedFile::fake()->image('portrait.jpg', 600, 800),
            'photo_cadrage' => '{"x":40,"y":25,"zoom":1.6}',
        ])->assertSessionHasNoErrors();

        $demande = DemandeBadge::firstOrFail();

        $this->assertEquals(['x' => 40.0, 'y' => 25.0, 'zoom' => 1.6], $demande->photo_cadrage);
        Storage::disk('public')->assertExists($demande->photo);
    }

    /**
     * Sans photo jointe, le badge reprend celle du compte : c'est donc le
     * cadrage du compte qu'il doit suivre, sans quoi la carte montrerait
     * autre chose que le profil.
     */
    public function test_sans_photo_jointe_le_badge_suit_le_cadrage_du_compte(): void
    {
        $institut = Application::factory()->create(['name' => 'IUM']);
        Application::factory()->module('badges')->create(['name' => 'Badges']);

        $employe = User::factory()->create([
            'status' => 'active',
            'avatar' => 'utilisateurs/photos/moi.png',
            'avatar_cadrage' => ['x' => 35.0, 'y' => 15.0, 'zoom' => 2.2],
        ]);
        $employe->applications()->attach($institut);

        $this->actingAs($employe)->post(route('badges.store'), [
            'nom_affiche' => 'Claire NKOA',
            'motif' => 'premiere',
            'application_id' => $institut->id,
        ])->assertSessionHasNoErrors();

        $demande = DemandeBadge::with('user')->firstOrFail();

        $this->assertNull($demande->photo);
        $this->assertEquals(['x' => 35.0, 'y' => 15.0, 'zoom' => 2.2], $demande->photoCadrage());
        $this->assertSame($demande->photoCadrage(), $demande->toUiArray()['photoCadrage']);
    }

    public function test_le_guichet_recadre_sans_remplacer_la_photo(): void
    {
        Storage::fake('public');

        $institut = Application::factory()->create(['name' => 'IUM']);
        $module = Application::factory()->module('badges')->create(['name' => 'Badges']);

        $guichet = User::factory()->create(['status' => 'active']);
        $guichet->applications()->attach($module, ['role_in_app' => 'accueil', 'roles' => json_encode(['accueil'])]);

        $employe = User::factory()->create(['status' => 'active']);
        $employe->applications()->attach($institut);

        $demande = DemandeBadge::create([
            'numero' => 'BDG-000001', 'user_id' => $employe->id, 'application_id' => $institut->id,
            'nom_affiche' => 'Claire NKOA', 'modele' => 'classique', 'motif' => 'premiere',
            'statut' => 'en_attente', 'photo' => 'badges/photos/claire.png',
        ]);

        $this->actingAs($guichet)->post(route('badges.modifier', $demande), [
            'nom_affiche' => 'Claire NKOA',
            'application_id' => $institut->id,
            'photo_cadrage' => '{"x":55,"y":10,"zoom":2}',
        ])->assertSessionHasNoErrors();

        $demande->refresh();

        $this->assertSame('badges/photos/claire.png', $demande->photo);
        $this->assertEquals(['x' => 55.0, 'y' => 10.0, 'zoom' => 2.0], $demande->photo_cadrage);
    }
}
