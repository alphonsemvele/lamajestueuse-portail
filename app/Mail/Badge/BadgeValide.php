<?php

namespace App\Mail\Badge;

use App\Mail\CourrielDuPortail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * La demande est validee : le badge part a la fabrication.
 *
 * C'est le moment ou le porteur peut enfin voir sa carte telle qu'elle sera
 * tiree — le message l'y conduit directement.
 */
class BadgeValide extends CourrielDuPortail
{
    public function __construct(
        public readonly string $nom,
        public readonly string $numero,
        public readonly ?string $institut = null,
    ) {}

    public static function exemple(): static
    {
        return new static('Claire NKOA', 'BDG-000147', 'IUM');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre badge a été validé');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.badge.valide');
    }
}
