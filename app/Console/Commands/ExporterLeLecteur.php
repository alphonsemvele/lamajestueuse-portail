<?php

namespace App\Console\Commands;

use App\Services\LecteurHikvision;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sort du lecteur ce qu'il porte : les personnes et leurs cartes.
 *
 * Le travail d'enrolement — cent vingt cartes passees une a une — ne doit
 * pas rester prisonnier d'un appareil. Le fichier produit se relit tel quel
 * par les deux commandes qui garnissent un lecteur :
 *
 *   hikvision:personnel --fichier=<ce fichier>   (les identites)
 *   hikvision:cartes    --fichier=<ce fichier>   (les cartes)
 *
 * De quoi monter un second lecteur, ou remonter celui-ci apres une remise a
 * zero, sans repasser une seule carte.
 *
 * Les empreintes, elles, ne sortent pas : l'appareil ne les rend pas, et
 * c'est tant mieux — une biometrie n'a pas a circuler en clair.
 */
class ExporterLeLecteur extends Command
{
    protected $signature = 'hikvision:exporter
        {--sortie= : Où écrire le fichier (par défaut, dans les Téléchargements)}
        {--avec-carte : N’exporte que les personnes qui portent une carte}';

    protected $description = 'Exporte le contenu du lecteur : matricules, noms et numéros de carte';

    public function handle(): int
    {
        $lecteur = LecteurHikvision::depuisLaConfiguration();

        if (! $lecteur->estConfigure()) {
            $this->error('Le lecteur n’est pas configuré.');

            return self::FAILURE;
        }

        try {
            $personnes = $lecteur->personnes();
            $cartes = $lecteur->cartes();
        } catch (Throwable $souci) {
            $this->error('Le lecteur n’a pas répondu : '.$souci->getMessage());

            return self::FAILURE;
        }

        if ($personnes === []) {
            $this->warn('Le lecteur ne porte personne.');

            return self::SUCCESS;
        }

        uksort($personnes, 'strnatcmp');

        $chemin = $this->chemin();
        $fichier = fopen($chemin, 'w');

        // Le marqueur d'UTF-8, comme l'export du personnel : sans lui, un
        // tableur rend les accents en charabia.
        fwrite($fichier, "\xEF\xBB\xBF");
        fputcsv($fichier, ['Matricule', 'Nom', 'Carte'], ';');

        $lignes = 0;
        $avecCarte = 0;

        foreach ($personnes as $numero => $nom) {
            $carte = $cartes[$numero] ?? '';

            if ($carte === '' && $this->option('avec-carte')) {
                continue;
            }

            fputcsv($fichier, [$numero, $nom, $carte], ';');
            $lignes++;
            $carte === '' || $avecCarte++;
        }

        fclose($fichier);

        $this->info($lignes.' ligne(s) écrite(s), dont '.$avecCarte.' avec une carte.');
        $this->line('Fichier : '.$chemin);
        $this->newLine();
        $this->line('Pour garnir un autre lecteur, réglez son adresse dans le .env, puis :');
        $this->line('  php artisan hikvision:personnel --fichier='.$chemin);
        $this->line('  php artisan hikvision:cartes    --fichier='.$chemin);
        $this->newLine();
        $this->line('Les empreintes ne sont pas dans ce fichier : l’appareil ne les rend pas.');

        return self::SUCCESS;
    }

    /** Où déposer le fichier : ce qu'on demande, ou les Téléchargements. */
    private function chemin(): string
    {
        $demande = (string) $this->option('sortie');

        if ($demande !== '') {
            return str_starts_with($demande, '~')
                ? (string) getenv('HOME').substr($demande, 1)
                : $demande;
        }

        $dossier = rtrim((string) getenv('HOME'), '/').'/Downloads';

        if (! is_dir($dossier)) {
            $dossier = getcwd() ?: '.';
        }

        return $dossier.'/lecteur-la-majestueuse-'.now()->format('Y-m-d').'.csv';
    }
}
