<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvenementCarriere extends Model
{
    /** Ce qui jalonne une carriere, du recrutement au depart. */
    public const TYPES = [
        'recrutement' => 'Recrutement',
        'avancement' => 'Avancement',
        'affectation' => 'Affectation',
        'formation' => 'Formation',
        'conge' => 'Congé',
        'sanction' => 'Sanction',
        'depart' => 'Départ',
    ];

    protected $table = 'evenements_carriere';

    protected $fillable = [
        'agent_id', 'contrat_id', 'date_evenement', 'type', 'libelle', 'details', 'saisi_par',
    ];

    protected $casts = ['date_evenement' => 'date'];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }

    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'agentId' => $this->agent_id,
            'agent' => $this->agent?->user?->fullName(),
            'contratId' => $this->contrat_id,
            'date' => $this->date_evenement?->format('Y-m-d'),
            'typeLibelle' => self::TYPES[$this->type] ?? $this->type,
            'type' => $this->type,
            'libelle' => $this->libelle,
            'details' => $this->details,
            'employeur' => $this->contrat?->employeur?->sigle,
        ];
    }
}
