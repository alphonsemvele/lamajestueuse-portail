<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un passage devant le lecteur : qui, quand, par quel moyen.
 */
class Pointage extends Model
{
    protected $fillable = [
        'numero', 'user_id', 'nom', 'moment', 'sens', 'moyen', 'porte', 'empreinte', 'brut',
    ];

    protected $casts = [
        'moment' => 'datetime',
        'brut' => 'array',
    ];

    /** Ce que le lecteur sait dire d'un passage. */
    public const MOYENS = [
        'carte' => 'Carte',
        'empreinte' => 'Empreinte',
        'visage' => 'Visage',
        'code' => 'Code',
        'autre' => 'Autre',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<Pointage> $query */
    public function scopeDuJour(Builder $query, ?string $jour = null): void
    {
        $query->whereDate('moment', $jour ?: now()->toDateString());
    }

    /**
     * Le nom a afficher : celui du compte s'il existe, sinon celui que le
     * lecteur a annonce, sinon le numero tout court.
     */
    public function nomAffiche(): string
    {
        return $this->user?->nomOfficiel() ?: ($this->nom ?: $this->numero);
    }
}
