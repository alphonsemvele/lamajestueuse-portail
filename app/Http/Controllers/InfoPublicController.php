<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Rubrique;
use App\Support\VignettePartage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LA MAJESTUEUSE Info, cote public : la salle de presse du groupe, ouverte a
 * tous, sans compte.
 *
 * Les pages portent leurs propres metadonnees (`meta`) : le gabarit Blade les
 * imprime dans l'en-tete, car WhatsApp et Facebook lisent le HTML brut et
 * n'executent aucun script. C'est a cette condition qu'un lien partage montre
 * son image.
 */
class InfoPublicController extends Controller
{
    private const PAR_PAGE = 9;

    /**
     * La vignette de la salle de presse, a defaut de visuel propre a
     * l'article : 1200 x 630, le format qu'attendent WhatsApp et Facebook
     * pour afficher une grande image plutot qu'un timbre-poste.
     */
    private const VIGNETTE = 'images/info-partage.png';

    public function index(Request $request): Response
    {
        $recherche = trim((string) $request->query('q'));
        $rubrique = Rubrique::active()->where('slug', $request->query('rubrique'))->first();

        /*
         * La une : le dernier article mis en avant, et seulement sur la
         * premiere page sans filtre — ailleurs, elle ferait doublon.
         */
        $laUne = null;

        if ($rubrique === null && $recherche === '' && $request->integer('page', 1) <= 1) {
            $laUne = Post::query()
                ->publique()
                ->with(['author', 'rubrique'])
                ->where('is_featured', true)
                ->orderByDesc('published_at')
                ->first();
        }

        $articles = Post::query()
            ->publique()
            ->with(['author', 'rubrique'])
            // Deja en haut de page, en grand : inutile de le remontrer.
            ->when($laUne, fn ($requete) => $requete->whereKeyNot($laUne->id))
            ->when($rubrique, fn ($requete) => $requete->where('rubrique_id', $rubrique->id))
            ->when($recherche !== '', fn ($requete) => $requete->where(
                fn ($sous) => $sous->where('title', 'like', "%{$recherche}%")
                    ->orWhere('excerpt', 'like', "%{$recherche}%")
                    ->orWhere('body', 'like', "%{$recherche}%")
            ))
            ->orderByDesc('published_at')
            ->paginate(self::PAR_PAGE)
            ->withQueryString()
            ->through(fn (Post $article) => $article->toCarteArray());

        return Inertia::render('info/index', [
            'articles' => $articles,
            'laUne' => $laUne?->toCarteArray(),
            'rubriques' => $this->rubriques(),
            'filtres' => ['q' => $recherche, 'rubrique' => $rubrique?->slug],
            'meta' => $this->meta(
                titre: 'LA MAJESTUEUSE Info',
                description: "Toute l'actualité du groupe La Majestueuse : IUM, IFPM, GSBM.",
                image: asset(self::VIGNETTE),
                url: route('info.index'),
            ),
        ]);
    }

    public function show(Request $request, Post $post): Response
    {
        abort_unless($post->estPublique(), 404);

        $post->load(['author', 'rubrique', 'medias']);
        $post->enregistrerVue($request);

        return Inertia::render('info/article', [
            'article' => $post->toArticleArray(),
            'rubriques' => $this->rubriques(),
            'aLire' => $this->aLire($post),
            'meta' => $this->meta(
                titre: $post->title,
                description: $post->chapeau(),
                // Pas l'image d'origine : une copie au format des reseaux.
                // Une affiche de 650 Ko ne franchit pas le filtre de WhatsApp.
                image: VignettePartage::pour($post, asset(self::VIGNETTE)),
                url: route('info.article', $post),
                type: 'article',
                publieLe: $post->published_at?->toIso8601String(),
            ),
        ]);
    }

    /**
     * Compte un partage. Le bouton ouvre le reseau de son cote : ici on ne
     * fait que tenir le compte, et une erreur ne doit rien casser.
     */
    public function partager(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->estPublique(), 404);

        $reseau = (string) $request->input('reseau');

        abort_unless(array_key_exists($reseau, Post::PARTAGES), 422, 'Réseau inconnu.');

        $post->compterPartage($reseau);

        return back();
    }

    /**
     * Trois articles a lire ensuite : la meme rubrique d'abord, le reste
     * ensuite — une page d'article ne doit jamais etre un cul-de-sac.
     *
     * @return array<int, array<string, mixed>>
     */
    private function aLire(Post $post): array
    {
        return Post::query()
            ->publique()
            ->with(['author', 'rubrique'])
            ->whereKeyNot($post->id)
            ->orderByRaw('CASE WHEN rubrique_id = ? THEN 0 ELSE 1 END', [$post->rubrique_id])
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Post $article) => $article->toCarteArray())
            ->all();
    }

    /**
     * Les rubriques qui ont quelque chose a montrer, avec leur compte.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rubriques(): array
    {
        return Rubrique::active()
            ->ordonnee()
            ->withCount(['posts as articles_count' => fn ($requete) => $requete->publique()])
            ->get()
            ->filter(fn (Rubrique $rubrique) => $rubrique->articles_count > 0)
            ->map(fn (Rubrique $rubrique) => $rubrique->toUiArray())
            ->values()
            ->all();
    }

    /**
     * Les dimensions reelles du visuel, quand il est chez nous.
     *
     * Les annoncer aide WhatsApp a decider d'une grande vignette ; les
     * inventer produit l'inverse, une vignette de travers.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function dimensions(string $url): array
    {
        $racineStockage = Storage::disk('public')->url('');
        $chemin = null;

        if (str_starts_with($url, $racineStockage)) {
            $chemin = Storage::disk('public')->path(Str::after($url, $racineStockage));
        } elseif (str_starts_with($url, asset(''))) {
            $chemin = public_path(Str::after($url, asset('')));
        }

        if ($chemin === null || ! is_file($chemin)) {
            return [null, null];
        }

        $taille = @getimagesize($chemin);

        return $taille ? [(int) $taille[0], (int) $taille[1]] : [null, null];
    }

    /**
     * Les metadonnees de partage, telles que le gabarit Blade les imprime.
     *
     * @return array<string, mixed>
     */
    private function meta(
        string $titre,
        string $description,
        string $image,
        string $url,
        string $type = 'website',
        ?string $publieLe = null,
    ): array {
        [$largeur, $hauteur] = $this->dimensions($image);

        return [
            'titre' => $titre,
            'description' => Str::limit(trim(preg_replace('/\s+/', ' ', $description)), 200),
            'image' => $image,
            'imageLargeur' => $largeur,
            'imageHauteur' => $hauteur,
            'url' => $url,
            'type' => $type,
            'publieLe' => $publieLe,
            // Ces pages-la, et elles seules, ont vocation a etre indexees.
            'indexable' => true,
            'site' => 'LA MAJESTUEUSE Info',
        ];
    }
}
