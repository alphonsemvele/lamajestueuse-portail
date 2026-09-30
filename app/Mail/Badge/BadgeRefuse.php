<?php

namespace App\Mail\Badge;

use App\Mail\CourrielDuPortail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** La demande de badge est refusee, avec son motif. */
class BadgeRefuse extends CourrielDuPortail
{
    public function __construct(
        public readonly string $nom,
        public readonly string $numero,
        public readonly ?string $motif = null,
    ) {}

    public static function exemple(): static
    {
        return new static('Claire NKOA', 'BDG-000147', 'La photo jointe est trop sombre pour être imprimée.');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre demande de badge '.$this->numero);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.badge.refuse');
    }
}
