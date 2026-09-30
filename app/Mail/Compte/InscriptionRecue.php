<?php

namespace App\Mail\Compte;

use App\Mail\CourrielDuPortail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Accuse de reception : l'inscription attend la validation d'un administrateur. */
class InscriptionRecue extends CourrielDuPortail
{
    public function __construct(
        public readonly string $nom,
        public readonly array $instituts = [],
    ) {}

    public static function exemple(): static
    {
        return new static('Claire NKOA', ['IUM', 'IFPM']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre inscription au portail La Majestueuse');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.compte.inscription-recue');
    }
}
