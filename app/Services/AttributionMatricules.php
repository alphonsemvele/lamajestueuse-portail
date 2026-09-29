<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Attribution des matricules du personnel.
 *
 * Format retenu : LM-00147 — prefixe du groupe et sequence continue sur cinq
 * chiffres. La sequence ne repart jamais a zero et un numero n'est jamais
 * reattribue : on prend donc toujours la suite du plus haut deja donne, meme
 * si le porteur a quitte le groupe.
 */
class AttributionMatricules
{
    public const PREFIXE = 'LM-';

    public const LONGUEUR = 5;

    /** Le plus haut numero deja attribue, 0 si aucun. */
    public function dernierNumero(): int
    {
        return User::whereNotNull('matricule')
            ->where('matricule', 'like', self::PREFIXE.'%')
            ->pluck('matricule')
            ->map(fn ($matricule) => $this->numeroDe($matricule))
            ->max() ?? 0;
    }

    /** Numero contenu dans un matricule, 0 s'il n'en porte pas. */
    public function numeroDe(?string $matricule): int
    {
        if (! $matricule || ! str_starts_with($matricule, self::PREFIXE)) {
            return 0;
        }

        $reste = substr($matricule, strlen(self::PREFIXE));

        // Un ancien matricule peut contenir autre chose que des chiffres :
        // il ne compte pas dans la sequence.
        return ctype_digit($reste) ? (int) $reste : 0;
    }

    public function formater(int $numero): string
    {
        return self::PREFIXE.str_pad((string) $numero, self::LONGUEUR, '0', STR_PAD_LEFT);
    }

    public function prochain(): string
    {
        return $this->formater($this->dernierNumero() + 1);
    }

    /**
     * Ce que recevrait chaque personne, sans rien enregistrer : de quoi
     * verifier avant d'attribuer.
     *
     * @param  Collection<int, User>  $personnes
     * @return array<int, array{id: int, nom: string, poste: ?string, entite: ?string, matricule: string}>
     */
    public function simuler(Collection $personnes): array
    {
        $numero = $this->dernierNumero();

        // Closure et non fonction flechee : celle-ci capture par valeur, et
        // le compteur repartirait du meme numero a chaque ligne.
        return $personnes->values()->map(function (User $personne) use (&$numero) {
            return [
                'id' => $personne->id,
                'nom' => $personne->fullName(),
                'poste' => $personne->poste,
                'entite' => $personne->entite,
                'email' => $personne->email,
                'matricule' => $this->formater(++$numero),
            ];
        })->all();
    }

    /**
     * Attribue les matricules aux personnes designees.
     *
     * Par defaut, celles qui en portent deja un sont laissees tranquilles :
     * la regle du groupe est qu'un matricule ne change pas. `$remplacer` leve
     * cette reserve, pour les corrections decidees en connaissance de cause ;
     * les nouveaux numeros prennent alors la suite du plus haut attribue, et
     * l'ancien numero reste brule — il ne sera donne a personne d'autre.
     *
     * @param  array<int, int>  $identifiants
     * @return array<int, array{id: int, nom: string, matricule: string, ancien: ?string}>
     */
    public function attribuer(array $identifiants, bool $remplacer = false): array
    {
        return DB::transaction(function () use ($identifiants, $remplacer) {
            $numero = $this->dernierNumero();
            $attribues = [];

            $personnes = User::whereIn('id', $identifiants)
                ->when(! $remplacer, fn ($q) => $q->whereNull('matricule'))
                ->lockForUpdate()
                ->orderBy('lastname')->orderBy('name')
                ->get();

            foreach ($personnes as $personne) {
                $ancien = $personne->matricule;
                $personne->update(['matricule' => $this->formater(++$numero)]);

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
}
