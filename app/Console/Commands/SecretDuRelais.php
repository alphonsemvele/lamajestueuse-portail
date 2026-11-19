<?php

namespace App\Console\Commands;

use App\Models\RelaisLecteur;
use Illuminate\Console\Command;

/**
 * Montre l'identite du relais, pour l'installer sur le poste du site.
 *
 * Le secret ne se lit que la : il n'est nulle part dans le depot, et le
 * portail le garde chiffre.
 */
class SecretDuRelais extends Command
{
    protected $signature = 'lecteur:secret';

    protected $description = 'Affiche l’identité du relais du lecteur, à poser dans le .env du site';

    public function handle(): int
    {
        $relais = RelaisLecteur::first();

        if ($relais === null) {
            $this->error('Aucun relais déclaré.');

            return self::FAILURE;
        }

        $this->line('À poser dans le .env du poste qui parle au lecteur :');
        $this->newLine();
        $this->line('HIKVISION_PORTAIL_URL='.config('app.url'));
        $this->line('HIKVISION_RELAIS_ID='.$relais->client_id);
        $this->line('HIKVISION_RELAIS_SECRET='.$relais->client_secret);
        $this->newLine();
        $this->line($relais->derniere_visite
            ? 'Dernier passage du relais : '.$relais->derniere_visite->diffForHumans()
            : 'Le relais ne s’est encore jamais présenté.');

        return self::SUCCESS;
    }
}
