<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Le poste du site qui parle au lecteur a la place du portail.
 *
 * Il n'a ni ecran ni session : il signe ses demandes avec ce secret partage,
 * comme une application du groupe, mais il n'en est pas une — personne ne
 * l'ouvre, et il n'apparait nulle part dans les tuiles.
 */
class RelaisLecteur extends Model
{
    protected $table = 'relais_lecteur';

    protected $fillable = ['nom', 'client_id', 'client_secret', 'derniere_visite'];

    protected $casts = [
        'client_secret' => 'encrypted',
        'derniere_visite' => 'datetime',
    ];

    /** Le relais est-il passe recemment ? */
    public function estPresent(): bool
    {
        return $this->derniere_visite?->gt(now()->subMinutes(5)) ?? false;
    }
}
