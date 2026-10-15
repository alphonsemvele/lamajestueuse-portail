<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Une piece jointe a une publication : une image de la galerie, ou un
 * document a telecharger.
 */
class PostMedia extends Model
{
    protected $table = 'post_medias';

    protected $fillable = ['post_id', 'genre', 'chemin', 'nom', 'taille', 'mime', 'legende', 'ordre'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->chemin);
    }

    /** Le poids, dit comme on le dit a quelqu'un : « 1,4 Mo ». */
    public function poids(): string
    {
        $octets = (int) $this->taille;

        if ($octets >= 1048576) {
            return number_format($octets / 1048576, 1, ',', ' ').' Mo';
        }

        return max(1, (int) round($octets / 1024)).' Ko';
    }

    /** L'extension, en majuscules : ce qu'on met sur la pastille d'un document. */
    public function extension(): string
    {
        return strtoupper(pathinfo($this->nom, PATHINFO_EXTENSION) ?: 'fichier');
    }

    /**
     * @return array<string, mixed>
     */
    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'genre' => $this->genre,
            'url' => $this->url(),
            'nom' => $this->nom,
            'legende' => $this->legende,
            'poids' => $this->poids(),
            'extension' => $this->extension(),
            'ordre' => $this->ordre,
        ];
    }
}
