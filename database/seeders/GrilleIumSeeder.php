<?php

namespace Database\Seeders;

use App\Models\CategorieRh;
use App\Models\Echelon;
use App\Models\Indemnite;
use App\Models\ProfilSalaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reprise de la grille de paie configuree dans IUM.
 *
 * Categories, echelons, indemnites et profils viennent tels quels de la
 * grille « LA MAJESTUEUSE SARL » de mai 2026, que l'IUM utilisait avant que
 * la paie ne passe au portail. Aucune retenue n'y etait configuree : le net
 * s'y calcule base + indemnites.
 *
 * L'affectation des personnes n'est pas reprise : dans le portail, c'est le
 * contrat qui porte le profil, et un agent peut en avoir un par institut.
 *
 * Ne fait rien si une grille existe deja : on ne remplace jamais ce que le
 * service RH a saisi ou corrige depuis.
 */
class GrilleIumSeeder extends Seeder
{
    /** Les indemnites de la grille. Toutes en montant fixe. */
    private const INDEMNITES = [
        'transport' => 'Indemnité de transport',
        'logement' => 'Logement',
        'caisse' => 'Prime de caisse',
        'technicite' => 'Technicité',
        'anciennete' => 'Ancienneté',
    ];

    /** [categorie, numero, libelle, salaire de base] */
    private const ECHELONS = [
        [1, 1, 'A', 60000],
        [1, 2, 'A (temps partiel)', 40000],
        [3, 1, 'A', 81880],
        [5, 1, 'F', 137820],
        [5, 2, 'C', 126945],
        [6, 1, 'A', 142183],
        [7, 1, 'F', 20000],
        [7, 2, 'A', 167198],
        [9, 1, 'A', 253258],
        [10, 1, 'B', 324508],
        [11, 1, 'A', 351658],
    ];

    /** nom => [categorie, echelon (categorie-libelle), indemnites fixes] */
    private const PROFILS = [
        'Coordonnateur de filière' => [7, '7-F', [
            'transport' => 183198, 'logement' => 30000, 'technicite' => 30000, 'anciennete' => 7687,
        ]],
        'Directeur / Cadre supérieur' => [9, '9-A', [
            'transport' => 10000, 'logement' => 15000, 'caisse' => 20000, 'technicite' => 25000, 'anciennete' => 9299,
        ]],
        'Comptable' => [7, '7-A', [
            'transport' => 10000, 'logement' => 25000, 'technicite' => 12500, 'anciennete' => 6546,
        ]],
        'Assistant(e) de direction (Cat 6)' => [6, '6-A', [
            'transport' => 10000, 'logement' => 20000, 'technicite' => 10000, 'anciennete' => 7787,
        ]],
        'Gestionnaire de stock' => [3, '3-A', [
            'transport' => 10000, 'logement' => 12800, 'caisse' => 10000, 'technicite' => 6000, 'anciennete' => 7052,
        ]],
        'Directeur (Cat 10)' => [10, '10-B', [
            'transport' => 10000, 'logement' => 30000, 'anciennete' => 7000,
        ]],
        'Directeur ISM (Cat 11)' => [11, '11-A', [
            'transport' => 35000, 'logement' => 25000, 'caisse' => 25000, 'technicite' => 35000, 'anciennete' => 12465,
        ]],
        'Agent de scolarité' => [5, '5-F', [
            'transport' => 10000, 'logement' => 12500, 'technicite' => 6825, 'anciennete' => 7000,
        ]],
        'Assistant(e) de direction (Cat 5)' => [5, '5-C', [
            'transport' => 10000, 'logement' => 12800, 'technicite' => 6825, 'anciennete' => 7000,
        ]],
        'Chauffeur' => [1, '1-A', [
            'transport' => 10000, 'logement' => 10000, 'anciennete' => 5000,
        ]],
        'Chauffeur (temps partiel)' => [1, '1-A (temps partiel)', [
            'transport' => 10000, 'logement' => 12800, 'caisse' => 7135, 'technicite' => 6825, 'anciennete' => 7687,
        ]],
    ];

    public function run(): void
    {
        if (CategorieRh::exists() || ProfilSalaire::exists()) {
            $this->command?->info('Une grille existe déjà : rien n’a été repris.');

            return;
        }

        DB::transaction(function () {
            $indemnites = $this->indemnites();
            [$categories, $echelons] = $this->grille();
            $this->profils($indemnites, $categories, $echelons);
        });

        $this->command?->info(sprintf(
            'Grille IUM reprise : %d catégories, %d échelons, %d indemnités, %d profils.',
            CategorieRh::count(), Echelon::count(), Indemnite::count(), ProfilSalaire::count(),
        ));
    }

    /** @return array<string, Indemnite> */
    private function indemnites(): array
    {
        $creees = [];

        foreach (self::INDEMNITES as $cle => $libelle) {
            $creees[$cle] = Indemnite::create(['libelle' => $libelle, 'imposable' => true, 'actif' => true]);
        }

        return $creees;
    }

    /** @return array{0: array<int, CategorieRh>, 1: array<string, Echelon>} */
    private function grille(): array
    {
        $categories = [];
        $echelons = [];

        foreach (self::ECHELONS as [$numeroCategorie, $numero, $libelle, $salaire]) {
            $categories[$numeroCategorie] ??= CategorieRh::create([
                'libelle' => "Catégorie {$numeroCategorie}",
                'actif' => true,
            ]);

            $echelons["{$numeroCategorie}-{$libelle}"] = Echelon::create([
                'categorie_rh_id' => $categories[$numeroCategorie]->id,
                'numero' => $numero,
                'libelle' => $libelle,
                'salaire' => $salaire,
                'actif' => true,
            ]);
        }

        return [$categories, $echelons];
    }

    /**
     * @param  array<string, Indemnite>  $indemnites
     * @param  array<int, CategorieRh>  $categories
     * @param  array<string, Echelon>  $echelons
     */
    private function profils(array $indemnites, array $categories, array $echelons): void
    {
        foreach (self::PROFILS as $nom => [$categorie, $cleEchelon, $montants]) {
            $profil = ProfilSalaire::create([
                'nom' => $nom,
                'categorie_rh_id' => $categories[$categorie]->id,
                'echelon_id' => $echelons[$cleEchelon]->id,
                'actif' => true,
            ]);

            $profil->indemnites()->sync(
                collect($montants)->mapWithKeys(fn ($montant, $cle) => [
                    $indemnites[$cle]->id => ['type_calcul' => 'fixe', 'valeur' => $montant],
                ])->all()
            );
        }
    }
}
