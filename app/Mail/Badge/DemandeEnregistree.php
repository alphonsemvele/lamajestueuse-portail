<?php

namespace App\Mail\Badge;

use App\Mail\CourrielDuPortail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** La demande de badge est arrivee au service qui les fabrique. */
class DemandeEnregistree extends CourrielDuPortail
{
    public function __construct(
        public readonly string $nom,
        public readonly string $numero,
        public readonly string $nomAffiche,
        public readonly ?string $institut = null,
    ) {}

    public static function exemple(): static
    {
        return new static('Claire NKOA', 'BDG-000147', 'Dr Claire NKOA', 'IUM');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre demande de badge '.$this->numero);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.badge.enregistree');
    }
}
