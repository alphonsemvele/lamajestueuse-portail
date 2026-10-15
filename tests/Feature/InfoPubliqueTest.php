<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Post;
use App\Models\Rubrique;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * LA MAJESTUEUSE Info : la salle de presse publique.
 *
 * Un article ouvert au public se lit sans compte, se partage avec son image,
 * et compte ses lectures une fois par visiteur.
 */
class InfoPubliqueTest extends TestCase
{
    use RefreshDatabase;

    private function rubrique(): Rubrique
    {
        return Rubrique::firstOrCreate(
            ['slug' => 'actualites'],
            ['nom' => 'Actualités', 'couleur' => '#7c3aed', 'famille' => 'news', 'ordre' => 1, 'actif' => true],
        );
    }

    private function article(array $attributs = []): Post
    {
        return Post::create($attributs + [
            'title' => 'Rentrée solennelle 2026',
            'slug' => 'rentree-solennelle-2026',
            'excerpt' => 'Le groupe a ouvert son année académique devant huit cents étudiants.',
            'body' => "Premier paragraphe.\n\nSecond paragraphe.",
            'type' => 'news',
            'rubrique_id' => $this->rubrique()->id,
            'is_visible' => true,
            'is_public' => true,
            'published_at' => now()->subDay(),
        ]);
    }

    // ------------------------------------------------------------- la lecture

    public function test_la_salle_de_presse_se_lit_sans_compte(): void
    {
        $this->article();

        $this->get(route('info.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('info/index')
                ->has('articles.data', 1)
                ->where('articles.data.0.titre', 'Rentrée solennelle 2026'));
    }

    public function test_un_article_se_lit_sans_compte(): void
    {
        $article = $this->article();

        $this->get(route('info.article', $article))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('info/article')
                ->where('article.titre', $article->title)
                ->where('article.corps', $article->body));
    }

    public function test_un_article_reserve_au_personnel_reste_hors_du_site_public(): void
    {
        $article = $this->article(['is_public' => false]);

        $this->get(route('info.article', $article))->assertNotFound();
        $this->get(route('info.index'))
            ->assertInertia(fn (Assert $page) => $page->has('articles.data', 0));
    }

    public function test_un_brouillon_n_est_pas_atteignable(): void
    {
        $article = $this->article(['published_at' => null]);

        $this->get(route('info.article', $article))->assertNotFound();
    }

    public function test_un_article_programme_attend_son_heure(): void
    {
        $article = $this->article(['published_at' => now()->addWeek()]);

        $this->get(route('info.article', $article))->assertNotFound();
    }

    // ----------------------------------------------------------- le partage

    /**
     * L'apercu d'un lien sur WhatsApp ou Facebook ne tient qu'a ces balises :
     * les robots lisent le HTML brut, sans executer le moindre script.
     */
    public function test_la_page_porte_les_balises_de_partage(): void
    {
        $article = $this->article(['image' => 'images/marque-la-majestueuse.png']);

        $reponse = $this->get(route('info.article', $article));

        $reponse->assertSee('<meta property="og:title" content="Rentrée solennelle 2026">', false);
        $reponse->assertSee('<meta property="og:type" content="article">', false);
        $reponse->assertSee('<meta property="og:url" content="'.route('info.article', $article).'">', false);
        $reponse->assertSee('property="og:image"', false);
        $reponse->assertSee('name="twitter:card" content="summary_large_image"', false);
        // Le chapeau sert de description : c'est le texte sous la vignette.
        $reponse->assertSee('huit cents étudiants', false);
        // Et cette page-la, contrairement au portail, s'indexe.
        $reponse->assertSee('<meta name="robots" content="index, follow">', false);
    }

