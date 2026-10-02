<?php

namespace App\Mail\Paie;

use App\Mail\CourrielDuPortail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Le salaire du mois est verse : le bulletin attend dans le portail.
 *
 * Le message ne porte aucun montant. Une boite mail se consulte sur un
 * telephone partage, se transfere par erreur et dort chez un fournisseur :
 * la remuneration reste derriere la connexion, le courriel ne fait que
 * prevenir.
 */
class BulletinDisponible extends CourrielDuPortail
{
    public function __construct(
        public readonly string $nom,
        public readonly string $periode,
        public readonly ?string $employeur = null,
    ) {}

    public static function exemple(): static
    {
        return new static('Claire NKOA', 'septembre 2026', 'Institut Universitaire de la Majestueuse');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre bulletin de '.$this->periode.' est disponible');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.paie.disponible');
    }
}
