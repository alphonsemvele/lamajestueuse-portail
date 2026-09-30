<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Attribution des matricules du personnel.
 *
 * Format : LM-260147 — prefixe du groupe, annee de recrutement sur deux
 * chiffres, sequence de quatre chiffres repartant a chaque annee, le tout
 * sans separateur interne.
 *
 * L'annee vient du premier contrat de la personne quand il est connu, sinon
 * de la date de creation de son compte : c'est ce que le portail sait de plus
 * proche de son arrivee. Elle reste modifiable a la main sur la fiche, pour
 * les reprises d'anciennete que le portail ignore.
 *
 * Un numero n'est jamais reattribue, meme libere par un depart ou par un
 * remplacement : la sequence d'une annee ne repart jamais en arriere.
 */
class AttributionMatricules
{
    public const PREFIXE = 'LM-';

    /** Chiffres de la sequence, a l'interieur d'une annee. */
    public const LONGUEUR = 4;

    /**
     * Ce qui fait un matricule du groupe : LM-, deux chiffres d'annee, quatre
     * de sequence. Un numero plus court ou plus long vient d'un autre systeme
     * et ne compte pas dans la numerotation.
     */
    public const GABARIT = '/^LM-(\d{2})(\d{4})$/';

    /**
     * Le plus haut numero deja attribue pour cette annee, 0 si aucun.
     */
    public function dernierNumero(int $annee): int
    {
        $debut = self::PREFIXE.$this->deuxChiffres($annee);

        return User::whereNotNull('matricule')
            ->where('matricule', 'like', $debut.'%')
            ->pluck('matricule')
            ->map(fn ($matricule) => $this->sequenceDe($matricule))
            ->max() ?? 0;
    }

    /**
     * Sequence contenue dans un matricule, 0 s'il n'est pas a ce format.
     *
     * Un matricule d'un autre format — celui d'un ancien systeme, ou la
     * sequence continue d'avant — ne compte pas dans la numerotation.
     */
    public function sequenceDe(?string $matricule): int
    {
        if (! $matricule || ! preg_match(self::GABARIT, $matricule, $trouve)) {
            return 0;
        }

        return (int) $trouve[2];
    }

    /** Annee portee par un matricule, null s'il n'en porte pas. */
    public function anneeDe(?string $matricule): ?int
    {
        if (! $matricule || ! preg_match(self::GABARIT, $matricule, $trouve)) {
            return null;
        }

        return 2000 + (int) $trouve[1];
    }

    public function formater(int $annee, int $sequence): string
    {
        return self::PREFIXE.$this->deuxChiffres($annee)
            .str_pad((string) $sequence, self::LONGUEUR, '0', STR_PAD_LEFT);
    }

    /** Le prochain numero libre de l'annee demandee. */
    public function prochain(?int $annee = null): string
    {
        $annee ??= (int) date('Y');

        return $this->formater($annee, $this->dernierNumero($annee) + 1);
    }

    /**
     * Annee de recrutement retenue pour une personne : celle de son premier
     * contrat, a defaut celle de la creation de son compte.
     */
    public function anneeDeRecrutement(User $personne): int
    {
        $debut = $personne->agent?->contrats()->min('date_debut');

        if ($debut) {
            return (int) substr((string) $debut, 0, 4);
        }

        return (int) ($personne->created_at?->year ?? date('Y'));
    }

    /**
     * Ce que recevrait chaque personne, sans rien enregistrer.
     *
     * @param  Collection<int, User>  $personnes
     * @return array<int, array{id: int, nom: string, poste: ?string, entite: ?string, email: ?string, annee: int, matricule: string}>
     */
    public function simuler(Collection $personnes): array
    {
        // Un compteur par annee : les sequences sont independantes.
        $compteurs = [];

        return $personnes->values()->map(function (User $personne) use (&$compteurs) {
            $annee = $this->anneeDeRecrutement($personne);
            $compteurs[$annee] ??= $this->dernierNumero($annee);

            return [
                'id' => $personne->id,
                'nom' => $personne->fullName(),
                'poste' => $personne->poste,
                'entite' => $personne->entite,
                'email' => $personne->email,
                'annee' => $annee,
                'matricule' => $this->formater($annee, ++$compteurs[$annee]),
            ];
        })->all();
    }

    /**
     * Attribue les matricules aux personnes designees.
     *
     * Par defaut, celles qui en portent deja un sont laissees tranquilles :
     * la regle du groupe est qu'un matricule ne change pas. `$remplacer` leve
     * cette reserve, pour les corrections decidees en connaissance de cause ;
     * l'ancien numero reste alors brule, il ne sera donne a personne d'autre.
     *
     * @param  array<int, int>  $identifiants
     * @return array<int, array{id: int, nom: string, matricule: string, ancien: ?string}>
     */
    public function attribuer(array $identifiants, bool $remplacer = false): array
    {
        return DB::transaction(function () use ($identifiants, $remplacer) {
            $compteurs = [];
            $attribues = [];

            $personnes = User::whereIn('id', $identifiants)
                ->when(! $remplacer, fn ($q) => $q->whereNull('matricule'))
                ->with('agent')
                ->lockForUpdate()
                ->orderBy('lastname')->orderBy('name')
                ->get();

            foreach ($personnes as $personne) {
                $annee = $this->anneeDeRecrutement($personne);
                $compteurs[$annee] ??= $this->dernierNumero($annee);

                $ancien = $personne->matricule;
                $personne->update(['matricule' => $this->formater($annee, ++$compteurs[$annee])]);

                $attribues[] = [
                    'id' => $personne->id,
                    'nom' => $personne->fullName(),
                    'matricule' => $personne->matricule,
                    'ancien' => $ancien,
                ];
            }

            return $attribues;
        });
    }

    private function deuxChiffres(int $annee): string
    {
        return str_pad((string) ($annee % 100), 2, '0', STR_PAD_LEFT);
    }
}
