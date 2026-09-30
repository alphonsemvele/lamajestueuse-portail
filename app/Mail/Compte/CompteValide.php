<?php

namespace App\Mail\Compte;

use App\Mail\CourrielDuPortail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Le compte est ouvert : la personne peut se connecter. */
class CompteValide extends CourrielDuPortail
{
    public function __construct(
        public readonly string $nom,
        public readonly ?string $matricule = null,
        public readonly array $applications = [],
    ) {}

    public static function exemple(): static
    {
        return new static('Claire NKOA', 'LM-260147', ['IUM', 'Annuaire', 'Badges']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre compte La Majestueuse est ouvert');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.compte.valide');
    }
}
