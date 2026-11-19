<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un ordre destine au lecteur de presence, en attente de son relais.
 */
class TacheLecteur extends Model
{
    protected $table = 'taches_lecteur';

    protected $fillable = [
        'action', 'parametres', 'statut', 'resultat', 'erreur', 'demande_par',
        'prise_le', 'terminee_le',
    ];

    protected $casts = [
        'parametres' => 'array',
        'resultat' => 'array',
        'prise_le' => 'datetime',
        'terminee_le' => 'datetime',
    ];

    /** Ce que le relais sait faire. */
    public const ACTIONS = [
        'personnel' => 'Envoyer le personnel',
        'cartes' => 'Attribuer des cartes',
        'lister' => 'Lire le contenu du lecteur',
        'heure' => 'Remettre le lecteur à l’heure',
        'retirer' => 'Retirer des fiches',
    ];

    public const STATUTS = [
        'en_attente' => 'En attente du relais',
        'prise' => 'En cours',
        'faite' => 'Faite',
        'echouee' => 'Échouée',
    ];

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demande_par');
    }

    /** @param Builder<TacheLecteur> $query */
    public function scopeAFaire(Builder $query): void
    {
        $query->where('statut', 'en_attente')->orderBy('id');
    }

    /**
     * Une tache reprise apres un relais interrompu.
     *
     * Un relais qui s'arrete en cours de route laisse sa tache « prise » sans
     * que personne ne la termine : passe un quart d'heure, elle retourne a la
     * file plutot que d'y rester bloquee.
     */
    public static function reprendreLesAbandonnees(): void
    {
        static::where('statut', 'prise')
            ->where('prise_le', '<', now()->subMinutes(15))
            ->update(['statut' => 'en_attente', 'prise_le' => null]);
    }
}
