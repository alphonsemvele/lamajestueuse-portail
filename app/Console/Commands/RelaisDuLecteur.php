<?php

namespace App\Console\Commands;

use App\Services\JetonRelais;
use App\Services\LecteurHikvision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Le relais entre le portail et le lecteur de presence.
 *
 * L'adresse du lecteur est privee : le portail, chez l'hebergeur, ne
 * l'atteindra jamais. Ce relais tourne sur un poste du site. Il sort vers le
 * portail, prend les ordres qui l'y attendent, les execute devant l'appareil
 * et rapporte ce qu'il a fait.
 *
 * Le sens compte : c'est toujours le relais qui appelle. Aucun port n'est
 * ouvert sur le reseau de l'etablissement, et le lecteur reste invisible
 * depuis l'exterieur.
 */
class RelaisDuLecteur extends Command
{
    protected $signature = 'hikvision:relais
        {--une-fois : Prend les ordres en attente, les execute, et s’arrete}
        {--intervalle= : Secondes entre deux passages}';

    protected $description = 'Exécute devant le lecteur les ordres déposés sur le portail';

    public function handle(): int
    {
        $portail = (array) config('hikvision.portail');
        $lecteur = LecteurHikvision::depuisLaConfiguration();

        if (blank($portail['secret'] ?? null)) {
            $this->error('Le relais n’a pas son secret.');
            $this->line('Renseignez HIKVISION_RELAIS_SECRET dans le .env de ce poste.');
            $this->line('Le secret se lit au portail : Administration › Applications › Relais du lecteur.');

            return self::FAILURE;
        }

        if (! $lecteur->estConfigure()) {
            $this->error('Le lecteur n’est pas configuré sur ce poste.');

            return self::FAILURE;
        }

        $adresse = rtrim((string) $portail['url'], '/');
        $intervalle = max(5, (int) ($this->option('intervalle') ?: $portail['intervalle']));

        $this->info('Relais en service.');
        $this->line('  portail : '.$adresse);
        $this->line('  lecteur : '.$lecteur->adresse());
        $this->newLine();

        do {
            try {
                $faites = $this->unPassage($lecteur, $portail, $adresse);

                if ($faites === 0 && ! $this->option('une-fois')) {
                    // Rien a faire : on ne dit rien, le journal resterait
                    // illisible a raison d'une ligne toutes les quinze secondes.
                    usleep($intervalle * 1_000_000);
                }
            } catch (Throwable $souci) {
                $this->error('['.now()->format('H:i:s').'] '.$souci->getMessage());
                usleep($intervalle * 1_000_000);
            }
        } while (! $this->option('une-fois'));

        return self::SUCCESS;
    }

    /**
     * Un passage : prendre ce qui attend, l'executer, rapporter.
     *
     * @param  array<string, mixed>  $portail
     */
    private function unPassage(LecteurHikvision $lecteur, array $portail, string $adresse): int
    {
        $reponse = Http::withToken($this->jeton($portail))
            ->timeout(30)->acceptJson()
            ->get($adresse.'/api/lecteur/taches');

        if ($reponse->status() === 401) {
            throw new \RuntimeException('Le portail a refusé le relais : vérifiez son secret.');
        }

        if ($reponse->failed()) {
            throw new \RuntimeException('Le portail a répondu '.$reponse->status().'.');
        }

        $taches = $reponse->json('taches') ?? [];

        foreach ($taches as $tache) {
            $this->line('['.now()->format('H:i:s').'] '.$tache['action'].' n°'.$tache['id']);

            try {
                $resultat = $this->executer($lecteur, (string) $tache['action'], (array) ($tache['parametres'] ?? []));
                $this->rapporter($portail, $adresse, (int) $tache['id'], $resultat, null);
                $this->line('   <fg=green>fait</>');
            } catch (Throwable $souci) {
                $this->rapporter($portail, $adresse, (int) $tache['id'], null, $souci->getMessage());
                $this->error('   '.$souci->getMessage());
            }
        }

        return count($taches);
    }

    /**
     * Ce que le relais sait faire devant l'appareil.
     *
     * @param  array<string, mixed>  $parametres
     * @return array<string, mixed>
     */
    private function executer(LecteurHikvision $lecteur, string $action, array $parametres): array
    {
        return match ($action) {
            'personnel' => $this->poserLePersonnel($lecteur, $parametres),
            'cartes' => $this->poserLesCartes($lecteur, $parametres),
            'lister' => [
                'appareil' => $lecteur->informations()['model'] ?? null,
                'personnes' => $lecteur->personnes(),
                'cartes' => $lecteur->cartes(),
            ],
            'retirer' => $this->retirer($lecteur, $parametres),
            default => throw new \RuntimeException('Ordre inconnu : '.$action),
        };
    }

    /**
     * @param  array<string, mixed>  $parametres
     * @return array<string, mixed>
     */
    private function poserLePersonnel(LecteurHikvision $lecteur, array $parametres): array
    {
        $ajoutes = $modifies = 0;
        $refus = [];

        foreach ((array) ($parametres['personnes'] ?? []) as $personne) {
            $numero = LecteurHikvision::numeroDepuisMatricule($personne['matricule'] ?? null);

            if ($numero === '') {
                $refus[] = ($personne['matricule'] ?? '?').' : aucun chiffre dans le matricule';

                continue;
            }

            try {
                $lecteur->enregistrer($numero, (string) ($personne['nom'] ?? ''))
                    === 'ajoute' ? $ajoutes++ : $modifies++;
            } catch (Throwable $souci) {
                $refus[] = ($personne['matricule'] ?? $numero).' : '.$souci->getMessage();
            }
        }

        return ['ajoutes' => $ajoutes, 'modifies' => $modifies, 'refus' => $refus];
    }

    /**
     * @param  array<string, mixed>  $parametres
     * @return array<string, mixed>
     */
    private function poserLesCartes(LecteurHikvision $lecteur, array $parametres): array
    {
        $posees = 0;
        $refus = [];

        foreach ((array) ($parametres['cartes'] ?? []) as $matricule => $carte) {
            $numero = LecteurHikvision::numeroDepuisMatricule((string) $matricule);

            try {
                $lecteur->poserCarte($numero, (string) $carte);
                $posees++;
            } catch (Throwable $souci) {
                $refus[] = $matricule.' : '.$souci->getMessage();
            }
        }

        return ['posees' => $posees, 'refus' => $refus];
    }

    /**
     * @param  array<string, mixed>  $parametres
     * @return array<string, mixed>
     */
    private function retirer(LecteurHikvision $lecteur, array $parametres): array
    {
        $retires = [];

        foreach ((array) ($parametres['numeros'] ?? []) as $numero) {
            $lecteur->retirer((string) $numero);
            $retires[] = (string) $numero;
        }

        return ['retires' => $retires];
    }

    /**
     * @param  array<string, mixed>  $portail
     * @param  array<string, mixed>|null  $resultat
     */
    private function rapporter(array $portail, string $adresse, int $tache, ?array $resultat, ?string $erreur): void
    {
        Http::withToken($this->jeton($portail))
            ->timeout(30)->acceptJson()
            ->post($adresse.'/api/lecteur/taches/'.$tache, [
                'resultat' => $resultat,
                'erreur' => $erreur,
            ]);
    }

    /** @param array<string, mixed> $portail */
    private function jeton(array $portail): string
    {
        return JetonRelais::signer(
            (string) $portail['client_id'],
            (string) $portail['secret'],
            'lecteur',
        );
    }
}
