<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Prime ou retenue exceptionnelle, valable pour un seul mois. */
class Ajustement extends Model
{
    protected $fillable = [
        'contrat_id', 'mois', 'annee', 'type', 'mode', 'libelle', 'montant', 'motif', 'saisi_par',
    ];

    protected $casts = [
        'mois' => 'integer',
        'annee' => 'integer',
        'montant' => 'decimal:2',
    ];

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }

    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'mois' => (int) $this->mois,
            'annee' => (int) $this->annee,
            'type' => $this->type,
            'mode' => $this->mode,
            'libelle' => $this->libelle,
            'montant' => (float) $this->montant,
            'motif' => $this->motif,
        ];
    }
}
