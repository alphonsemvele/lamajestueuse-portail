<?php

namespace Database\Seeders;

use App\Models\CategorieRh;
use App\Models\Echelon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * La grille salariale officielle du groupe : 12 categories, 6 echelons.
 *
 * Reprise telle quelle du tableau papier « ECH / CAT » : les categories sont
 * les lignes (1 a 12), les echelons les colonnes (A a F). Elle remplace
 * l'extrait partiel repris d'IUM, qui ne couvrait que onze cases et portait
 * deux valeurs erronees.
 *
 * Deux cellules s'ecartent du pas de leur ligne, et sont saisies telles que
 * le document les porte : categorie 4 echelon F (118 070 la ou le pas de
 * 3 715 donnerait 116 070) et categorie 11 echelon A (351 658 la ou le pas
 * de 7 140 donnerait 351 648).
 *
 * Ne touche a rien si la grille est deja complete : une fois installee,
 * c'est le service RH qui la fait vivre depuis l'ecran des referentiels.
 */
class GrilleSalarialeSeeder extends Seeder
{
    /** Les colonnes du tableau, dans l'ordre. */
    private const ECHELONS = ['A', 'B', 'C', 'D', 'E', 'F'];

    /** De quoi garer l'existant le temps de renumeroter sans collision. */
    private const DECALAGE = 100;

    /** Les lignes : categorie => salaire de base de A a F. */
    private const GRILLE = [
        1 => [60000, 61500, 63000, 64500, 66000, 67500],
        2 => [69500, 71500, 73500, 75500, 77500, 79500],
        3 => [81880, 84260, 86640, 89020, 91400, 93780],
        4 => [97495, 101210, 104925, 108640, 112355, 118070],
        5 => [119695, 123320, 126945, 130570, 134195, 137820],
        6 => [142183, 146546, 150909, 155272, 159635, 163998],
        7 => [167198, 170398, 173598, 176798, 179998, 183198],
        8 => [192833, 202468, 212103, 221738, 231373, 241008],
        9 => [253258, 265508, 277758, 290008, 302258, 314508],
        10 => [319508, 324508, 329508, 334508, 339508, 344508],
        11 => [351658, 358788, 365928, 373068, 380208, 387348],
        12 => [399068, 410788, 422508, 434228, 445948, 457668],
    ];

    public function run(): void
    {
        if ($this->dejaEnPlace()) {
            $this->command?->info('La grille salariale est déjà complète : rien n’a été touché.');

            return;
        }

        $bilan = DB::transaction(function () {
            $gardes = $this->poser();

            return $this->retirerLeReste($gardes);
        });

        $this->command?->info(sprintf(
            'Grille salariale posée : %d catégories × %d échelons. '
            .'Retiré : %d échelon(s), %d catégorie(s). Désactivé faute de pouvoir le retirer : %d échelon(s), %d catégorie(s).',
            count(self::GRILLE), count(self::ECHELONS),
            $bilan['echelons_retires'], $bilan['categories_retirees'],
            $bilan['echelons_desactives'], $bilan['categories_desactivees'],
        ));
    }

    /** La grille est posee des que les 12 lignes portent leurs 6 colonnes. */
    private function dejaEnPlace(): bool
    {
        foreach (array_keys(self::GRILLE) as $numero) {
            $categorie = CategorieRh::where('libelle', $this->nom($numero))->first();

            if ($categorie === null) {
                return false;
            }

            $lettres = $categorie->echelons()->pluck('libelle')->all();

            if (array_diff(self::ECHELONS, $lettres) !== []) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pose les 72 cases. Une case deja presente est mise a jour plutot que
     * recreee : les contrats et les profils qui la visent gardent leur lien.
     *
     * @return array{categories: list<int>, echelons: list<int>}
     */
    private function poser(): array
    {
        $categories = [];
        $echelons = [];

        foreach (self::GRILLE as $numero => $salaires) {
            $categorie = CategorieRh::firstOrNew(['libelle' => $this->nom($numero)]);
            $categorie->actif = true;
            $categorie->save();

            $categories[] = $categorie->id;

            // Un numero d'echelon est unique dans sa categorie. L'extrait
            // d'IUM occupe deja les premiers rangs, souvent avec d'autres
            // lettres : on pousse l'existant hors de portee avant de
            // renumeroter, sinon la renumerotation se heurte a elle-meme.
            Echelon::where('categorie_rh_id', $categorie->id)
                ->update(['numero' => DB::raw('numero + '.self::DECALAGE)]);

            foreach (self::ECHELONS as $rang => $lettre) {
                $echelon = Echelon::firstOrNew([
                    'categorie_rh_id' => $categorie->id,
                    'libelle' => $lettre,
                ]);

                $echelon->numero = $rang + 1;
                $echelon->salaire = $salaires[$rang];
                $echelon->actif = true;
                $echelon->save();

                $echelons[] = $echelon->id;
            }
        }

        return ['categories' => $categories, 'echelons' => $echelons];
    }

    /**
     * Retire ce qui ne fait pas partie de la grille. Ce qu'un contrat ou un
     * profil vise encore n'est pas supprime — la paie passee y perdrait sa
     * reference — mais desactive : il ne se propose plus a la saisie, et le
     * service RH tranche.
     *
     * @param  array{categories: list<int>, echelons: list<int>}  $gardes
     * @return array<string, int>
     */
    private function retirerLeReste(array $gardes): array
    {
        $bilan = [
            'echelons_retires' => 0, 'echelons_desactives' => 0,
            'categories_retirees' => 0, 'categories_desactivees' => 0,
        ];

        $gardes_a_ranger = [];

        foreach (Echelon::whereNotIn('id', $gardes['echelons'])->orderBy('numero')->get() as $echelon) {
            if ($echelon->contrats()->exists() || $echelon->profils()->exists()) {
                $echelon->update(['actif' => false]);
                $bilan['echelons_desactives']++;

                if (in_array($echelon->categorie_rh_id, $gardes['categories'], true)) {
                    $gardes_a_ranger[$echelon->categorie_rh_id][] = $echelon;
                }

                continue;
            }

            $echelon->delete();
            $bilan['echelons_retires']++;
        }

        $this->ranger($gardes_a_ranger);

        foreach (CategorieRh::whereNotIn('id', $gardes['categories'])->get() as $categorie) {
            if ($categorie->echelons()->exists() || $this->porteUnProfil($categorie)) {
                $categorie->update(['actif' => false]);
                $bilan['categories_desactivees']++;

                continue;
            }

            $categorie->delete();
            $bilan['categories_retirees']++;
        }

        return $bilan;
    }

    /**
     * Ramene les echelons gardes hors grille a la suite des colonnes du
     * tableau : sans cela ils resteraient au numero de garage, et la liste
     * afficherait un « 102 » derriere le 6.
     *
     * @param  array<int, list<Echelon>>  $parCategorie
     */
    private function ranger(array $parCategorie): void
    {
        foreach ($parCategorie as $echelons) {
            $rang = count(self::ECHELONS);

            foreach ($echelons as $echelon) {
                $echelon->update(['numero' => ++$rang]);
            }
        }
    }

    private function porteUnProfil(CategorieRh $categorie): bool
    {
        return DB::table('profils_salaire')->where('categorie_rh_id', $categorie->id)->exists();
    }

    private function nom(int $numero): string
    {
        return "Catégorie {$numero}";
    }
}
