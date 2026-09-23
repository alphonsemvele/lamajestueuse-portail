<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un employeur par institut : c'est lui qui declare a la CNPS et qui signe
 * les bulletins. Le groupe en compte plusieurs, un agent peut en servir deux.
 */
class Employeur extends Model
{
    protected $fillable = [
        'nom', 'sigle', 'application_id', 'niu', 'numero_cnps',
        'banque', 'compte_bancaire', 'signataire', 'actif',
    ];

    protected $casts = ['actif' => 'boolean'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    /** Gestionnaires RH autorises sur cet employeur. */
    public function gestionnaires(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }

    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'sigle' => $this->sigle,
            'niu' => $this->niu,
            'numeroCnps' => $this->numero_cnps,
            'banque' => $this->banque,
            'compteBancaire' => $this->compte_bancaire,
            'signataire' => $this->signataire,
            'actif' => (bool) $this->actif,
            'contratsActifs' => $this->contrats_actifs_count ?? null,
        ];
    }
}
