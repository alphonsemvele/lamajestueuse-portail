<?php

namespace App\Console\Commands;

use App\Models\TacheLecteur;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Depose un ordre pour le lecteur de presence.
 *
 * L'ordre attend dans la file du portail ; le relais du site viendra le
 * prendre a son prochain passage. On peut donc commander le lecteur sans
 * etre sur place — et sans rien ouvrir sur le reseau de l'etablissement.
 */
class DeposerUnOrdreLecteur extends Command
{
    protected $signature = 'lecteur:demander
        {action : personnel, lister, cartes ou retirer}
        {--matricule=* : Pour « personnel », ne prend que ces matricules}
        {--carte=* : Pour « cartes », des paires « matricule=numero »}
        {--numero=* : Pour « retirer », les numéros à effacer du lecteur}';

    protected $description = 'Dépose un ordre que le relais exécutera devant le lecteur';

    public function handle(): int
    {
        $action = (string) $this->argument('action');

        if (! array_key_exists($action, TacheLecteur::ACTIONS)) {
            $this->error('Ordre inconnu. Les ordres possibles : '.implode(', ', array_keys(TacheLecteur::ACTIONS)).'.');

            return self::FAILURE;
        }

        $parametres = match ($action) {
            'personnel' => ['personnes' => $this->lePersonnel()],
            'cartes' => ['cartes' => $this->lesCartes()],
            'retirer' => ['numeros' => array_values(array_filter((array) $this->option('numero')))],
            default => [],
        };

        if ($action === 'personnel' && $parametres['personnes'] === []) {
            $this->warn('Personne à envoyer : aucun membre du personnel ne porte de matricule.');

            return self::SUCCESS;
        }

        $tache = TacheLecteur::create([
            'action' => $action,
            'parametres' => $parametres,
            'statut' => 'en_attente',
        ]);

        $this->info('Ordre n°'.$tache->id.' déposé : '.TacheLecteur::ACTIONS[$action].'.');
        $this->line('Le relais le prendra à son prochain passage.');

        return self::SUCCESS;
    }

    /**
     * Le personnel tel que le portail le connait : matricule et nom officiel.
     *
     * @return array<int, array{matricule: string, nom: string}>
     */
    private function lePersonnel(): array
    {
        $matricules = array_filter((array) $this->option('matricule'));

        return User::duPersonnel()
            ->whereNotNull('matricule')
            ->where('matricule', '!=', '')
            ->where('status', 'active')
            ->when($matricules !== [], fn ($q) => $q->whereIn('matricule', $matricules))
            ->orderBy('matricule')
            ->get()
            ->map(fn (User $personne) => [
                'matricule' => (string) $personne->matricule,
                'nom' => $personne->nomOfficiel(),
            ])
            ->all();
    }

    /**
     * Les cartes passees en ligne de commande : « matricule=numero ».
     *
     * @return array<string, string>
     */
    private function lesCartes(): array
    {
        $cartes = [];

        foreach ((array) $this->option('carte') as $paire) {
            [$matricule, $numero] = array_pad(explode('=', (string) $paire, 2), 2, '');

            if (trim($matricule) !== '' && trim($numero) !== '') {
                $cartes[trim($matricule)] = trim($numero);
            }
        }

        return $cartes;
    }
}
