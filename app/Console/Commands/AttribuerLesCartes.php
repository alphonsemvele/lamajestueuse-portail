<?php

namespace App\Console\Commands;

use App\Services\LecteurHikvision;
use Illuminate\Console\Command;
use Throwable;

/**
 * Attribue les cartes du personnel sans passer par les menus du lecteur.
 *
 * Sur l'appareil, poser une carte demande de chercher la personne, d'ouvrir
 * sa fiche, de choisir « carte », de presenter la carte, de valider : une
 * minute a chaque fois, et cent vingt fois de suite.
 *
 * Ici, le portail tient la liste et l'ordre. On passe les cartes l'une apres
 * l'autre devant le lecteur, sans rien toucher : a chaque lecture, la carte
 * va se poser sur la personne annoncee, et la suivante s'annonce.
 *
 * L'autre voie — « --fichier » — ne demande meme pas le lecteur : si les
 * numeros sont imprimes sur les cartes, un fichier « matricule;carte » les
 * pose toutes d'un coup.
 */
class AttribuerLesCartes extends Command
{
    protected $signature = 'hikvision:cartes
        {--fichier= : Un CSV « matricule;carte », pour tout poser sans presenter les cartes}
        {--matricule=* : Ne traite que ces matricules}
        {--toutes : Reprend aussi les personnes qui portent deja une carte}
        {--attente=45 : Secondes accordees a chaque carte}';

    protected $description = 'Attribue les cartes d’accès, une présentation par personne';

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

        $attendus = $this->aTraiter($personnes, $cartes);

        if ($attendus === []) {
            $this->info('Tout le monde porte déjà une carte.');

            return self::SUCCESS;
        }

