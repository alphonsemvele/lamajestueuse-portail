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
 * mise a jour, jamais dupliquee, et ce qui a ete enrole sur l'appareil
 * n'est pas touche.
 *
 * Elle se lance depuis un poste du site : l'adresse du lecteur est privee,
 * le portail en ligne ne l'atteint pas.
 */
class EnregistrerPersonnelDansLecteur extends Command
{
    protected $signature = 'hikvision:personnel
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

        $personnes = $this->personnes();

        if ($personnes->isEmpty()) {
            $this->warn('Personne à envoyer : aucun membre du personnel ne porte de matricule.');

            return self::SUCCESS;
        }

        if ($this->option('simuler')) {
            return $this->simuler($personnes);
        }

        return $this->envoyer($lecteur, $personnes);
    }

    /**
     * Qui part dans le lecteur : le personnel du groupe qui porte un
     * matricule. Sans matricule, pas de numero a poser sur l'appareil.
     *
     * @return Collection<int, User>
     */
    private function personnes(): Collection
    {
        $matricules = array_filter((array) $this->option('matricule'));

        return User::duPersonnel()
            ->whereNotNull('matricule')
            ->where('matricule', '!=', '')
            ->when(! $this->option('tous'), fn ($q) => $q->where('status', 'active'))
            ->when($matricules !== [], fn ($q) => $q->whereIn('matricule', $matricules))
            ->orderBy('matricule')
            ->get();
    }

    /** @param Collection<int, User> $personnes */
    private function simuler(Collection $personnes): int
    {
        $this->info("Simulation : {$personnes->count()} personne(s) seraient envoyées au lecteur.");
        $this->newLine();

        $this->table(
            ['Matricule', 'Numéro envoyé', 'Nom envoyé'],
            $personnes->map(fn (User $p) => [
                $p->matricule,
                LecteurHikvision::numeroDe($p),
                LecteurHikvision::nomDe($p),
            ])->all(),
        );

        $this->line('Rien n’a été écrit dans le lecteur.');

        return self::SUCCESS;
    }

    /** @param Collection<int, User> $personnes */
    private function envoyer(LecteurHikvision $lecteur, Collection $personnes): int
    {
        try {
            $appareil = $lecteur->informations();
        } catch (Throwable $souci) {
            $this->error('Le lecteur n’a pas répondu : '.$souci->getMessage());

            return self::FAILURE;
        }

        $modele = $appareil['model'] ?? 'modèle inconnu';
        $this->info("Lecteur joint : {$modele} ({$lecteur->adresse()}).");
        $this->line("Déjà enregistrées sur l’appareil : {$lecteur->nombreDePersonnes()} personne(s).");
        $this->newLine();

        $ajoutes = $modifies = 0;
        $echecs = [];
        $barre = $this->output->createProgressBar($personnes->count());
        $barre->start();

        foreach ($personnes as $personne) {
            try {
                $lecteur->enregistrer($personne) === 'ajoute' ? $ajoutes++ : $modifies++;
            } catch (Throwable $souci) {
                $echecs[] = [$personne->matricule, LecteurHikvision::nomDe($personne), $souci->getMessage()];
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
        $this->line('Les empreintes et les badges se posent maintenant sur l’appareil : chaque personne');
        $this->line('y est déjà, sous son numéro.');

        return self::SUCCESS;
    }
}
