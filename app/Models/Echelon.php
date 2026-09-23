<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Echelon extends Model
{
    protected $fillable = [
        'categorie_rh_id', 'numero', 'libelle', 'salaire', 'anciennete_min', 'actif',
    ];

    protected $casts = [
        'numero' => 'integer',
        'salaire' => 'decimal:2',
        'anciennete_min' => 'integer',
        'actif' => 'boolean',
    ];

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieRh::class, 'categorie_rh_id');
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    public function profils(): HasMany
    {
        return $this->hasMany(ProfilSalaire::class);
    }

    public function nomComplet(): string
    {
        return trim(($this->categorie?->libelle ?? '').' · échelon '.$this->numero);
    }

    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'categorieId' => $this->categorie_rh_id,
            'categorie' => $this->categorie?->libelle,
            'numero' => (int) $this->numero,
            'libelle' => $this->libelle,
            'salaire' => (float) $this->salaire,
            'ancienneteMin' => (int) $this->anciennete_min,
            'actif' => (bool) $this->actif,
        ];
    }
}
