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

    /**
     * L'etat d'une piece au dossier.
     *
     * Ce que la RH saisit est vrai par construction : « valide ». Ce que
     * l'interesse depose depuis « Mon profil » attend d'etre verifie.
     */
    public const STATUTS = [
        'en_attente' => 'En attente de validation',
        'valide' => 'Validé',
        'refuse' => 'Refusé',
    ];

    protected $fillable = [
        'agent_id', 'intitule', 'niveau', 'specialite',
        'etablissement', 'annee_obtention', 'piece_fournie',
        'statut', 'soumis_par', 'decide_par', 'decide_le', 'motif_refus',
    ];

    protected $casts = [
        'annee_obtention' => 'integer',
        'piece_fournie' => 'boolean',
        'decide_le' => 'datetime',
    ];

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

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
            'statut' => $this->statut,
            'statutLibelle' => self::STATUTS[$this->statut] ?? $this->statut,
            'soumisParLAgent' => $this->soumis_par !== null,
            'motifRefus' => $this->motif_refus,
            'decideLe' => $this->decide_le?->format('d/m/Y'),
        ];
    }
}