    /**
     * WhatsApp renonce a telecharger une image trop lourde et n'affiche
     * alors aucun apercu, la ou Facebook, lui, l'accepte. L'affiche d'un
     * concours fait couramment 650 Ko : on en publie une copie au format.
     */
    public function test_l_image_de_partage_est_une_copie_au_format_des_reseaux(): void
    {
        Storage::fake('public');

        // Une affiche carree, comme celles de l'institut.
        $article = $this->article([
            'image' => UploadedFile::fake()->image('affiche.jpg', 1600, 1600)->store('publications', 'public'),
        ]);

        $reponse = $this->get(route('info.article', $article));

        preg_match('#<meta property="og:image" content="([^"]+)"#', $reponse->getContent(), $trouve);
        $this->assertNotEmpty($trouve, "La page n'annonce aucune image de partage.");

        $vignette = Str::after($trouve[1], Storage::disk('public')->url(''));
        $this->assertStringStartsWith('publications/partage/', $vignette);

        // Le format attendu, et un poids que WhatsApp accepte de charger.
        [$largeur, $hauteur] = getimagesize(Storage::disk('public')->path($vignette));
        $this->assertSame([1200, 630], [$largeur, $hauteur]);
        $this->assertLessThan(300 * 1024, Storage::disk('public')->size($vignette));

        // L'originale n'est pas touchee : c'est elle qui illustre l'article.
        Storage::disk('public')->assertExists($article->image);

        // Et la vignette ne se refabrique pas a chaque visite.
        $horodatage = Storage::disk('public')->lastModified($vignette);
        $this->get(route('info.article', $article));
        $this->assertSame($horodatage, Storage::disk('public')->lastModified($vignette));
    }

    public function test_un_article_sans_image_reprend_la_vignette_du_groupe(): void
    {
        $article = $this->article(['image' => null]);

        $this->get(route('info.article', $article))
            ->assertSee('images/info-partage.png', false);
    }

