<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une rubrique de LA MAJESTUEUSE Info : ce qui range les articles sur le site
 * public et dans la console de redaction.
 *
 * @see database/migrations/2026_10_15_100000_la_majestueuse_info.php
 */
class Rubrique extends Model
{
    /** Les familles historiques, qui garnissent encore posts.type. */
    public const FAMILLES = [
        'news' => 'Actualité',
        'announcement' => 'Annonce',
        'billboard' => 'Affichage',
    ];

    protected $fillable = ['nom', 'slug', 'description', 'couleur', 'famille', 'ordre', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    public function scopeOrdonnee(Builder $query): Builder
    {
        return $query->orderBy('ordre')->orderBy('nom');
    }

    /**
     * @return array<string, mixed>
     */
    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'slug' => $this->slug,
            'description' => $this->description,
            'couleur' => $this->couleur,
            'famille' => $this->famille,
            'familleLibelle' => self::FAMILLES[$this->famille] ?? $this->famille,
            'ordre' => $this->ordre,
            'actif' => (bool) $this->actif,
            'articles' => $this->articles_count ?? null,
        ];
    }
}
