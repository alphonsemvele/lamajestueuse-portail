<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'image', 'type',
        'is_featured', 'is_visible', 'published_at', 'views', 'author_id',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_visible' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
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
