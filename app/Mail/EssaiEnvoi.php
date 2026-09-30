<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Message d'essai envoye depuis l'administration, pour verifier que le
 * relais fonctionne avant qu'un employe n'attende son mot de passe en vain.
 */
class EssaiEnvoi extends CourrielDuPortail
{
    public function __construct(public readonly string $demandePar) {}

    public static function exemple(): static
    {
        return new static('Alphonse MVELE');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Essai d’envoi — Portail La Majestueuse');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.essai', with: [
            'demandePar' => $this->demandePar,
            'envoyeLe' => now()->translatedFormat('d F Y à H:i'),
        ]);
    }
}
