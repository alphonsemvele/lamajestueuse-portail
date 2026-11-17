<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LecteurHikvision;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Envoie le personnel du groupe dans le lecteur d'empreintes.
 *
 * Le portail pose l'identite — un numero et un nom ; l'empreinte et le
 * badge se posent ensuite sur l'appareil, personne par personne, et
 * viennent se ranger sous le numero deja present. La commande peut donc
 * etre relancee autant de fois qu'on veut : une personne deja connue est
 * mise a jour, jamais dupliquee, et ce qui a ete enrole n'est pas touche.
 *
 * Elle se lance depuis un poste du site : l'adresse du lecteur est privee,
 * le portail en ligne ne l'atteint pas. D'ou les deux sources possibles —
 * la base, quand on travaille sur place, ou le fichier du personnel
 * telecharge depuis le portail (Personnel › Exporter), quand la base vit
 * ailleurs.
 */
class EnregistrerPersonnelDansLecteur extends Command
{
    protected $signature = 'hikvision:personnel
        {--fichier= : Le CSV exporte depuis le portail, au lieu de la base locale}
        {--simuler : Montre ce qui serait envoye, sans rien ecrire dans le lecteur}
        {--matricule=* : Ne traite que ces matricules}
        {--tous : Prend aussi les comptes suspendus ou en attente}';

    protected $description = 'Enregistre le personnel du portail dans le lecteur d’empreintes';

    public function handle(): int
    {
        $lecteur = LecteurHikvision::depuisLaConfiguration();

        // La simulation ne touche pas l'appareil : elle n'a pas besoin de ses
        // identifiants, et sert justement a verifier la liste avant de les poser.
        if (! $lecteur->estConfigure() && ! $this->option('simuler')) {
            $this->error('Le lecteur n’est pas configuré.');
            $this->line('Renseignez HIKVISION_URL et HIKVISION_MOT_DE_PASSE dans le .env, puis relancez.');
            $this->line('Pour voir la liste sans rien envoyer : php artisan hikvision:personnel --simuler');

            return self::FAILURE;
        }

        try {
            $personnes = $this->option('fichier')
                ? $this->depuisLeFichier((string) $this->option('fichier'))
                : $this->depuisLaBase();
        } catch (Throwable $souci) {
            $this->error($souci->getMessage());

            return self::FAILURE;
        }

        if ($personnes->isEmpty()) {
            $this->warn('Personne à envoyer : aucun membre du personnel ne porte de matricule.');

            return self::SUCCESS;
        }

        return $this->option('simuler')
            ? $this->simuler($personnes)
            : $this->envoyer($lecteur, $personnes);
    }

    /**
     * Le personnel du groupe qui porte un matricule. Sans matricule, pas de
     * numero a poser sur l'appareil.
     *
     * @return Collection<int, array{matricule: string, nom: string}>
     */
    private function depuisLaBase(): Collection
    {
        $matricules = array_filter((array) $this->option('matricule'));

        return User::duPersonnel()
            ->whereNotNull('matricule')
            ->where('matricule', '!=', '')
            ->when(! $this->option('tous'), fn ($q) => $q->where('status', 'active'))
            ->when($matricules !== [], fn ($q) => $q->whereIn('matricule', $matricules))
            ->orderBy('matricule')
            ->get()
            ->map(fn (User $p) => ['matricule' => (string) $p->matricule, 'nom' => $p->nomOfficiel()]);
    }

