<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieRh extends Model
{
    protected $table = 'categories_rh';

    protected $fillable = ['libelle', 'description', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function echelons(): HasMany
    {
        return $this->hasMany(Echelon::class)->orderBy('numero');
    }

    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'description' => $this->description,
            'actif' => (bool) $this->actif,
            'echelons' => $this->relationLoaded('echelons')
                ? $this->echelons->map->toUiArray()->all()
                : null,
        ];
    }
}
