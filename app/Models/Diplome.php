<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diplome extends Model
{
    /** Niveaux retenus pour le classement des dossiers. */
    public const NIVEAUX = [
        'CEP', 'BEPC', 'CAP', 'Probatoire', 'BAC', 'BTS/DUT', 'Licence',
        'Master', 'Doctorat', 'Autre',
    ];

    protected $fillable = [
        'agent_id', 'intitule', 'niveau', 'specialite',
        'etablissement', 'annee_obtention', 'piece_fournie',
    ];

    protected $casts = [
        'annee_obtention' => 'integer',
        'piece_fournie' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'intitule' => $this->intitule,
            'niveau' => $this->niveau,
            'specialite' => $this->specialite,
            'etablissement' => $this->etablissement,
            'anneeObtention' => $this->annee_obtention,
            'pieceFournie' => (bool) $this->piece_fournie,
        ];
    }
}