    /**
     * Le fichier du personnel tel que le portail l'exporte : un CSV a
     * point-virgules, en UTF-8, dont les trois premieres colonnes sont le
     * matricule, le nom et le prenom.
     *
     * @return Collection<int, array{matricule: string, nom: string}>
     */
    private function depuisLeFichier(string $chemin): Collection
    {
        $chemin = str_starts_with($chemin, '~')
            ? (string) getenv('HOME').substr($chemin, 1)
            : $chemin;

        if (! is_readable($chemin)) {
            throw new \RuntimeException("Fichier introuvable ou illisible : {$chemin}");
        }

        $matricules = array_filter((array) $this->option('matricule'));
        $lignes = collect();
        $fichier = fopen($chemin, 'r');

        // Le marqueur d'UTF-8 pose par l'export collerait au premier en-tete.
        $entete = fgetcsv($fichier, 0, ';');
        $entete[0] = ltrim((string) ($entete[0] ?? ''), "\xEF\xBB\xBF");

        $colonne = fn (string $cherche) => collect($entete)
            ->search(fn ($titre) => mb_strtolower(trim((string) $titre)) === $cherche);

        $ouEstLeMatricule = $colonne('matricule');
        $ouEstLeNom = $colonne('nom');
        $ouEstLePrenom = $colonne('prénom');

        if ($ouEstLeMatricule === false || $ouEstLeNom === false) {
            fclose($fichier);

            throw new \RuntimeException(
                'Ce fichier n’a pas les colonnes attendues. Prenez celui du portail : Personnel › Exporter.'
            );
        }

        while (($ligne = fgetcsv($fichier, 0, ';')) !== false) {
            $matricule = trim((string) ($ligne[$ouEstLeMatricule] ?? ''));
            $nom = trim((string) ($ligne[$ouEstLeNom] ?? ''));
            $prenom = $ouEstLePrenom === false ? '' : trim((string) ($ligne[$ouEstLePrenom] ?? ''));

            if ($matricule === '' || ($matricules !== [] && ! in_array($matricule, $matricules, true))) {
                continue;
            }

            $lignes->push(['matricule' => $matricule, 'nom' => trim($nom.' '.$prenom)]);
        }

        fclose($fichier);

        return $lignes->sortBy('matricule')->values();
    }

    /** @param Collection<int, array{matricule: string, nom: string}> $personnes */
    private function simuler(Collection $personnes): int
    {
        $this->info("Simulation : {$personnes->count()} personne(s) seraient envoyées au lecteur.");
        $this->newLine();

        $this->table(
            ['Matricule', 'Numéro envoyé', 'Nom envoyé'],
            $personnes->map(fn (array $p) => [
                $p['matricule'],
                LecteurHikvision::numeroDepuisMatricule($p['matricule']),
                LecteurHikvision::nomCourt($p['nom']),
            ])->all(),
        );

        $this->line('Rien n’a été écrit dans le lecteur.');

        return self::SUCCESS;
    }

    /** @param Collection<int, array{matricule: string, nom: string}> $personnes */
    private function envoyer(LecteurHikvision $lecteur, Collection $personnes): int
    {
        try {
            $appareil = $lecteur->informations();
            $dejaLa = $lecteur->nombreDePersonnes();
        } catch (Throwable $souci) {
            $this->error('Le lecteur n’a pas répondu : '.$souci->getMessage());

            return self::FAILURE;
        }

        $modele = $appareil['model'] ?? 'modèle inconnu';
        $this->info("Lecteur joint : {$modele} ({$lecteur->adresse()}).");
        $this->line("Déjà enregistrées sur l’appareil : {$dejaLa} personne(s).");
        $this->newLine();

        $ajoutes = $modifies = 0;
        $echecs = [];
        $barre = $this->output->createProgressBar($personnes->count());
        $barre->start();

        foreach ($personnes as $personne) {
            try {
                $fait = $lecteur->enregistrer(
                    LecteurHikvision::numeroDepuisMatricule($personne['matricule']),
                    $personne['nom'],
                );

                $fait === 'ajoute' ? $ajoutes++ : $modifies++;
            } catch (Throwable $souci) {
                $echecs[] = [$personne['matricule'], $personne['nom'], $souci->getMessage()];
            }

            $barre->advance();
        }

        $barre->finish();
        $this->newLine(2);

        $this->info("{$ajoutes} ajoutée(s), {$modifies} mise(s) à jour.");

        if ($echecs !== []) {
            $this->newLine();
            $this->error(count($echecs).' refusée(s) par le lecteur :');
            $this->table(['Matricule', 'Nom', 'Motif'], $echecs);

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Les empreintes et les badges se posent maintenant sur l’appareil : chaque');
        $this->line('personne y est déjà, sous son numéro.');

        return self::SUCCESS;
    }
}
