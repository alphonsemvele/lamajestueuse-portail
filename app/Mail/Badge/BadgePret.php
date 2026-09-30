<?php

namespace App\Mail\Badge;

use App\Mail\CourrielDuPortail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Le badge est imprime : il attend d'etre retire. */
class BadgePret extends CourrielDuPortail
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
        return new Envelope(subject: 'Votre badge est prêt');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.badge.pret');
    }
}