    public function test_le_portail_reste_hors_des_moteurs(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_un_partage_se_compte(): void
    {
        $article = $this->article();

        $this->post(route('info.partage', $article), ['reseau' => 'whatsapp'])->assertRedirect();
        $this->post(route('info.partage', $article), ['reseau' => 'facebook'])->assertRedirect();
        $this->post(route('info.partage', $article), ['reseau' => 'whatsapp'])->assertRedirect();

        $article->refresh();

        $this->assertSame(2, $article->partages_whatsapp);
        $this->assertSame(1, $article->partages_facebook);
        $this->assertSame(3, $article->partagesTotal());
    }

    public function test_un_reseau_inconnu_est_refuse(): void
    {
        $article = $this->article();

        $this->post(route('info.partage', $article), ['reseau' => 'pigeon'])->assertStatus(422);
    }

    // ----------------------------------------------------------- les lectures

    public function test_une_lecture_ne_se_compte_qu_une_fois_par_visiteur(): void
    {
        $article = $this->article();

        $this->get(route('info.article', $article))->assertOk();
        $this->get(route('info.article', $article))->assertOk();

        $this->assertSame(1, $article->refresh()->views);
    }

    public function test_un_autre_visiteur_compte_pour_une_lecture_de_plus(): void
    {
        $article = $this->article();

        $this->get(route('info.article', $article));
        // Une autre adresse : c'est bien quelqu'un d'autre.
        $this->withServerVariables(['REMOTE_ADDR' => '41.202.219.44'])
            ->get(route('info.article', $article));

        $this->assertSame(2, $article->refresh()->views);
    }

    // ------------------------------------------------------- la redaction

    private function redacteur(): User
    {
        $user = User::factory()->create();
        $user->applications()->attach(
            Application::factory()->module('informations')->create(['name' => 'LA MAJESTUEUSE Info']),
            ['role_in_app' => 'redacteur', 'roles' => json_encode(['redacteur'])],
        );

        return $user;
    }

    public function test_le_responsable_publie_avec_images_et_documents(): void
    {
        Storage::fake('public');

        $this->actingAs($this->redacteur())
            ->post(route('informations.store'), [
                'title' => 'Journée portes ouvertes',
                'excerpt' => 'Trois jours pour découvrir nos filières.',
                'body' => 'Le programme complet.',
                'rubrique_id' => $this->rubrique()->id,
                'published_at' => now()->toDateTimeString(),
                'is_visible' => true,
                'is_public' => true,
                'images' => [
                    UploadedFile::fake()->image('cour.jpg', 1200, 800),
                    UploadedFile::fake()->image('amphi.jpg', 1200, 800),
                ],
                'documents' => [UploadedFile::fake()->create('programme.pdf', 120, 'application/pdf')],
            ])
            ->assertSessionHasNoErrors();

        $article = Post::with('medias')->firstOrFail();

        $this->assertCount(2, $article->medias->where('genre', 'image'));
        $this->assertCount(1, $article->medias->where('genre', 'document'));
        $this->assertSame('programme.pdf', $article->medias->firstWhere('genre', 'document')->nom);

        foreach ($article->medias as $piece) {
            Storage::disk('public')->assertExists($piece->chemin);
        }

        // La rubrique garnit l'ancienne colonne « type » : le tableau de bord
        // classe encore par la.
        $this->assertSame('news', $article->type);
    }

    public function test_un_employe_sans_droit_ne_publie_pas(): void
    {
        $user = User::factory()->create();
        $user->applications()->attach(Application::factory()->module('informations')->create());

        $this->actingAs($user)
            ->post(route('informations.store'), ['title' => 'Tentative', 'rubrique_id' => $this->rubrique()->id])
            ->assertForbidden();
    }

    public function test_une_rubrique_qui_porte_des_articles_ne_se_supprime_pas(): void
    {
        $rubrique = $this->rubrique();
        $this->article();

        $this->actingAs($this->redacteur())
            ->delete(route('informations.rubriques.destroy', $rubrique))
            ->assertSessionHasErrors('rubrique');

        $this->assertDatabaseHas('rubriques', ['id' => $rubrique->id]);
    }

    public function test_retirer_une_piece_efface_aussi_le_fichier(): void
    {
        Storage::fake('public');

        $article = $this->article();
        $piece = $article->medias()->create([
            'genre' => 'image',
            'chemin' => UploadedFile::fake()->image('vue.jpg')->store('publications/'.$article->id, 'public'),
            'nom' => 'vue.jpg',
            'taille' => 2048,
        ]);

        Storage::disk('public')->assertExists($piece->chemin);

        $this->actingAs($this->redacteur())
            ->delete(route('informations.medias.destroy', $piece))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($piece->chemin);
        $this->assertDatabaseMissing('post_medias', ['id' => $piece->id]);
    }

    /**
     * Rien ne doit survivre a un article supprime : ni ses pieces, ni leurs
     * fichiers, ni le compte de ses lectures. La base de production porte
     * des tables qui ne savent pas tenir de cascade.
     */
    public function test_supprimer_un_article_emporte_ses_pieces_et_ses_lectures(): void
    {
        Storage::fake('public');

        $article = $this->article();
        $piece = $article->medias()->create([
            'genre' => 'image',
            'chemin' => UploadedFile::fake()->image('vue.jpg')->store('publications/'.$article->id, 'public'),
            'nom' => 'vue.jpg',
            'taille' => 2048,
        ]);

        $this->get(route('info.article', $article))->assertOk();
        $this->assertDatabaseCount('post_vues', 1);

        $article->delete();

        Storage::disk('public')->assertMissing($piece->chemin);
        $this->assertDatabaseCount('post_medias', 0);
        $this->assertDatabaseCount('post_vues', 0);
    }

    /** Un article public n'a qu'une adresse : celle de la salle de presse. */
    public function test_le_lien_interne_renvoie_vers_la_page_publique(): void
    {
        $article = $this->article();

        $this->actingAs(User::factory()->create())
            ->get(route('posts.show', $article))
            ->assertRedirect(route('info.article', $article));
    }
}
