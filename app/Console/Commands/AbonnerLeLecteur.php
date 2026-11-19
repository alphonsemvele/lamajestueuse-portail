<?php

namespace App\Console\Commands;

use App\Services\JetonRelais;
use App\Services\LecteurHikvision;
use Illuminate\Console\Command;
use Throwable;

/**
 * Abonne le lecteur au portail : chaque passage y part aussitot.
 *
 * L'appareil sait pousser ses evenements vers une adresse web. Il n'y met ni
 * en-tete ni signature : c'est donc un jeton porte dans l'adresse qui
 * l'identifie, et ce jeton se deduit du secret partage — rien de plus a
 * installer que ce secret.
 *
 * C'est le chemin court, celui de l'instantane. Le relais, lui, reste la
 * pour rattraper ce qui manque : un passage pendant une coupure d'internet
 * ne se perd pas, l'appareil le garde en memoire.
 */
class AbonnerLeLecteur extends Command
{
    protected $signature = 'hikvision:abonner
        {--portail= : L’adresse du portail où pousser (par défaut, celle du .env)}
        {--voir : Montre l’abonnement en place, sans y toucher}
        {--retirer : Retire l’abonnement}';

    protected $description = 'Abonne le lecteur au portail : chaque passage y part aussitôt';

    public function handle(): int
    {
        $lecteur = LecteurHikvision::depuisLaConfiguration();

        if (! $lecteur->estConfigure()) {
            $this->error('Le lecteur n’est pas configuré.');

            return self::FAILURE;
        }

        try {
            if ($this->option('voir')) {
                $this->montrer($lecteur->abonnement());

                return self::SUCCESS;
            }

            if ($this->option('retirer')) {
                $lecteur->desabonner();
                $this->info('Abonnement retiré : le lecteur ne pousse plus rien.');

                return self::SUCCESS;
            }

            $jeton = JetonRelais::jetonPointage((string) config('hikvision.portail.secret') ?: null);

            if ($jeton === null) {
                $this->error('Le secret partagé manque : renseignez HIKVISION_RELAIS_SECRET dans le .env.');

                return self::FAILURE;
            }

            $portail = (string) ($this->option('portail') ?: config('hikvision.portail.url'));
            $pose = $lecteur->abonner($portail, '/api/lecteur/pointage/'.$jeton);

            $this->info('Le lecteur pousse désormais vers '.$pose.'.');
            $this->newLine();
            $this->montrer($lecteur->abonnement());
            $this->newLine();
            $this->line('Présentez une carte : la ligne doit apparaître dans Personnel › Présence.');
        } catch (Throwable $souci) {
            $this->error('Le lecteur n’a pas répondu : '.$souci->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $abonnement */
    private function montrer(array $abonnement): void
    {
        $this->line('Hôte        : '.($abonnement['hote'] ?: '— aucun —'));
        $this->line('Protocole   : '.($abonnement['protocole'] ?: '—'));
        $this->line('Port        : '.($abonnement['port'] ?: '—'));
        // L'adresse porte le jeton : on n'en montre que le debut.
        $this->line('Adresse     : '.mb_substr((string) $abonnement['adresse'], 0, 34).'…');
    }
}
