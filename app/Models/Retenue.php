<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Retenue extends Model
{
    protected $fillable = ['libelle', 'description', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'description' => $this->description,
            'actif' => (bool) $this->actif,
        ];
    }
}
