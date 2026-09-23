<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dossier administratif d'un membre du personnel. L'identite (nom, matricule,
 * photo, contacts) reste sur le compte du portail ; on ne garde ici que ce qui
 * releve de la gestion du personnel.
 */
class Agent extends Model
{
    protected $fillable = [
        'user_id', 'date_naissance', 'lieu_naissance', 'situation_familiale',
        'enfants', 'cni', 'numero_cnps', 'adresse', 'urgence_nom',
        'urgence_telephone', 'observations',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'enfants' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diplomes(): HasMany
    {
        return $this->hasMany(Diplome::class)->orderByDesc('annee_obtention');
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class)->orderByDesc('date_debut');
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(EvenementCarriere::class)->orderByDesc('date_evenement');
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }

    /**
     * Restreint la liste au perimetre d'un gestionnaire. Un dossier tout juste
     * ouvert n'a pas encore de contrat : il reste visible, sinon il
     * disparaitrait avant meme d'avoir ete complete.
     *
     * @param  array<int, int>|null  $employeurs  null : aucune limite
     */
    public function scopeDuPerimetre(Builder $query, ?array $employeurs): Builder
    {
        if ($employeurs === null) {
            return $query;
        }

        return $query->where(fn ($sub) => $sub
            ->whereHas('contrats', fn ($c) => $c->whereIn('employeur_id', $employeurs))
            ->orWhereDoesntHave('contrats'));
    }

    /** L'agent entre-t-il dans le perimetre donne ? */
    public function dansLePerimetre(?array $employeurs): bool
    {
        if ($employeurs === null) {
            return true;
        }

        return ! $this->contrats()->exists()
            || $this->contrats()->whereIn('employeur_id', $employeurs)->exists();
    }

    public function contratsActifs()
    {
        return $this->contrats()->where('statut', 'actif');
    }

    /** Anciennete dans le groupe, en annees, depuis le premier contrat. */
    public function anciennete(): ?int
    {
        $debut = $this->contrats()->min('date_debut');

        return $debut ? (int) now()->diffInYears($debut) : null;
    }

    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'nom' => $this->user?->fullName(),
            'matricule' => $this->user?->matricule,
            'email' => $this->user?->email,
            'telephone' => $this->user?->phone,
            'photoUrl' => $this->user?->avatarUrl(),
            'initiales' => $this->user?->initials(),
            'dateNaissance' => $this->date_naissance?->format('Y-m-d'),
            'lieuNaissance' => $this->lieu_naissance,
            'situationFamiliale' => $this->situation_familiale,
            'enfants' => (int) $this->enfants,
            'cni' => $this->cni,
            'numeroCnps' => $this->numero_cnps,
            'adresse' => $this->adresse,
            'urgenceNom' => $this->urgence_nom,
            'urgenceTelephone' => $this->urgence_telephone,
            'observations' => $this->observations,
            'anciennete' => $this->anciennete(),
        ];
    }
}
