<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un profil rassemble ce qui se repete : l'echelon de reference, les
 * indemnites et les retenues qui vont avec. On l'attache a un contrat.
 */
class ProfilSalaire extends Model
{
    protected $table = 'profils_salaire';

    protected $fillable = ['nom', 'description', 'categorie_rh_id', 'echelon_id', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieRh::class, 'categorie_rh_id');
    }

    public function echelon(): BelongsTo
    {
        return $this->belongsTo(Echelon::class);
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class, 'profil_salaire_id');
    }

    public function indemnites(): BelongsToMany
    {
        return $this->belongsToMany(Indemnite::class, 'profil_indemnite')
            ->withPivot(['type_calcul', 'valeur'])->withTimestamps();
    }

    public function retenues(): BelongsToMany
    {
        return $this->belongsToMany(Retenue::class, 'profil_retenue')
            ->withPivot(['type_calcul', 'valeur'])->withTimestamps();
    }

    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'description' => $this->description,
            'categorie' => $this->categorie?->libelle,
            'categorieId' => $this->categorie_rh_id,
            'echelonId' => $this->echelon_id,
            'echelon' => $this->echelon ? $this->echelon->nomComplet() : null,
            'salaireBase' => $this->echelon ? (float) $this->echelon->salaire : 0.0,
            'actif' => (bool) $this->actif,
            'indemnites' => $this->relationLoaded('indemnites') ? $this->indemnites->map(fn ($i) => [
                'id' => $i->id, 'libelle' => $i->libelle,
                'typeCalcul' => $i->pivot->type_calcul, 'valeur' => (float) $i->pivot->valeur,
            ])->all() : null,
            'retenues' => $this->relationLoaded('retenues') ? $this->retenues->map(fn ($r) => [
                'id' => $r->id, 'libelle' => $r->libelle,
                'typeCalcul' => $r->pivot->type_calcul, 'valeur' => (float) $r->pivot->valeur,
            ])->all() : null,
        ];
    }
}
