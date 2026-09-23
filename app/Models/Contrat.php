<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Le contrat lie un agent a un employeur : c'est lui qui porte le poste, la
 * quotite et la remuneration. Un agent qui enseigne dans deux instituts a deux
 * contrats, donc deux bulletins.
 */
class Contrat extends Model
{
    /** Natures de contrat, telles qu'elles figurent sur les actes. */
    public const TYPES = [
        'cdi' => 'Contrat à durée indéterminée',
        'cdd' => 'Contrat à durée déterminée',
        'stage' => 'Stage',
        'vacation' => 'Vacation',
    ];

    protected $fillable = [
        'agent_id', 'employeur_id', 'type', 'poste', 'date_debut', 'date_fin',
        'quotite', 'profil_salaire_id', 'echelon_id', 'statut', 'motif_fin', 'observations',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'quotite' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function employeur(): BelongsTo
    {
        return $this->belongsTo(Employeur::class);
    }

    public function profil(): BelongsTo
    {
        return $this->belongsTo(ProfilSalaire::class, 'profil_salaire_id');
    }

    public function echelon(): BelongsTo
    {
        return $this->belongsTo(Echelon::class);
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }

    public function ajustements(): HasMany
    {
        return $this->hasMany(Ajustement::class);
    }

    /** L'echelon du contrat prime sur celui du profil. */
    public function echelonApplique(): ?Echelon
    {
        return $this->echelon ?? $this->profil?->echelon;
    }

    /** Salaire de base, proratise par la quotite. */
    public function salaireBase(): float
    {
        $echelon = $this->echelonApplique();

        if (! $echelon) {
            return 0.0;
        }

        return round((float) $echelon->salaire * max(0, min(100, (int) $this->quotite)) / 100, 2);
    }

    public function estActif(): bool
    {
        return $this->statut === 'actif';
    }

    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'agentId' => $this->agent_id,
            'userId' => $this->agent?->user_id,
            'agent' => $this->agent?->user?->fullName(),
            'employeurId' => $this->employeur_id,
            'employeur' => $this->employeur?->sigle,
            'type' => $this->type,
            'typeLibelle' => self::TYPES[$this->type] ?? $this->type,
            'poste' => $this->poste,
            'dateDebut' => $this->date_debut?->format('Y-m-d'),
            'dateFin' => $this->date_fin?->format('Y-m-d'),
            'quotite' => (int) $this->quotite,
            'profilId' => $this->profil_salaire_id,
            'profil' => $this->profil?->nom,
            'echelonId' => $this->echelon_id,
            'echelon' => $this->echelonApplique()?->nomComplet(),
            'salaireBase' => $this->salaireBase(),
            'statut' => $this->statut,
            'motifFin' => $this->motif_fin,
            'observations' => $this->observations,
        ];
    }
}
