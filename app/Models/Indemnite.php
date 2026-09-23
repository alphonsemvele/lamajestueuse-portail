<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Indemnite extends Model
{
    protected $fillable = ['libelle', 'description', 'imposable', 'actif'];

    protected $casts = ['imposable' => 'boolean', 'actif' => 'boolean'];

    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'description' => $this->description,
            'imposable' => (bool) $this->imposable,
            'actif' => (bool) $this->actif,
        ];
    }
}
