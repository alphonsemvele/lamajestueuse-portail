<?php

namespace App\Mail\Compte;

use App\Mail\CourrielDuPortail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Un compte a ete ouvert pour la personne par le service du personnel.
 *
 * Le mot de passe provisoire ne voyage pas par courriel : il est remis de
 * la main a la main, et le message le dit.
 */
class CompteCree extends CourrielDuPortail
{
    public function __construct(
        public readonly string $nom,
        public readonly string $identifiant,
        public readonly ?string $matricule = null,
        public readonly ?string $institut = null,
    ) {}

    public static function exemple(): static
    {
        return new static('Claire NKOA', 'claire.nkoa@lamajestueuse.cm', 'LM-260147', 'IUM');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Un compte a été ouvert à votre nom');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.compte.cree');
    }
}