        return $this->option('fichier')
            ? $this->depuisLeFichier($lecteur, (string) $this->option('fichier'), $personnes)
            : $this->uneParUne($lecteur, $attendus);
    }

    /**
     * Qui reste a servir : ceux que le lecteur porte et qui n'ont pas encore
     * de carte, dans l'ordre de leur numero.
     *
     * @param  array<string, string>  $personnes
     * @param  array<string, string>  $cartes
     * @return array<string, string>
     */
    private function aTraiter(array $personnes, array $cartes): array
    {
        $choisis = array_filter((array) $this->option('matricule'));

        $numeros = array_map(
            fn (string $matricule) => LecteurHikvision::numeroDepuisMatricule($matricule),
            $choisis,
        );

        $attendus = [];

        foreach ($personnes as $numero => $nom) {
            if ($numeros !== [] && ! in_array($numero, $numeros, true)) {
                continue;
            }

            if (! $this->option('toutes') && isset($cartes[$numero])) {
                continue;
            }

            $attendus[$numero] = $nom;
        }

        uksort($attendus, 'strnatcmp');

        return $attendus;
    }

    /**
     * Le fichier des cartes : « matricule;carte », une ligne par personne.
     *
     * @param  array<string, string>  $personnes
     */
    private function depuisLeFichier(LecteurHikvision $lecteur, string $chemin, array $personnes): int
    {
        $chemin = str_starts_with($chemin, '~')
            ? (string) getenv('HOME').substr($chemin, 1)
            : $chemin;

        if (! is_readable($chemin)) {
            $this->error("Fichier introuvable ou illisible : {$chemin}");

            return self::FAILURE;
        }

        $fichier = fopen($chemin, 'r');
        $posees = 0;
        $echecs = [];
        $ouEstLaCarte = $this->colonneDeLaCarte($fichier);

        while (($ligne = fgetcsv($fichier, 0, ';')) !== false) {
            $matricule = trim((string) ($ligne[0] ?? ''));
            $carte = trim((string) ($ligne[$ouEstLaCarte] ?? ''));

            // L'en-tete d'un tableur passe ici comme une ligne ordinaire :
            // sans chiffres des deux cotes, ce n'en est pas une.
            $numero = LecteurHikvision::numeroDepuisMatricule($matricule);

            if ($numero === '' || $carte === '' || ! ctype_digit($carte)) {
                continue;
            }

            if (! isset($personnes[$numero])) {
                $echecs[] = [$matricule, $carte, 'inconnu du lecteur'];

                continue;
            }

            try {
                $lecteur->poserCarte($numero, $carte);
                $posees++;
            } catch (Throwable $souci) {
                $echecs[] = [$matricule, $carte, $souci->getMessage()];
            }
        }

        fclose($fichier);

        $this->info("{$posees} carte(s) posée(s).");

        return $this->bilan($echecs);
    }

    /**
     * Dans quelle colonne se trouve le numero de carte.
     *
     * L'export du lecteur porte « Matricule, Nom, Carte » — le meme fichier
     * sert alors aux deux commandes, l'une lisant les identites, l'autre les
     * cartes. Un fichier sans en-tete garde l'ancienne regle : la carte est
     * en deuxieme colonne.
     *
     * @param  resource  $fichier
     */
    private function colonneDeLaCarte($fichier): int
    {
        $depart = ftell($fichier);
        $entete = fgetcsv($fichier, 0, ';');

        if ($entete === false) {
            return 1;
        }

        // Le marqueur d'UTF-8 colle au premier titre.
        $entete[0] = ltrim((string) ($entete[0] ?? ''), "\xEF\xBB\xBF");

        $colonne = collect($entete)->search(
            fn ($titre) => in_array(mb_strtolower(trim((string) $titre)), ['carte', 'card', 'cardno'], true)
        );

        if ($colonne === false) {
            // Pas d'en-tete reconnu : la premiere ligne est une donnee.
            fseek($fichier, $depart);

            return 1;
        }

        return (int) $colonne;
    }

    /**
     * Les cartes passees une par une devant le lecteur.
     *
     * @param  array<string, string>  $attendus
     */
    private function uneParUne(LecteurHikvision $lecteur, array $attendus): int
    {
        $attente = max(5, (int) $this->option('attente'));

        $this->info(count($attendus).' personne(s) sans carte.');
        $this->line('Présentez la carte de chacun devant le lecteur, dans l’ordre annoncé.');
        $this->line('Ctrl-C pour arrêter : ce qui est posé reste posé.');
        $this->newLine();

        $posees = 0;
        $echecs = [];
        $rang = 0;
        $total = count($attendus);

        foreach ($attendus as $numero => $nom) {
            $rang++;
            $this->line(sprintf('[%d/%d] %s — %s', $rang, $total, $numero, $nom ?: 'sans nom'));

            try {
                $carte = $lecteur->capturerCarte($attente);
            } catch (Throwable $souci) {
                $this->error('   '.$souci->getMessage());
                $echecs[] = [$numero, $nom, $souci->getMessage()];

                continue;
            }

            if ($carte === null) {
                $this->warn('   aucune carte présentée — passé');
                $echecs[] = [$numero, $nom, 'aucune carte présentée'];

                continue;
            }

            try {
                $lecteur->poserCarte($numero, $carte);
                $this->line('   <fg=green>carte '.$carte.' posée</>');
                $posees++;
            } catch (Throwable $souci) {
                $this->error('   carte '.$carte.' refusée : '.$souci->getMessage());
                $echecs[] = [$numero, $nom, $souci->getMessage()];
            }
        }

        $this->newLine();
        $this->info("{$posees} carte(s) posée(s).");

        return $this->bilan($echecs);
    }

    /** @param array<int, array{0: string, 1: string, 2: string}> $echecs */
    private function bilan(array $echecs): int
    {
        if ($echecs === []) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn(count($echecs).' restée(s) sans carte :');
        $this->table(['Numéro', 'Nom', 'Motif'], $echecs);
        $this->line('Relancez la commande : elle ne reprend que ceux qui n’ont pas de carte.');

        return self::FAILURE;
    }
}
