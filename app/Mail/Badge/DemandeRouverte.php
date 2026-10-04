<?php

namespace App\Mail\Badge;

use App\Mail\CourrielDuPortail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Le refus est revenu sur sa decision : la demande repart a l'etude.
 *
 * Le porteur avait recu un refus et l'invitation a recommencer : il faut
 * donc le prevenir, sinon il depose une seconde demande pour rien.
 */
class DemandeRouverte extends CourrielDuPortail
{
    public function __construct(
        public readonly string $nom,
        public readonly string $numero,
    ) {}

    public static function exemple(): static
    {
        return new static('Claire NKOA', 'BDG-000147');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre demande de badge est réexaminée');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.badge.rouverte');
    }
}
