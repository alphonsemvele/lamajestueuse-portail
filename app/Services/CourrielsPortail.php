<?php

namespace App\Services;

use App\Mail\CourrielDuPortail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoi des courriels du portail.
 *
 * Un relais injoignable ne doit jamais faire echouer la procedure elle-meme :
 * une inscription reste enregistree, un compte reste valide, meme si le
 * message ne part pas. L'echec est consigne dans le journal, et l'ecran des
 * reglages e-mail sert a le diagnostiquer.
 */
class CourrielsPortail
{
    /** Envoie a une personne, si elle a une adresse. */
    public function envoyerA(?User $destinataire, CourrielDuPortail $courriel): bool
    {
        if (! $destinataire || blank($destinataire->email)) {
            return false;
        }

        return $this->envoyer($destinataire->email, $courriel);
    }

    public function envoyer(string $adresse, CourrielDuPortail $courriel): bool
    {
        try {
            Mail::to($adresse)->send($courriel);

            return true;
        } catch (Throwable $e) {
            Log::warning("Courriel non envoyé", [
                'courriel' => $courriel::class,
                'destinataire' => $adresse,
                'erreur' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
