<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Socle des courriels du portail.
 *
 * Chaque message sait se construire avec des donnees d'exemple : c'est ce
 * qui permet de le relire et de l'essayer depuis l'administration, sans
 * avoir a reproduire la situation qui le declenche.
 */
abstract class CourrielDuPortail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** Un exemplaire rempli de donnees fictives, pour l'apercu et l'essai. */
    abstract public static function exemple(): static;
}
