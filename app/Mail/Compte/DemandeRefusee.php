<?php

namespace App\Mail\Compte;

use App\Mail\CourrielDuPortail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** La demande d'inscription n'est pas retenue. */
class DemandeRefusee extends CourrielDuPortail
{
    public function __construct(
        public readonly string $nom,
        public readonly ?string $motif = null,
    ) {}

    public static function exemple(): static
    {
        return new static('Claire NKOA', "Le matricule indiqué ne correspond à aucun agent du groupe.");
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre demande d’inscription au portail');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.compte.refusee');
    }
}
