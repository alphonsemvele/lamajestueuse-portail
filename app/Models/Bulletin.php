<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bulletin d'un contrat pour un mois donne. Le detail est fige au calcul :
 * un bulletin edite ne change plus si le referentiel evolue ensuite.
 */
class Bulletin extends Model
{
    protected $fillable = [
        'contrat_id', 'employeur_id', 'agent_id', 'mois', 'annee',
        'salaire_base', 'total_indemnites', 'total_retenues', 'salaire_net',
        'detail', 'statut', 'valide_par', 'valide_le', 'paye_par', 'paye_le', 'note',
    ];

    protected $casts = [
        'mois' => 'integer',
        'annee' => 'integer',
        'salaire_base' => 'decimal:2',
        'total_indemnites' => 'decimal:2',
        'total_retenues' => 'decimal:2',
        'salaire_net' => 'decimal:2',
        'detail' => 'array',
        'valide_le' => 'datetime',
        'paye_le' => 'datetime',
    ];

    public const MOIS = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }

    public function employeur(): BelongsTo
    {
        return $this->belongsTo(Employeur::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function periode(): string
    {
        return (self::MOIS[$this->mois] ?? '?').' '.$this->annee;
    }

    /** Un bulletin paye ne se recalcule plus. */
    public function estFige(): bool
    {
        return $this->statut === 'paye';
    }

    /**
     * Les bulletins dont le titulaire existe encore.
     *
     * Un compte supprime laisse derriere lui son dossier et ses bulletins —
     * l'hebergement n'applique pas les cles etrangeres. Ces bulletins ne
     * designent plus personne : ils n'ont rien a faire a l'ecran.
     */
    public function scopeAvecTitulaire(Builder $query): Builder
    {
        return $query->whereHas('agent.user');
    }

    /**
     * Le dossier du bulletin, ou a defaut celui de son contrat.
     *
     * Un bulletin peut avoir perdu son lien direct au dossier ; l'identite
     * ne doit pas disparaitre de l'ecran pour autant, puisque le contrat la
     * porte aussi.
     */
    public function dossier(): ?Agent
    {
        return $this->agent ?? $this->contrat?->agent;
    }

    public function toUiArray(): array
    {
        $agent = $this->dossier();

        return [
            'id' => $this->id,
            'contratId' => $this->contrat_id,
            'agentId' => $agent?->id,
            'userId' => $agent?->user_id,
            'agent' => $agent?->user?->fullName(),
            'matricule' => $agent?->user?->matricule,
            'employeur' => $this->employeur?->sigle,
            'poste' => $this->contrat?->poste,
            'mois' => (int) $this->mois,
            'annee' => (int) $this->annee,
            'periode' => $this->periode(),
            'salaireBase' => (float) $this->salaire_base,
            'totalIndemnites' => (float) $this->total_indemnites,
            'totalRetenues' => (float) $this->total_retenues,
            'salaireNet' => (float) $this->salaire_net,
            'detail' => $this->detail ?? ['indemnites' => [], 'retenues' => []],
            'statut' => $this->statut,
            'note' => $this->note,
            'valideLe' => $this->valide_le?->format('d/m/Y'),
            'payeLe' => $this->paye_le?->format('d/m/Y'),
        ];
    }
}
