<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Post extends Model
{
    /** Les reseaux ou l'on partage, et la colonne qui les compte. */
    public const PARTAGES = [
        'whatsapp' => 'partages_whatsapp',
        'facebook' => 'partages_facebook',
        'lien' => 'partages_lien',
    ];

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'image', 'type', 'rubrique_id',
        'is_featured', 'is_visible', 'is_public', 'published_at', 'views',
        'author_id', 'cover_legende',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_visible' => 'boolean',
            'is_public' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function rubrique(): BelongsTo
    {
        return $this->belongsTo(Rubrique::class);
    }

    public function medias(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('ordre')->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->medias()->where('genre', 'image');
    }

    public function documents(): HasMany
    {
        return $this->medias()->where('genre', 'document');
    }

    /**
     * Ce que voit un employe : l'interrupteur est ouvert, la date est posee,
     * et elle est passee.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_visible', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Ce que voit un visiteur sans compte : une publication en ligne que son
     * auteur a ouverte au public.
     */
    public function scopePublique(Builder $query): Builder
    {
        return $query->published()->where('is_public', true);
    }

    public function estPublique(): bool
    {
        return $this->is_public && $this->isVisibleToStaff();
    }

    public function isVisibleToStaff(): bool
    {
        return $this->is_visible
            && $this->published_at !== null
            && ! $this->published_at->isFuture();
    }

    /**
     * Etat d'affichage, pour l'etiquette des listes.
     */
    public function visibilityState(): string
    {
        if (! $this->is_visible) {
            return 'hidden';
        }

        if ($this->published_at === null) {
            return 'draft';
        }

        return $this->published_at->isFuture() ? 'scheduled' : 'visible';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, mixed>
     */
    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'image' => $this->image,
            'imageUrl' => $this->imageUrl(),
            'type' => $this->type,
            'isFeatured' => (bool) $this->is_featured,
            'isVisible' => (bool) $this->is_visible,
            'visibility' => $this->visibilityState(),
            'publishedAt' => $this->published_at?->toIso8601String(),
            'publishedAtLabel' => $this->published_at?->translatedFormat('j M Y'),
            'views' => $this->views,
            'author' => $this->relationLoaded('author') && $this->author ? $this->author->fullName() : null,
            'isPublic' => (bool) $this->is_public,
            'coverLegende' => $this->cover_legende,
            'rubriqueId' => $this->rubrique_id,
            'rubrique' => $this->relationLoaded('rubrique') && $this->rubrique ? $this->rubrique->toUiArray() : null,
            'partages' => $this->partagesTotal(),
            'partagesDetail' => [
                'whatsapp' => (int) $this->partages_whatsapp,
                'facebook' => (int) $this->partages_facebook,
                'lien' => (int) $this->partages_lien,
            ],
            'images' => $this->relationLoaded('medias') ? $this->piecesUi('image') : [],
            'documents' => $this->relationLoaded('medias') ? $this->piecesUi('document') : [],
        ];
    }

    /**
     * Les pieces d'un genre, prises dans la collection deja chargee : une
     * seule requete pour la galerie et les documents.
     *
     * @return array<int, array<string, mixed>>
     */
    public function piecesUi(string $genre): array
    {
        return $this->medias
            ->where('genre', $genre)
            ->map->toUiArray()
            ->values()
            ->all();
    }

    /**
     * Compte une lecture, une fois par visiteur et par jour.
     *
     * L'empreinte ne garde personne : c'est une somme de controle de
     * l'adresse et du navigateur, qui change chaque jour et ne permet de
     * remonter a rien.
     */
    public function enregistrerVue(Request $request): bool
    {
        $empreinte = hash('sha256', implode('|', [
            $request->ip(),
            (string) $request->userAgent(),
            now()->toDateString(),
            (string) $this->id,
        ]));

        // Insertion ignoree si l'empreinte est deja la : pas de course.
        $nouvelle = DB::table('post_vues')->insertOrIgnore([
            'post_id' => $this->id,
            'empreinte' => $empreinte,
            'created_at' => now(),
        ]);

        if ($nouvelle) {
            $this->increment('views');
        }

        return (bool) $nouvelle;
    }

    /** Compte un partage vers un reseau. */
    public function compterPartage(string $reseau): void
    {
        $colonne = self::PARTAGES[$reseau] ?? null;

        if ($colonne !== null) {
            $this->increment($colonne);
        }
    }

    public function partagesTotal(): int
    {
        return (int) $this->partages_whatsapp + (int) $this->partages_facebook + (int) $this->partages_lien;
    }

    /** Le chapeau, ou a defaut les premieres lignes du corps. */
    public function chapeau(): string
    {
        return $this->excerpt ?: Str::limit(trim(preg_replace('/\s+/', ' ', (string) $this->body)), 180);
    }

    /** « 3 min de lecture » : 200 mots la minute, arrondi vers le haut. */
    public function tempsDeLecture(): int
    {
        return max(1, (int) ceil(str_word_count((string) $this->body) / 200));
    }

    /**
     * La carte d'un article dans une liste publique.
     *
     * @return array<string, mixed>
     */
    public function toCarteArray(): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->title,
            'slug' => $this->slug,
            'chapeau' => $this->chapeau(),
            'imageUrl' => $this->imageUrl(),
            'rubrique' => $this->relationLoaded('rubrique') && $this->rubrique
                ? ['nom' => $this->rubrique->nom, 'slug' => $this->rubrique->slug, 'couleur' => $this->rubrique->couleur]
                : null,
            'aLaUne' => (bool) $this->is_featured,
            'publieLe' => $this->published_at?->toIso8601String(),
            'publieLeLibelle' => $this->published_at?->translatedFormat('j F Y'),
            'auteur' => $this->relationLoaded('author') && $this->author ? $this->author->fullName() : null,
            'vues' => (int) $this->views,
            'partages' => $this->partagesTotal(),
            'lecture' => $this->tempsDeLecture(),
        ];
    }

    /**
     * L'article entier, tel que la page publique l'affiche.
     *
     * @return array<string, mixed>
     */
    public function toArticleArray(): array
    {
        return $this->toCarteArray() + [
            'corps' => $this->body,
            'legende' => $this->cover_legende,
            'images' => $this->piecesUi('image'),
            'documents' => $this->piecesUi('document'),
            'partagesDetail' => [
                'whatsapp' => (int) $this->partages_whatsapp,
                'facebook' => (int) $this->partages_facebook,
                'lien' => (int) $this->partages_lien,
            ],
        ];
    }

    /**
     * Trois provenances : URL externe, visuel livre avec le projet
     * (public/images/...) ou fichier televerse (disque public).
     */
    public function imageUrl(): ?string
    {
        if (blank($this->image)) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        if (str_starts_with($this->image, 'images/')) {
            return asset($this->image);
        }

        return Storage::disk('public')->url($this->image);
    }
}
