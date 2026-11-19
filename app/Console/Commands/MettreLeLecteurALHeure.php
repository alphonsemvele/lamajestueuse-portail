<?php

namespace App\Console\Commands;

use App\Services\LecteurHikvision;
use Illuminate\Console\Command;
use Throwable;

/**
 * Met le lecteur de presence a l'heure du Cameroun.
 *
 * Les appareils sortent d'usine a l'heure de Chine. Tant que le fuseau n'est
 * pas corrige, chaque pointage porte une heure fausse de sept heures : le
 * registre de presence ne veut plus rien dire, et personne ne s'en apercoit
 * avant de le relire.
 */
class MettreLeLecteurALHeure extends Command
{
    protected $signature = 'hikvision:heure
        {--fuseau=Africa/Douala : Le fuseau à poser}
        {--voir : Montre l’heure du lecteur sans y toucher}';

    protected $description = 'Met le lecteur à l’heure du Cameroun';

    public function handle(): int
    {
        $lecteur = LecteurHikvision::depuisLaConfiguration();

        if (! $lecteur->estConfigure()) {
            $this->error('Le lecteur n’est pas configuré.');

            return self::FAILURE;
        }

        try {
            $avant = $lecteur->heure();
            $this->line('Lecteur  : '.($avant['heure'] ?? '?').'  fuseau '.($avant['fuseau'] ?? '?'));

            if ($this->option('voir')) {
                return self::SUCCESS;
            }

            $pose = $lecteur->reglerHeure((string) $this->option('fuseau'));
            $apres = $lecteur->heure();
        } catch (Throwable $souci) {
            $this->error('Le lecteur n’a pas répondu : '.$souci->getMessage());

            return self::FAILURE;
        }

        $this->info('Posé    : '.$pose);
        $this->line('Relu    : '.($apres['heure'] ?? '?').'  fuseau '.($apres['fuseau'] ?? '?'));

        return self::SUCCESS;
    }
}
